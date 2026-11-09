<?php

namespace App\Console\Commands;

use App\Models\QuranTranslationEdition;
use App\Services\Quran\Translations\TranslationAiSync;
use Illuminate\Console\Command;

/**
 * Shows, retries or withdraws an edition's AI copy. Approval itself only happens through the
 * review workflow; this never approves anything.
 */
class TranslationAiSyncCommand extends Command
{
    protected $signature = 'quran:translation-ai-sync
        {identifier : Edition identifier, such as en.clearquran}
        {--revoke : Switch the AI copy off}
        {--status : Only show the current state}';

    protected $description = 'Show, retry or withdraw an approved translation\'s AI copy';

    public function handle(TranslationAiSync $sync): int
    {
        $edition = QuranTranslationEdition::query()->where('identifier', $this->argument('identifier'))->first();
        if (! $edition) {
            $this->error('No such edition.');

            return self::FAILURE;
        }
        if ($this->option('revoke')) {
            $sync->revoke($edition);
        } elseif (! $this->option('status')) {
            if ($edition->review_status !== QuranTranslationEdition::STATUS_APPROVED) {
                $this->error("{$edition->identifier} is {$edition->review_status}. Only approved editions are copied for the AI.");

                return self::FAILURE;
            }
            $sync->sync($edition);
        }
        $edition->refresh();
        $eligible = QuranTranslationEdition::query()->whereKey($edition->id)->aiEligible()->exists();
        $this->line("{$edition->identifier}: review {$edition->review_status}, AI copy {$edition->ai_sync_status}"
            .($edition->ai_synced_at ? " at {$edition->ai_synced_at}" : '').', AI may use it: '.($eligible ? 'yes' : 'no'));
        if ($edition->ai_sync_error) {
            $this->warn("Last error: {$edition->ai_sync_error}");
        }

        return $eligible || $this->option('status') || $this->option('revoke') ? self::SUCCESS : self::FAILURE;
    }
}
