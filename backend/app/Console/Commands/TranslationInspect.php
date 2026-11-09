<?php

namespace App\Console\Commands;

use App\Models\QuranTranslationEdition;
use App\Models\QuranTranslationFlag;
use App\Models\User;
use App\Services\Quran\Translations\TranslationContent;
use App\Services\Quran\Translations\TranslationHistory;
use App\Services\Quran\Translations\TranslationReviewWorkflow;
use App\Services\Quran\Translations\TranslationTextInspector;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Checks a stored edition for damaged text, lists flagged verses for a reviewer, and records how
 * flags were resolved. It never changes translation text.
 */
class TranslationInspect extends Command
{
    protected $signature = 'quran:translation-inspect
        {identifier : Edition identifier}
        {--record : Store the findings as flags for review}
        {--resolve=* : Flag ids a reviewer has checked}
        {--reviewer= : Email of the reviewer resolving flags}
        {--notes= : What the reviewer checked}';

    protected $description = 'Inspect a translation edition and manage its review flags';

    public function handle(TranslationTextInspector $inspector, TranslationHistory $history, TranslationReviewWorkflow $workflow): int
    {
        $edition = QuranTranslationEdition::query()->where('identifier', $this->argument('identifier'))->first();
        if (! $edition) {
            $this->error('No such edition.');

            return self::FAILURE;
        }

        if ($ids = $this->option('resolve')) {
            $reviewer = User::query()->where('email', $this->option('reviewer'))->first();
            if (! $reviewer) {
                $this->error('Give --reviewer= to resolve flags.');

                return self::FAILURE;
            }
            try {
                $n = $workflow->resolveFlags($edition, $ids, $reviewer, (string) $this->option('notes'));
            } catch (DomainException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $this->info("Resolved {$n} flags.");

            return self::SUCCESS;
        }

        $findings = [];
        foreach (TranslationContent::stored($edition->id)->verses as $key => $row) {
            $issues = array_merge($inspector->inspect($edition->language, $row['text'], $row['footnotes']), $inspector->footnoteIssues($row['text'], $row['footnotes']));
            foreach ($issues as $issue) {
                $findings[] = ['verse' => $key, 'severity' => $issue->severity, 'code' => $issue->code, 'detail' => $issue->detail];
            }
        }
        $byVerse = collect($findings)->groupBy('verse');
        $withErrors = collect($findings)->where('severity', 'error')->pluck('verse')->unique();
        $this->line("{$edition->identifier}: {$byVerse->count()} verses flagged, {$withErrors->count()} with errors.");
        foreach ($byVerse as $verse => $list) {
            $this->line("  {$verse}: ".$list->map(fn ($f) => "[{$f['severity']}] {$f['code']}")->unique()->implode(', '));
        }

        if ($this->option('record') && $findings) {
            $verseIds = DB::table('quran_verses')->pluck('id', 'verse_key')->all();
            $open = QuranTranslationFlag::query()->where('quran_translation_edition_id', $edition->id)->whereNull('resolved_at')
                ->get(['quran_verse_id', 'code'])->map(fn ($f) => "{$f->quran_verse_id}|{$f->code}")->flip();
            $new = array_values(array_filter($findings, fn ($f) => ! isset($open[$verseIds[$f['verse']].'|'.$f['code']])));
            $history->storeFlags($edition, $edition->current_revision, $new, $verseIds);
            $this->info('Recorded '.count($new).' new flags for review. Open flags already recorded were not duplicated.');
        }

        return self::SUCCESS;
    }
}
