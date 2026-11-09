<?php

namespace App\Console\Commands;

use App\Models\QuranTranslationEdition;
use App\Models\User;
use App\Services\Quran\Translations\TranslationHistory;
use Illuminate\Console\Command;

class TranslationRollback extends Command
{
    protected $signature = 'quran:translation-rollback
        {identifier : Edition identifier}
        {revision : Revision number to restore}
        {--by= : Email of the Scholar Reviewer or Super Administrator doing this}
        {--dry-run : Check the revision can be restored, without changing anything}';

    protected $description = 'Restore an earlier revision of a translation edition exactly';

    public function handle(TranslationHistory $history): int
    {
        $edition = QuranTranslationEdition::query()->where('identifier', $this->argument('identifier'))->first();
        $user = User::query()->where('email', $this->option('by'))->first();
        if (! $edition || ! $user || ! $user->hasAdminRole(User::ROLE_SCHOLAR_REVIEWER, User::ROLE_SUPER_ADMIN)) {
            $this->error('Give an existing edition, and --by= the email of a Scholar Reviewer or Super Administrator.');

            return self::FAILURE;
        }
        $log = $history->rollback($edition, (int) $this->argument('revision'), $user, (bool) $this->option('dry-run'));
        if ($log->status === 'failed') {
            $this->error(implode(' ', $log->error_details['validation'] ?? []));

            return self::FAILURE;
        }
        $this->info($log->status === 'dry_run'
            ? "Revision {$this->argument('revision')} is complete and matches its checksum, so it can be restored."
            : "Restored revision {$this->argument('revision')} as revision {$log->options['new_revision']}. The edition is now under review.");

        return self::SUCCESS;
    }
}
