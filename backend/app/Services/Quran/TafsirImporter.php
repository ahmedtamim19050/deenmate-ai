<?php

namespace App\Services\Quran;

use App\Models\QuranImportLog;
use App\Models\QuranTafsir;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Imports one tafsir work. An entry may explain a single verse or a run of verses in one surah,
 * as many tafsir books do. Text is stored exactly as published, attributed to its author, and a
 * new tafsir stays inactive until its licence is confirmed and a reviewer approves it.
 *
 * Only real tafsir from a named source belongs here, never generated explanations.
 */
class TafsirImporter
{
    public const IMPORT_TYPE = 'tafsir';

    /**
     * @param  array{slug:string, name:string, author:?string, language:string, license_name:string, license_url:?string, quran_data_source_id:?int}  $tafsir
     * @param  iterable<array{surah:int, from:int, to?:int, text:string}>  $entries
     */
    public function import(array $tafsir, iterable $entries, bool $dryRun = false): QuranImportLog
    {
        foreach (['slug', 'name', 'language', 'license_name'] as $field) {
            if (blank($tafsir[$field] ?? null)) {
                throw new InvalidArgumentException("The tafsir needs a {$field}: tafsir is never imported without attribution.");
            }
        }
        $log = QuranImportLog::create([
            'import_type' => self::IMPORT_TYPE,
            'quran_data_source_id' => $tafsir['quran_data_source_id'] ?? null,
            'status' => QuranImportLog::STATUS_RUNNING,
            'options' => ['tafsir' => $tafsir['slug'], 'dry_run' => $dryRun],
            'started_at' => now(),
        ]);

        try {
            [$rows, $problems] = $this->collect($entries);
            $log->total_records = count($rows) + count($problems);
            if ($problems !== []) {
                $log->fill(['status' => QuranImportLog::STATUS_FAILED, 'failed_records' => count($problems), 'error_details' => ['validation' => $problems], 'completed_at' => now()])->save();

                return $log;
            }

            $model = QuranTafsir::query()->where('slug', $tafsir['slug'])->first();
            $existing = $model
                ? DB::table('quran_tafsir_entries')->where('quran_tafsir_id', $model->id)->get(['surah_id', 'verse_from', 'verse_to', 'text'])
                    ->keyBy(fn ($r) => "{$r->surah_id}:{$r->verse_from}")->all()
                : [];
            $new = $changed = $same = 0;
            foreach ($rows as $r) {
                $old = $existing["{$r['surah_id']}:{$r['verse_from']}"] ?? null;
                if ($old === null) {
                    $new++;
                } elseif ($old->text === $r['text'] && (int) $old->verse_to === $r['verse_to']) {
                    $same++;
                } else {
                    $changed++;
                }
            }

            if (! $dryRun) {
                DB::transaction(function () use ($tafsir, $model, $rows) {
                    $model ??= new QuranTafsir(['is_active' => false]);
                    $model->fill(collect($tafsir)->only(['slug', 'name', 'author', 'language', 'license_name', 'license_url', 'quran_data_source_id'])->all())->save();
                    $now = now();
                    $payload = array_map(fn ($r) => $r + ['quran_tafsir_id' => $model->id, 'created_at' => $now, 'updated_at' => $now], $rows);
                    foreach (array_chunk($payload, 200) as $chunk) {
                        DB::table('quran_tafsir_entries')->upsert($chunk, ['quran_tafsir_id', 'surah_id', 'verse_from'], ['verse_to', 'text', 'updated_at']);
                    }
                });
            }
            $log->fill([
                'status' => $dryRun ? QuranImportLog::STATUS_DRY_RUN : QuranImportLog::STATUS_COMPLETED,
                'imported_records' => $new,
                'updated_records' => $changed,
                'skipped_records' => $same,
                'completed_at' => now(),
            ])->save();

            return $log;
        } catch (Throwable $e) {
            $log->fill(['status' => QuranImportLog::STATUS_FAILED, 'error_details' => ['exception' => $e->getMessage()], 'completed_at' => now()])->save();
            throw $e;
        }
    }

    /** @return array{0: list<array{surah_id:int, verse_from:int, verse_to:int, text:string}>, 1: list<string>} */
    private function collect(iterable $entries): array
    {
        $rows = [];
        $problems = [];
        $covered = [];
        foreach ($entries as $i => $e) {
            $surah = (int) ($e['surah'] ?? 0);
            $from = (int) ($e['from'] ?? 0);
            $to = (int) ($e['to'] ?? $from);
            $label = "{$surah}:{$from}".($to !== $from ? "-{$to}" : '');
            if (! QuranStructure::exists($surah, $from) || ! QuranStructure::exists($surah, $to) || $to < $from) {
                $problems[] = "Entry {$i} ({$label}) is not a valid verse range.";

                continue;
            }
            if (trim((string) ($e['text'] ?? '')) === '') {
                $problems[] = "Entry {$label} has no text.";

                continue;
            }
            for ($v = $from; $v <= $to; $v++) {
                if (isset($covered["{$surah}:{$v}"])) {
                    $problems[] = "Verse {$surah}:{$v} is covered by more than one entry ({$covered["{$surah}:{$v}"]} and {$label}).";
                }
                $covered["{$surah}:{$v}"] = $label;
            }
            $rows[] = ['surah_id' => $surah, 'verse_from' => $from, 'verse_to' => $to, 'text' => (string) $e['text']];
        }

        return [$rows, $problems];
    }
}
