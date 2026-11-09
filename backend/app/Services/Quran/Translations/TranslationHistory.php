<?php

namespace App\Services\Quran\Translations;

use App\Models\QuranImportLog;
use App\Models\QuranTranslationEdition;
use App\Models\QuranTranslationRevision;
use App\Models\User;
use App\Services\Quran\QuranStructure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Revision history, footnote index, flags and review log for translation editions, plus
 * rollback to any earlier revision. Callers wrap these in a transaction.
 */
class TranslationHistory
{
    public const ROLLBACK_TYPE = 'translation_rollback';

    /** Saves the content found before history existed, so the first import can be undone. */
    public function ensureBaseline(QuranTranslationEdition $edition): void
    {
        if (QuranTranslationRevision::query()->where('quran_translation_edition_id', $edition->id)->exists()) {
            return;
        }
        $stored = TranslationContent::stored($edition->id);
        if ($stored->verses === []) {
            return;
        }
        $verseIds = DB::table('quran_verses')->pluck('id', 'verse_key')->all();
        $revision = $this->record($edition, $stored, QuranTranslationRevision::KIND_BASELINE, $verseIds);
        $edition->forceFill(['current_revision' => $revision->revision, 'content_checksum' => $revision->checksum])->save();
    }

    public function record(QuranTranslationEdition $edition, TranslationContent $content, string $kind, array $verseIds, ?int $logId = null, ?string $version = null, ?int $restored = null): QuranTranslationRevision
    {
        $next = (int) QuranTranslationRevision::query()->where('quran_translation_edition_id', $edition->id)->max('revision') + 1;
        $revision = QuranTranslationRevision::create([
            'quran_translation_edition_id' => $edition->id,
            'revision' => $next,
            'kind' => $kind,
            'restored_revision' => $restored,
            'quran_import_log_id' => $logId,
            'source_version' => $version,
            'checksum' => $content->checksum(),
            'verse_count' => count($content->verses),
            'footnote_count' => $content->footnoteCount(),
        ]);
        $rows = [];
        foreach ($content->verses as $key => $row) {
            $rows[] = ['quran_translation_revision_id' => $revision->id, 'quran_verse_id' => $verseIds[$key], 'text' => $row['text'], 'footnotes_raw' => $row['footnotes']];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('quran_translation_revision_texts')->insert($chunk);
        }

        return $revision;
    }

    /** Re-parses the footnote index from the raw fields just written. */
    public function rebuildFootnoteIndex(int $editionId, TranslationContent $content, array $verseIds): void
    {
        $translationIds = DB::table('quran_translations')->where('quran_translation_edition_id', $editionId)->pluck('id', 'quran_verse_id')->all();
        DB::table('quran_translation_footnotes')->whereIn('quran_translation_id', array_values($translationIds))->delete();
        $now = now();
        $rows = [];
        foreach ($content->verses as $key => $row) {
            foreach (FootnoteParser::parse($row['text'], $row['footnotes']) as $note) {
                $rows[] = $note + ['quran_translation_id' => $translationIds[$verseIds[$key]], 'created_at' => $now, 'updated_at' => $now];
            }
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('quran_translation_footnotes')->insert($chunk);
        }
    }

    public function storeFlags(QuranTranslationEdition $edition, ?int $revision, array $flags, array $verseIds): void
    {
        $now = now();
        $rows = array_map(fn ($f) => [
            'quran_translation_edition_id' => $edition->id,
            'quran_verse_id' => $verseIds[$f['verse']],
            'revision' => $revision,
            'severity' => $f['severity'],
            'code' => $f['code'],
            'detail' => $f['detail'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $flags);
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('quran_translation_flags')->insert($chunk);
        }
    }

    public function logReview(QuranTranslationEdition $edition, string $from, string $to, ?User $reviewer, ?int $revision, ?string $notes): void
    {
        $edition->reviews()->create([
            'reviewer_id' => $reviewer?->id,
            'from_status' => $from,
            'to_status' => $to,
            'revision' => $revision,
            'notes' => $notes,
        ]);
    }

    /**
     * Restores an earlier revision exactly. The current content is kept as a revision first,
     * the restore is recorded as a new revision, and the edition goes back for review.
     */
    public function rollback(QuranTranslationEdition $edition, int $revisionNumber, ?User $by = null, bool $dryRun = false): QuranImportLog
    {
        $target = QuranTranslationRevision::query()->where('quran_translation_edition_id', $edition->id)->where('revision', $revisionNumber)->first();
        $log = QuranImportLog::create([
            'import_type' => self::ROLLBACK_TYPE,
            'status' => QuranImportLog::STATUS_RUNNING,
            'options' => ['edition' => $edition->identifier, 'to_revision' => $revisionNumber, 'dry_run' => $dryRun, 'by' => $by?->email],
            'total_records' => QuranStructure::VERSES,
            'started_at' => now(),
        ]);
        if ($target === null) {
            return $this->failLog($log, "Revision {$revisionNumber} does not exist for {$edition->identifier}.");
        }
        $content = TranslationContent::ofRevision($target->id);
        if (count($content->verses) !== QuranStructure::VERSES || $content->checksum() !== $target->checksum) {
            return $this->failLog($log, "Revision {$revisionNumber} is incomplete or does not match its checksum, so it cannot be restored safely.");
        }
        if ($dryRun) {
            $log->update(['status' => QuranImportLog::STATUS_DRY_RUN, 'options' => $log->options + ['checksum' => $target->checksum], 'completed_at' => now()]);

            return $log;
        }

        $verseIds = DB::table('quran_verses')->pluck('id', 'verse_key')->all();
        $restored = DB::transaction(function () use ($edition, $content, $target, $verseIds, $log, $by) {
            $this->ensureBaseline($edition);
            $revision = $this->record($edition, $content, QuranTranslationRevision::KIND_ROLLBACK, $verseIds, $log->id, $target->source_version, $target->revision);
            $now = now();
            $rows = [];
            foreach ($content->verses as $key => $row) {
                $rows[] = ['quran_verse_id' => $verseIds[$key], 'quran_translation_edition_id' => $edition->id, 'text' => $row['text'], 'footnotes_raw' => $row['footnotes'], 'created_at' => $now, 'updated_at' => $now];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('quran_translations')->upsert($chunk, ['quran_verse_id', 'quran_translation_edition_id'], ['text', 'footnotes_raw', 'updated_at']);
            }
            $this->rebuildFootnoteIndex($edition->id, $content, $verseIds);

            $before = $edition->review_status;
            $edition->forceFill(['content_checksum' => $target->checksum, 'current_revision' => $revision->revision, 'review_status' => QuranTranslationEdition::STATUS_UNDER_REVIEW])->save();
            $this->logReview($edition, $before, QuranTranslationEdition::STATUS_UNDER_REVIEW, $by, $revision->revision, "Rolled back to revision {$target->revision}; please review again.");

            if (TranslationContent::stored($edition->id)->checksum() !== $target->checksum) {
                throw new RuntimeException('Read-back check failed after rollback. Nothing was changed.');
            }

            return $revision;
        });

        $log->update(['status' => QuranImportLog::STATUS_COMPLETED, 'updated_records' => QuranStructure::VERSES, 'options' => $log->options + ['new_revision' => $restored->revision, 'checksum' => $target->checksum], 'completed_at' => now()]);

        return $log;
    }

    private function failLog(QuranImportLog $log, string $problem): QuranImportLog
    {
        $log->update(['status' => QuranImportLog::STATUS_FAILED, 'error_details' => ['validation' => [$problem]], 'completed_at' => now()]);

        return $log;
    }
}
