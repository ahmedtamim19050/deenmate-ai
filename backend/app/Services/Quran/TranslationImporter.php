<?php

namespace App\Services\Quran;

use App\Models\QuranImportLog;
use App\Models\QuranTranslationEdition;
use App\Models\QuranTranslationRevision;
use App\Services\Quran\Translations\TranslationContent;
use App\Services\Quran\Translations\TranslationHistory;
use App\Services\Quran\Translations\TranslationTextInspector;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Imports one translation edition into quran_translation_editions and quran_translations.
 *
 * - Every verse is inspected first. An error blocks the whole import and nothing is written,
 *   unless a reviewer has acknowledged that verse, in which case it is imported and flagged.
 * - Text and footnotes are stored exactly as published. Footnotes are also indexed by marker.
 * - Each import that changes content creates a complete, checksummed revision, and content found
 *   before history existed is saved first as a baseline, so any earlier state can be restored.
 * - Everything is written in one transaction and read back against the checksum before commit.
 *   A failed import leaves the stored edition exactly as it was.
 * - A new edition is "imported" and inactive. New content for a reviewed edition sends it back
 *   to "imported" and switches it off, so nothing unreviewed is shown or used by the AI.
 *
 * Only call this for an edition whose licence has been confirmed.
 */
class TranslationImporter
{
    public const IMPORT_TYPE = 'translation';

    public function __construct(
        private TranslationTextInspector $inspector,
        private TranslationHistory $history,
    ) {}

