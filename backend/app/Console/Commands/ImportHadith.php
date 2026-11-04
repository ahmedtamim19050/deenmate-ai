<?php

namespace App\Console\Commands;

use App\Models\HadithCollection;
use App\Models\HadithRecord;
use App\Models\IslamicSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Imports a Hadith collection from the configured dataset. A grade is stored only when the
 * dataset documents one, along with who gave it. Nothing is inferred or filled in.
 */
class ImportHadith extends Command
{
    private const COLLECTIONS = [
        'nawawi' => ['name' => "An-Nawawi's Forty Hadith", 'compiler' => 'Imam an-Nawawi'],
        'bukhari' => ['name' => 'Sahih al-Bukhari', 'compiler' => 'Imam al-Bukhari'],
        'muslim' => ['name' => 'Sahih Muslim', 'compiler' => 'Imam Muslim'],
        'abudawud' => ['name' => 'Sunan Abi Dawud', 'compiler' => 'Imam Abu Dawud'],
        'tirmidhi' => ['name' => "Jami' at-Tirmidhi", 'compiler' => 'Imam at-Tirmidhi'],
        'nasai' => ['name' => "Sunan an-Nasa'i", 'compiler' => "Imam an-Nasa'i"],
        'ibnmajah' => ['name' => 'Sunan Ibn Majah', 'compiler' => 'Imam Ibn Majah'],
        'malik' => ['name' => "Muwatta Malik", 'compiler' => 'Imam Malik'],
    ];

    protected $signature = 'hadith:import {collection=nawawi : One of nawawi, bukhari, muslim, abudawud, tirmidhi, nasai, ibnmajah, malik}';

    protected $description = 'Import a Hadith collection with source metadata';

    public function handle(): int
    {
        $slug = $this->argument('collection');
        $meta = self::COLLECTIONS[$slug] ?? null;
        if ($meta === null) {
            $this->error('Unknown collection.');

            return self::INVALID;
        }

        $base = rtrim(config('deenmate.hadith.api_base'), '/');
        $englishUrl = "{$base}/eng-{$slug}.min.json";
        $english = Http::timeout(180)->retry(3, 2000)->get($englishUrl)->throw()->json();
        $arabic = Http::timeout(180)->retry(3, 2000)->get("{$base}/ara-{$slug}.min.json");
        $arabicByNumber = $arabic->successful()
            ? collect($arabic->json('hadiths', []))->mapWithKeys(fn ($h) => [(string) $h['hadithnumber'] => $h['text']])
            : collect();

        $hadiths = collect($english['hadiths'] ?? [])->filter(fn ($h) => filled($h['text'] ?? null))->values();
        if ($hadiths->isEmpty()) {
            $this->error('The dataset returned no hadith. Nothing was changed.');

            return self::FAILURE;
        }
        $sections = $english['metadata']['sections'] ?? [];

        DB::transaction(function () use ($slug, $meta, $hadiths, $arabicByNumber, $sections, $englishUrl) {
            $collection = HadithCollection::query()->updateOrCreate(['slug' => $slug], $meta + [
                'source_url' => $englishUrl,
                'license' => 'Dataset licence: The Unlicense (fawazahmed0/hadith-api). Confirm translation rights before production.',
            ]);
            $source = IslamicSource::query()->firstOrCreate(
                ['source_type' => 'hadith', 'reference_identifier' => 'collection:'.$slug, 'language' => 'en'],
                ['title' => $meta['name'], 'author' => $meta['compiler'], 'publisher' => 'fawazahmed0/hadith-api', 'url' => $englishUrl],
            );

            foreach ($hadiths->values() as $i => $h) {
                $number = (string) $h['hadithnumber'];
                $grade = collect($h['grades'] ?? [])->first();
                $book = $h['reference']['book'] ?? null;
                HadithRecord::query()->updateOrCreate(
                    ['hadith_collection_id' => $collection->id, 'hadith_number' => $number],
                    [
                        'book' => $book !== null ? ($sections[$book] ?? (string) $book) : null,
                        'text_arabic' => $arabicByNumber[$number] ?? null,
                        'translation' => $h['text'],
                        'grade' => $grade['grade'] ?? null,
                        'grade_source' => $grade['name'] ?? null,
                        'source_reference' => $book !== null ? "Book {$book}, Hadith ".($h['reference']['hadith'] ?? '') : null,
                    ],
                );
                DB::table('islamic_source_chunks')->upsert([[
                    'islamic_source_id' => $source->id,
                    'chunk_index' => $i,
                    'reference' => "{$meta['name']} {$number}",
                    'content' => $h['text'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]], ['islamic_source_id', 'chunk_index'], ['reference', 'content', 'updated_at']);
            }
        });

        $this->info("Imported {$hadiths->count()} hadith from {$meta['name']}. Its source is pending scholar review.");

        return self::SUCCESS;
    }
}
