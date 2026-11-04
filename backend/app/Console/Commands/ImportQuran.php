<?php

namespace App\Console\Commands;

use App\Models\IslamicSource;
use App\Models\QuranSurah;
use App\Models\QuranTranslation;
use App\Models\QuranTranslationEdition;
use App\Models\QuranVerse;
use App\Services\Quran\BasmalaSeparator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Imports Quran text exactly as published by the upstream API. Nothing is typed by hand
 * or generated. The run fails if the verse count is wrong, so a partial copy cannot land.
 */
class ImportQuran extends Command
{
    private const EXPECTED_SURAHS = 114;

    private const EXPECTED_VERSES = 6236;

    protected $signature = 'quran:import {--skip-translations : Import the Arabic text only}';

    protected $description = 'Import Quran text, transliteration and translations from the configured source';

    public function handle(): int
    {
        $text = $this->fetch(config('deenmate.quran.text_edition'));
        $this->assertComplete($text);
        $sourceName = 'AlQuran Cloud: '.$text['edition']['identifier'].' ('.$text['edition']['name'].')';
        $text = app(BasmalaSeparator::class)->separate($text);

        $translit = $this->fetch(config('deenmate.quran.transliteration_edition'));
        $this->assertComplete($translit);
        $translitByKey = $this->byKey($translit);

        DB::transaction(function () use ($text, $translitByKey, $sourceName) {
            foreach ($text['surahs'] as $s) {
                QuranSurah::query()->updateOrCreate(['id' => $s['number']], [
                    'name_arabic' => $s['name'],
                    'name_transliterated' => $s['englishName'],
                    'name_english' => $s['englishNameTranslation'],
                    'revelation_place' => strtolower($s['revelationType']),
                    'verse_count' => count($s['ayahs']),
                ]);
                foreach ($s['ayahs'] as $a) {
                    QuranVerse::query()->updateOrCreate(
                        ['surah_id' => $s['number'], 'verse_number' => $a['numberInSurah']],
                        [
                            'page' => $a['page'] ?? null,
                            'juz' => $a['juz'] ?? null,
                            'text_arabic' => $a['text'],
                            'transliteration' => $translitByKey[$s['number'].':'.$a['numberInSurah']] ?? null,
                            'text_source' => $sourceName,
                        ],
                    );
                }
            }
        });
        $this->info('Arabic text imported: '.QuranVerse::query()->count().' verses.');

        if (! $this->option('skip-translations')) {
            foreach (config('deenmate.quran.translation_editions') as $identifier) {
                $this->importTranslation(trim($identifier));
            }
        }

        return self::SUCCESS;
    }

    private function importTranslation(string $identifier): void
    {
        $data = $this->fetch($identifier);
        $this->assertComplete($data);
        $meta = $data['edition'];
        $url = rtrim(config('deenmate.quran.api_base'), '/').'/quran/'.$identifier;
        $verseIds = QuranVerse::query()->get(['id', 'surah_id', 'verse_number'])
            ->mapWithKeys(fn ($v) => ["{$v->surah_id}:{$v->verse_number}" => $v->id]);

        DB::transaction(function () use ($data, $meta, $identifier, $url, $verseIds) {
            $edition = QuranTranslationEdition::query()->updateOrCreate(['identifier' => $identifier], [
                'language' => $meta['language'],
                'name' => $meta['englishName'] ?? $meta['name'],
                'translator' => $meta['englishName'] ?? null,
                'source_url' => $url,
                'license' => 'Verify rights before production use. See docs/ISLAMIC_SOURCE_VERIFICATION.md',
            ]);

            // A new import stays pending until a Scholar Reviewer approves the edition.
            $source = IslamicSource::query()->firstOrCreate(
                ['source_type' => 'quran', 'reference_identifier' => 'edition:'.$identifier, 'language' => $meta['language']],
                [
                    'title' => 'The Quran, translation: '.($meta['englishName'] ?? $identifier),
                    'author' => $meta['englishName'] ?? null,
                    'publisher' => 'AlQuran Cloud',
                    'url' => $url,
                    'license' => 'Verify rights before production use',
                ],
            );

            $now = now();
            $translations = [];
            $chunks = [];
            $index = 0;
            foreach ($data['surahs'] as $s) {
                foreach ($s['ayahs'] as $a) {
                    $key = $s['number'].':'.$a['numberInSurah'];
                    $translations[] = [
                        'quran_verse_id' => $verseIds[$key],
                        'quran_translation_edition_id' => $edition->id,
                        'text' => $a['text'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $chunks[] = [
                        'islamic_source_id' => $source->id,
                        'chunk_index' => $index++,
                        'reference' => 'Quran '.$key,
                        'content' => $a['text'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            foreach (array_chunk($translations, 500) as $batch) {
                QuranTranslation::query()->upsert($batch, ['quran_verse_id', 'quran_translation_edition_id'], ['text', 'updated_at']);
            }
            foreach (array_chunk($chunks, 500) as $batch) {
                DB::table('islamic_source_chunks')->upsert($batch, ['islamic_source_id', 'chunk_index'], ['reference', 'content', 'updated_at']);
            }
        });

        $this->info("Translation imported: {$identifier}");
    }

    private function fetch(string $edition): array
    {
        $this->line("Downloading {$edition}...");
        $response = Http::timeout(180)->retry(3, 2000)->get(rtrim(config('deenmate.quran.api_base'), '/').'/quran/'.$edition);
        $data = $response->throw()->json('data');
        if (! is_array($data) || ! isset($data['surahs'], $data['edition'])) {
            throw new \RuntimeException("Unexpected response for edition {$edition}.");
        }

        // Some upstream strings start with an invisible byte-order mark. Nothing else is altered.
        foreach ($data['surahs'] as $i => $s) {
            foreach ($s['ayahs'] as $j => $a) {
                $data['surahs'][$i]['ayahs'][$j]['text'] = preg_replace('/\x{FEFF}/u', '', $a['text']);
            }
        }

        return $data;
    }

    private function assertComplete(array $data): void
    {
        $verses = array_sum(array_map(fn ($s) => count($s['ayahs']), $data['surahs']));
        if (count($data['surahs']) !== self::EXPECTED_SURAHS || $verses !== self::EXPECTED_VERSES) {
            throw new \RuntimeException("Integrity check failed for {$data['edition']['identifier']}: {$verses} verses in ".count($data['surahs']).' surahs.');
        }
    }

    private function byKey(array $data): array
    {
        $out = [];
        foreach ($data['surahs'] as $s) {
            foreach ($s['ayahs'] as $a) {
                $out[$s['number'].':'.$a['numberInSurah']] = $a['text'];
            }
        }

        return $out;
    }
}