    /**
     * @param  array<string, mixed>  $edition  identifier, language, name, license, attribution_text, translator or publisher,
     *                                         and optionally copyright_holder, source_url, license_url, version, source_updated_at,
     *                                         import_source, source_key, quran_data_source_id
     * @param  iterable<string, string|array{text: string, footnotes?: ?string}>  $verses  "2:255" => text, or text with footnotes
     * @param  list<string>  $acknowledged  verse keys whose errors a reviewer has seen and accepted for import as flagged records
     */
    public function import(array $edition, iterable $verses, bool $dryRun = false, array $acknowledged = []): QuranImportLog
    {
        $this->assertAttribution($edition);
        $log = QuranImportLog::create([
            'import_type' => self::IMPORT_TYPE,
            'quran_data_source_id' => $edition['quran_data_source_id'] ?? null,
            'status' => QuranImportLog::STATUS_RUNNING,
            'options' => ['edition' => $edition['identifier'], 'version' => $edition['version'] ?? null, 'dry_run' => $dryRun, 'acknowledged' => $acknowledged],
            'total_records' => QuranStructure::VERSES,
            'started_at' => now(),
        ]);

        try {
            [$content, $problems] = $this->collect($verses);
            [$blocking, $flags] = $this->inspect($edition['language'], $content, $acknowledged);
            $problems = array_merge($problems, $blocking);
            if ($problems !== []) {
                return $this->fail($log, $problems);
            }
            $verseIds = DB::table('quran_verses')->pluck('id', 'verse_key')->all();
            if ($missing = array_diff(array_keys($content->verses), array_keys($verseIds))) {
                return $this->fail($log, ['Verses not in the database yet: '.implode(', ', array_slice($missing, 0, 20)).'. Import the Arabic text first.']);
            }

            $existing = QuranTranslationEdition::query()->where('identifier', $edition['identifier'])->first();
            $stored = $existing ? TranslationContent::stored($existing->id) : new TranslationContent([]);
            $checksum = $content->checksum();
            [$new, $changed, $same] = $this->diff($stored, $content);

            if ($dryRun || ($existing && $stored->checksum() === $checksum)) {
                $log->update([
                    'status' => $dryRun ? QuranImportLog::STATUS_DRY_RUN : QuranImportLog::STATUS_COMPLETED,
                    'imported_records' => $new,
                    'updated_records' => $changed,
                    'skipped_records' => $same,
                    'options' => $log->options + ['checksum' => $checksum, 'flags' => count($flags), 'unchanged' => ! $dryRun],
                    'error_details' => $flags ? ['flags' => array_slice($flags, 0, 200)] : null,
                    'completed_at' => now(),
                ]);

                return $log;
            }

            $revision = DB::transaction(function () use ($edition, $existing, $content, $verseIds, $checksum, $flags, $log) {
                $model = $existing ?? new QuranTranslationEdition(['is_active' => false, 'review_status' => QuranTranslationEdition::STATUS_IMPORTED]);
                $model->fill(collect($edition)->only([
                    'identifier', 'language', 'name', 'translator', 'publisher', 'copyright_holder', 'attribution_text', 'source_url',
                    'license', 'license_url', 'version', 'source_updated_at', 'import_source', 'source_key', 'quran_data_source_id',
                ])->all())->save();

                if ($existing) {
                    $this->history->ensureBaseline($model);
                }
                $revision = $this->history->record($model, $content, QuranTranslationRevision::KIND_IMPORT, $verseIds, $log->id, $edition['version'] ?? null);
                $this->writeContent($model->id, $content, $verseIds);
                $this->history->storeFlags($model, $revision->revision, $flags, $verseIds);

                $before = $model->review_status;
                $model->forceFill(['content_checksum' => $checksum, 'current_revision' => $revision->revision]);
                if ($existing && $before !== QuranTranslationEdition::STATUS_IMPORTED && $before !== QuranTranslationEdition::STATUS_PENDING) {
                    $model->forceFill(['review_status' => QuranTranslationEdition::STATUS_IMPORTED, 'is_active' => false]);
                    $this->history->logReview($model, $before, QuranTranslationEdition::STATUS_IMPORTED, null, $revision->revision, 'New content imported; it needs a fresh review.');
                } elseif ($before === QuranTranslationEdition::STATUS_PENDING) {
                    $model->forceFill(['review_status' => QuranTranslationEdition::STATUS_IMPORTED]);
                    $this->history->logReview($model, $before, QuranTranslationEdition::STATUS_IMPORTED, null, $revision->revision, 'Content imported.');
                }
                $model->save();

                $this->verifyStored($model->id, $checksum);

                return $revision;
            });

            $log->update([
                'status' => QuranImportLog::STATUS_COMPLETED,
                'imported_records' => $new,
                'updated_records' => $changed,
                'skipped_records' => $same,
                'options' => $log->options + ['checksum' => $checksum, 'revision' => $revision->revision, 'flags' => count($flags)],
                'error_details' => $flags ? ['flags' => array_slice($flags, 0, 200)] : null,
                'completed_at' => now(),
            ]);

            return $log;
        } catch (Throwable $e) {
            $log->update(['status' => QuranImportLog::STATUS_FAILED, 'error_details' => ['exception' => $e->getMessage()], 'completed_at' => now()]);
            throw $e;
        }
    }

    /** Reads the edition back and compares it with what was meant to be written. */
    protected function verifyStored(int $editionId, string $expected): void
    {
        if (TranslationContent::stored($editionId)->checksum() !== $expected) {
            throw new RuntimeException('Read-back check failed: the stored edition does not match the import. Nothing was changed.');
        }
    }

    private function assertAttribution(array $edition): void
    {
        foreach (['identifier', 'language', 'name', 'license', 'attribution_text'] as $field) {
            if (blank($edition[$field] ?? null)) {
                throw new InvalidArgumentException("The edition needs a {$field}: translations are never imported without attribution.");
            }
        }
        if (blank($edition['translator'] ?? null) && blank($edition['publisher'] ?? null)) {
            throw new InvalidArgumentException('The edition needs a translator or a publisher.');
        }
        if (($edition['import_source'] ?? null) === 'quranenc' && blank($edition['version'] ?? null)) {
            throw new InvalidArgumentException('QuranEnc editions need their version number: QuranEnc\'s terms require citing it.');
        }
    }

