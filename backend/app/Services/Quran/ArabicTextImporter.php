<?php

namespace App\Services\Quran;

use App\Models\QuranDataSource;
use App\Models\QuranImportLog;
use App\Models\QuranSurah;
use App\Services\Quran\Tanzil\TanzilMetadata;
use App\Services\Quran\Tanzil\TanzilText;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Writes the Arabic Quran into quran_surahs and quran_verses.
 *
 * - Validates the whole dataset first and writes nothing if any check fails.
 * - Idempotent: a repeat run finds every verse already present and changes nothing.
 * - Never deletes. Stored text that differs from the source is reported, and only replaced
 *   when [$replaceText] is set, so a re-import cannot silently rewrite the Quran.
 * - Every run, including dry runs and failures, leaves a quran_import_logs row.
 */
class ArabicTextImporter
{
    public const IMPORT_TYPE = 'arabic_text';

    public function __construct(private QuranIntegrityValidator $validator) {}

    public function import(
        TanzilText $uthmani,
        TanzilText $simpleClean,
        TanzilMetadata $metadata,
        array $sourceAttributes,
        bool $dryRun = false,
        bool $replaceText = false,
    ): ArabicImportResult {
        $source = $dryRun
            ? QuranDataSource::query()->where('slug', $sourceAttributes['slug'])->first()
            : QuranDataSource::query()->updateOrCreate(['slug' => $sourceAttributes['slug']], $sourceAttributes);
        $log = QuranImportLog::create([
            'import_type' => self::IMPORT_TYPE,
            'quran_data_source_id' => $source?->id,
            'status' => QuranImportLog::STATUS_RUNNING,
            'options' => ['dry_run' => $dryRun, 'replace_text' => $replaceText, 'edition' => $uthmani->edition],
            'total_records' => QuranStructure::VERSES,
            'started_at' => now(),
        ]);

        try {
            $problems = $this->validator->validate($uthmani, $metadata, $simpleClean);
            $problems = array_merge($problems, $this->surahProblems());
            if ($problems !== []) {
                $log->update([
                    'status' => QuranImportLog::STATUS_FAILED,
                    'failed_records' => count($problems),
                    'error_details' => ['validation' => $problems],
                    'completed_at' => now(),
                ]);

                return ArabicImportResult::failed($log, $problems);
            }

            $plan = $this->plan($uthmani, $simpleClean, $metadata, $source?->id, $replaceText);
            if (! $dryRun) {
                DB::transaction(function () use ($metadata, $plan) {
                    $this->writeSurahs($metadata);
                    foreach (array_chunk($plan['rows'], 500) as $chunk) {
                        DB::table('quran_verses')->upsert(
                            $chunk,
                            ['surah_id', 'verse_number'],
                            ['verse_key', 'text_arabic', 'text_simple_clean', 'text_source', 'quran_data_source_id', 'juz', 'page', 'updated_at'],
                        );
                    }
                });
                $this->assertStored($uthmani, $replaceText ? [] : $plan['differences']);
            }

            $details = array_filter([
                'text_differences_kept' => $replaceText ? null : $plan['differences'],
                'text_replaced' => $replaceText ? $plan['differences'] : null,
                'division_mismatches' => $plan['divisionMismatches'],
            ]);
            $log->update([
                'status' => $dryRun ? QuranImportLog::STATUS_DRY_RUN : QuranImportLog::STATUS_COMPLETED,
                'imported_records' => $plan['inserted'],
                'updated_records' => $plan['updated'],
                'skipped_records' => $plan['unchanged'] + ($replaceText ? 0 : count($plan['differences'])),
                'error_details' => $details ?: null,
                'completed_at' => now(),
            ]);

            return new ArabicImportResult(true, $log, [], $plan['inserted'], $plan['updated'], $plan['unchanged'], $plan['differences'], $replaceText, $plan['divisionMismatches']);
        } catch (Throwable $e) {
            $log->update([
                'status' => QuranImportLog::STATUS_FAILED,
                'error_details' => ['exception' => $e->getMessage()],
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }

    /** Existing surah rows must agree with the fixed structure before any verse is written. */
    private function surahProblems(): array
    {
        $problems = [];
        foreach (QuranSurah::query()->get(['id', 'verse_count']) as $s) {
            if ($s->id < 1 || $s->id > QuranStructure::SURAHS) {
                $problems[] = "Stored surah {$s->id} is not a surah number.";
            } elseif ($s->verse_count !== QuranStructure::verseCount($s->id)) {
                $problems[] = "Stored surah {$s->id} lists {$s->verse_count} verses, expected ".QuranStructure::verseCount($s->id).'.';
            }
        }

        return $problems;
    }

    private function plan(TanzilText $uthmani, TanzilText $clean, TanzilMetadata $meta, ?int $sourceId, bool $replaceText): array
    {
        $existing = [];
        foreach (DB::table('quran_verses')->get(['surah_id', 'verse_number', 'verse_key', 'text_arabic', 'text_simple_clean', 'text_source', 'quran_data_source_id', 'juz', 'page']) as $row) {
            $existing[$row->surah_id][$row->verse_number] = $row;
        }

        $now = now();
        $sourceLabel = "{$uthmani->edition}, Tanzil Project (tanzil.net), CC BY 3.0";
        $rows = [];
        $differences = [];
        $divisionMismatches = [];
        $inserted = $updated = $unchanged = 0;

        foreach ($uthmani->verses as $surah => $verses) {
            foreach ($verses as $verse => $text) {
                $key = QuranStructure::key($surah, $verse);
                $juz = TanzilMetadata::divisionOf($meta->juzStarts, $surah, $verse);
                $page = TanzilMetadata::divisionOf($meta->pageStarts, $surah, $verse);
                $old = $existing[$surah][$verse] ?? null;

                $row = [
                    'surah_id' => $surah,
                    'verse_number' => $verse,
                    'verse_key' => $key,
                    'text_arabic' => $text,
                    'text_simple_clean' => $clean->verses[$surah][$verse],
                    'text_source' => $sourceLabel,
                    'quran_data_source_id' => $sourceId,
                    'juz' => $juz,
                    'page' => $page,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($old === null) {
                    $rows[] = $row;
                    $inserted++;

                    continue;
                }

                foreach (['juz' => $juz, 'page' => $page] as $field => $value) {
                    if ($old->{$field} !== null && (int) $old->{$field} !== $value) {
                        $divisionMismatches[] = "{$key} {$field}: stored {$old->{$field}}, Tanzil {$value}";
                    }
                }

                if ($old->text_arabic !== $text) {
                    $differences[] = $key;
                    if (! $replaceText) {
                        // Keep the stored text and its attribution; only fill fields that are empty.
                        $row['text_arabic'] = $old->text_arabic;
                        $row['text_source'] = $old->text_source;
                        $row['quran_data_source_id'] = $old->quran_data_source_id;
                    }
                }
                $row['juz'] = $old->juz ?? $juz;
                $row['page'] = $old->page ?? $page;
                unset($row['created_at']);

                $changed = $old->verse_key !== $row['verse_key']
                    || $old->text_arabic !== $row['text_arabic']
                    || $old->text_simple_clean !== $row['text_simple_clean']
                    || $old->text_source !== $row['text_source']
                    || $old->quran_data_source_id !== $row['quran_data_source_id']
                    || $old->juz !== $row['juz']
                    || $old->page !== $row['page'];
                if ($changed) {
                    $row['created_at'] = $now;
                    $rows[] = $row;
                    $updated++;
                } else {
                    $unchanged++;
                }
            }
        }

        return compact('rows', 'differences', 'divisionMismatches', 'inserted', 'updated', 'unchanged');
    }

    private function writeSurahs(TanzilMetadata $meta): void
    {
        $present = QuranSurah::query()->pluck('id')->all();
        foreach ($meta->surahs as $number => $s) {
            if (in_array($number, $present, true)) {
                continue;
            }
            QuranSurah::query()->create([
                'id' => $number,
                'name_arabic' => $s['name'],
                'name_transliterated' => $s['tname'],
                'name_english' => $s['ename'],
                'revelation_place' => $s['type'],
                'verse_count' => $s['ayas'],
            ]);
        }
    }

    /** Reads every verse back and compares it byte for byte with the source. */
    private function assertStored(TanzilText $uthmani, array $keptDifferences): void
    {
        $kept = array_flip($keptDifferences);
        $stored = [];
        foreach (DB::table('quran_verses')->get(['surah_id', 'verse_number', 'text_arabic']) as $row) {
            $stored[$row->surah_id][$row->verse_number] = $row->text_arabic;
        }
        $bad = [];
        foreach ($uthmani->verses as $surah => $verses) {
            foreach ($verses as $verse => $text) {
                $key = QuranStructure::key($surah, $verse);
                if (! isset($kept[$key]) && ($stored[$surah][$verse] ?? null) !== $text) {
                    $bad[] = $key;
                }
            }
        }
        if ($bad !== []) {
            throw new \RuntimeException('Read-back check failed for '.count($bad).' verses: '.implode(', ', array_slice($bad, 0, 20)));
        }
    }
}
