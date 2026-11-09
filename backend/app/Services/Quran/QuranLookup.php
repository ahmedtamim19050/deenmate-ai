<?php

namespace App\Services\Quran;

use App\Models\QuranSurah;
use App\Models\QuranTafsirEntry;
use App\Models\QuranTranslation;
use App\Models\QuranVerse;
use Illuminate\Support\Collection;

/**
 * Read access for future retrieval (and the AI layer): surahs, single verses, Arabic and
 * translation search, and tafsir for a verse. Every result carries its exact "surah:verse"
 * reference and its source, so nothing can be quoted without attribution.
 */
class QuranLookup
{
    public function surah(int $number): ?QuranSurah
    {
        return QuranSurah::query()->with(['verses' => fn ($q) => $q->orderBy('verse_number')])->find($number);
    }

    public function verse(string $key): ?QuranVerse
    {
        $parsed = QuranStructure::parseKey($key);

        return $parsed === null ? null : QuranVerse::query()->with('source')->where('verse_key', QuranStructure::key(...$parsed))->first();
    }

    /**
     * Arabic search over Tanzil's Simple Clean text, so a query typed without vowel marks still
     * matches. Results show the Uthmani text.
     *
     * @return Collection<int, array{reference:string, arabic:string, source:?string}>
     */
    public function searchArabic(string $query, int $limit = 20): Collection
    {
        $needle = self::normaliseArabic($query);
        if (mb_strlen($needle) < 2) {
            return collect();
        }

        return QuranVerse::query()
            ->where('text_simple_clean', 'like', '%'.addcslashes($needle, '%_\\').'%')
            ->orderBy('id')
            ->limit($limit)
            ->get(['verse_key', 'text_arabic', 'text_source'])
            ->map(fn (QuranVerse $v) => ['reference' => $v->verse_key, 'arabic' => $v->text_arabic, 'source' => $v->text_source]);
    }

    /** @return Collection<int, array{reference:string, arabic:string, translation:string, edition:string}> */
    public function searchTranslation(string $query, string $edition, int $limit = 20): Collection
    {
        if (mb_strlen(trim($query)) < 2) {
            return collect();
        }

        return QuranTranslation::query()
            ->with(['verse', 'edition'])
            ->whereHas('edition', fn ($q) => $q->where('identifier', $edition))
            ->where('text', 'like', '%'.addcslashes(trim($query), '%_\\').'%')
            ->orderBy('quran_verse_id')
            ->limit($limit)
            ->get()
            ->map(fn (QuranTranslation $t) => [
                'reference' => $t->verse->verse_key,
                'arabic' => $t->verse->text_arabic,
                'translation' => $t->text,
                'edition' => $t->edition->name,
            ]);
    }

    /**
     * Active tafsir entries that explain the verse, including entries that cover a range.
     *
     * @return Collection<int, array{reference:string, tafsir:string, author:?string, language:string, license:?string, text:string}>
     */
    public function tafsirFor(string $key, ?string $language = null): Collection
    {
        $parsed = QuranStructure::parseKey($key);
        if ($parsed === null) {
            return collect();
        }
        [$surah, $verse] = $parsed;

        return QuranTafsirEntry::query()
            ->with('tafsir')
            ->whereHas('tafsir', fn ($q) => $q->where('is_active', true)->when($language, fn ($q) => $q->where('language', $language)))
            ->where('surah_id', $surah)
            ->where('verse_from', '<=', $verse)
            ->where('verse_to', '>=', $verse)
            ->get()
            ->map(fn (QuranTafsirEntry $e) => [
                'reference' => $e->reference(),
                'tafsir' => $e->tafsir->name,
                'author' => $e->tafsir->author,
                'language' => $e->tafsir->language,
                'license' => $e->tafsir->license_name,
                'text' => $e->text,
            ]);
    }

    /** Removes vowel marks, Quranic annotation signs and tatweel, as in Tanzil's Simple Clean text. */
    public static function normaliseArabic(string $text): string
    {
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $text) ?? '';
        $text = str_replace('ٱ', 'ا', $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