    /** @return array{0: TranslationContent, 1: list<string>} */
    private function collect(iterable $verses): array
    {
        $byKey = [];
        $problems = [];
        foreach ($verses as $key => $value) {
            $parsed = QuranStructure::parseKey((string) $key);
            if ($parsed === null) {
                $problems[] = "\"{$key}\" is not a verse reference.";

                continue;
            }
            $key = QuranStructure::key(...$parsed);
            if (isset($byKey[$key])) {
                $problems[] = "Verse {$key} appears more than once.";
            }
            $text = is_array($value) ? (string) ($value['text'] ?? '') : (string) $value;
            $notes = is_array($value) ? ($value['footnotes'] ?? null) : null;
            $byKey[$key] = ['text' => $text, 'footnotes' => ($notes === null || $notes === '') ? null : (string) $notes];
        }
        if (count($byKey) !== QuranStructure::VERSES) {
            $missing = [];
            foreach (QuranStructure::VERSE_COUNTS as $i => $count) {
                for ($v = 1; $v <= $count && count($missing) < 20; $v++) {
                    if (! isset($byKey[($i + 1).":{$v}"])) {
                        $missing[] = ($i + 1).":{$v}";
                    }
                }
            }
            $problems[] = 'The edition has '.count($byKey).' verses, expected '.QuranStructure::VERSES.($missing ? '; missing '.implode(', ', $missing) : '').'.';
        }

        return [new TranslationContent($byKey), $problems];
    }

    /** @return array{0: list<string>, 1: list<array{verse: string, severity: string, code: string, detail: string}>} */
    private function inspect(string $language, TranslationContent $content, array $acknowledged): array
    {
        $acknowledged = array_flip($acknowledged);
        $blocking = [];
        $flags = [];
        foreach ($content->verses as $key => $row) {
            $issues = array_merge(
                $this->inspector->inspect($language, $row['text'], $row['footnotes']),
                $this->inspector->footnoteIssues($row['text'], $row['footnotes']),
            );
            foreach ($issues as $issue) {
                if ($issue->isError() && ! isset($acknowledged[$key])) {
                    $blocking[] = "Verse {$key}: {$issue->detail} [{$issue->code}]";
                }
                $flags[] = ['verse' => $key, 'severity' => $issue->severity, 'code' => $issue->code, 'detail' => $issue->detail];
            }
        }

        return [$blocking, $flags];
    }

    /** @return array{0: int, 1: int, 2: int} new, changed and unchanged verses */
    private function diff(TranslationContent $stored, TranslationContent $incoming): array
    {
        $new = $changed = $same = 0;
        foreach ($incoming->verses as $key => $row) {
            $old = $stored->verses[$key] ?? null;
            $old === null ? $new++ : ($old === $row ? $same++ : $changed++);
        }

        return [$new, $changed, $same];
    }

    private function writeContent(int $editionId, TranslationContent $content, array $verseIds): void
    {
        $now = now();
        $rows = [];
        foreach ($content->verses as $key => $row) {
            $rows[] = ['quran_verse_id' => $verseIds[$key], 'quran_translation_edition_id' => $editionId, 'text' => $row['text'], 'footnotes_raw' => $row['footnotes'], 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('quran_translations')->upsert($chunk, ['quran_verse_id', 'quran_translation_edition_id'], ['text', 'footnotes_raw', 'updated_at']);
        }
        $this->history->rebuildFootnoteIndex($editionId, $content, $verseIds);
    }

    private function fail(QuranImportLog $log, array $problems): QuranImportLog
    {
        $log->update([
            'status' => QuranImportLog::STATUS_FAILED,
            'failed_records' => count($problems),
            'error_details' => ['validation' => array_slice($problems, 0, 500)],
            'completed_at' => now(),
        ]);

        return $log;
    }
}
