<?php

namespace App\Services\Quran;

use App\Models\QuranImportLog;
use App\Models\QuranTranslationEdition;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Imports one translation edition, from any source, into quran_translation_editions and
 * quran_translations. One text per verse per edition. The edition must be complete, every
 * reference must be a real verse, and the attribution is stored with it.
 *
 * Only call this for an edition whose licence has been confirmed. A new edition is created
 * inactive, so it is not shown in the app until someone switches it on.
 */
class TranslationImporter
{
    public const IMPORT_TYPE = 'translation';

    /**
     * @param  array{identifier:string, language:string, name:string, translator:?string, source_url:?string, license:string, license_url:?string, quran_data_source_id:?int}  $edition
     * @param  iterable<string, string>  $texts  verse key ("2:255") => translated text, exactly as published
     */
    public function import(array $edition, iterable $texts, bool $dryRun = false): QuranImportLog
    {
        foreach (['identifier', 'language', 'name', 'license'] as $field) {
            if (blank($edition[$field] ?? null)) {
                throw new InvalidArgumentException("The edition needs a {$field}: translations are never imported without attribution.");
            }
        }
        $log = QuranImportLog::create([
            'import_type' => self::IMPORT_TYPE,
            'quran_data_source_id' => $edition['quran_data_source_id'] ?? null,
            'status' => QuranImportLog::STATUS_RUNNING,
            'options' => ['edition' => $edition['identifier'], 'dry_run' => $dryRun],
            'total_records' => QuranStructure::VERSES,
            'started_at' => now(),
        ]);

        try {
            [$byKey, $problems] = $this->collect($texts);
            if ($problems !== []) {
                $log->update(['status' => QuranImportLog::STATUS_FAILED, 'failed_records' => count($problems), 'error_details' => ['validation' => $problems], 'completed_at' => now()]);

                return $log;
            }
            $verseIds = DB::table('quran_verses')->pluck('id', 'verse_key')->all();
            $missing = array_diff(array_keys($byKey), array_keys($verseIds));
            if ($missing !== []) {
                $log->update(['status' => QuranImportLog::STATUS_FAILED, 'error_details' => ['validation' => ['Verses not in the database yet: '.implode(', ', array_slice($missing, 0, 20)).'. Import the Arabic text first.']], 'completed_at' => now()]);

                return $log;
            }

            $existingEdition = QuranTranslationEdition::query()->where('identifier', $edition['identifier'])->first();
            $current = $existingEdition
                ? DB::table('quran_translations')->where('quran_translation_edition_id', $existingEdition->id)->pluck('text', 'quran_verse_id')->all()
                : [];
            $new = $changed = $same = 0;
            foreach ($byKey as $key => $text) {
                $old = $current[$verseIds[$key]] ?? null;
                $old === null ? $new++ : ($old === $text ? $same++ : $changed++);
            }

            if (! $dryRun) {
                DB::transaction(function () use ($edition, $existingEdition, $byKey, $verseIds) {
                    $model = $existingEdition ?? new QuranTranslationEdition(['is_active' => false]);
                    $model->fill(collect($edition)->only(['identifier', 'language', 'name', 'translator', 'source_url', 'license', 'license_url', 'quran_data_source_id'])->all())->save();
                    $now = now();
                    $rows = [];
                    foreach ($byKey as $key => $text) {
                        $rows[] = ['quran_verse_id' => $verseIds[$key], 'quran_translation_edition_id' => $model->id, 'text' => $text, 'created_at' => $now, 'updated_at' => $now];
                    }
                    foreach (array_chunk($rows, 500) as $chunk) {
                        DB::table('quran_translations')->upsert($chunk, ['quran_verse_id', 'quran_translation_edition_id'], ['text', 'updated_at']);
                    }
                });
            }
            $log->update([
                'status' => $dryRun ? QuranImportLog::STATUS_DRY_RUN : QuranImportLog::STATUS_COMPLETED,
                'imported_records' => $new,
                'updated_records' => $changed,
                'skipped_records' => $same,
                'completed_at' => now(),
            ]);

            return $log;
        } catch (Throwable $e) {
            $log->update(['status' => QuranImportLog::STATUS_FAILED, 'error_details' => ['exception' => $e->getMessage()], 'completed_at' => now()]);
            throw $e;
        }
    }

    /** @return array{0: array<string,string>, 1: list<string>} */
    private function collect(iterable $texts): array
    {
        $byKey = [];
        $problems = [];
        foreach ($texts as $key => $text) {
            $parsed = QuranStructure::parseKey((string) $key);
            if ($parsed === null) {
                $problems[] = "\"{$key}\" is not a verse reference.";

                continue;
            }
            $key = QuranStructure::key(...$parsed);
            if (isset($byKey[$key])) {
                $problems[] = "Verse {$key} appears more than once.";
            }
            if (trim((string) $text) === '') {
                $problems[] = "Verse {$key} has no text.";
            } elseif (str_contains((string) $text, "\u{FFFD}") || ! mb_check_encoding((string) $text, 'UTF-8')) {
                $problems[] = "Verse {$key} has corrupted characters.";
            }
            $byKey[$key] = (string) $text;
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

        return [$byKey, $problems];
    }
}
