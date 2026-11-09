<?php

namespace App\Console\Commands;

use App\Models\QuranTranslationEdition;
use Illuminate\Console\Command;

class TranslationHistoryCommand extends Command
{
    protected $signature = 'quran:translation-history {identifier : Edition identifier, such as bn.bengali}';

    protected $description = 'List an edition\'s revisions, review decisions and open flags';

    public function handle(): int
    {
        $edition = QuranTranslationEdition::query()->where('identifier', $this->argument('identifier'))->first();
        if (! $edition) {
            $this->error('No such edition.');

            return self::FAILURE;
        }
        $this->line("{$edition->identifier}: {$edition->name} | status {$edition->review_status} | "
            .($edition->is_active ? 'shown in app' : 'hidden').' | current revision '.($edition->current_revision ?? 'none')
            .' | version '.($edition->version ?? '-'));
        $this->table(['Revision', 'Kind', 'Restores', 'Version', 'Verses', 'Footnotes', 'Checksum', 'When'], $edition->revisions->map(fn ($r) => [
            $r->revision, $r->kind, $r->restored_revision ?? '', $r->source_version ?? '', $r->verse_count, $r->footnote_count, substr($r->checksum, 0, 12), $r->created_at,
        ])->all());
        $this->table(['From', 'To', 'Revision', 'Reviewer', 'Notes', 'When'], $edition->reviews()->with('reviewer')->get()->map(fn ($r) => [
            $r->from_status, $r->to_status, $r->revision ?? '', $r->reviewer?->email ?? 'system', $r->notes, $r->created_at,
        ])->all());
        $this->line('Open flags: '.$edition->flags()->whereNull('resolved_at')->count().". See quran:translation-inspect {$edition->identifier}.");

        return self::SUCCESS;
    }
}
