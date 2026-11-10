<?php

namespace App\Services\AI\Understanding;

use App\Models\QuranSurah;
use App\Services\AI\SourceRetriever;
use App\Services\Quran\QuranStructure;

/**
 * Finds Quran references, surahs, Islamic concepts, prophets and Hadith references in a question.
 * A reference is only accepted when the surah and ayah exist, so "2:300" is never looked up.
 */
class IslamicEntityExtractor
{
    private const SURAH_WORDS = ['surah', 'sura', 'surat', 'suroh', 'shura', 'surah', 'সূরা', 'সুরা', 'سورة'];

    private const AYAH_WORDS = ['ayah', 'ayat', 'aayat', 'ayaat', 'verse', 'verses', 'no', 'number', 'er', 'আয়াত', 'আয়াত', 'নং', 'এর'];

    private const HADITH_COLLECTIONS = [
        'bukhari' => 'Sahih al-Bukhari', 'sahih muslim' => 'Sahih Muslim', 'tirmidhi' => 'Jami at-Tirmidhi',
        'abu dawud' => 'Sunan Abi Dawud', 'abu dawood' => 'Sunan Abi Dawud', 'nasai' => "Sunan an-Nasa'i",
        'ibn majah' => 'Sunan Ibn Majah', 'nawawi' => "An-Nawawi's Forty Hadith", 'বুখারী' => 'Sahih al-Bukhari', 'বুখারি' => 'Sahih al-Bukhari',
    ];

    /** @var array<string, int>|null */
    private ?array $surahKeys = null;

    /**
     * @return array{
     *   references: array<int, array{surah: int, ayah_from: int, ayah_to: int, via: string}>,
     *   surahs: array<int, int>,
     *   concepts: array<int, string>,
     *   prophets: array<int, string>,
     *   hadith: array<int, array{collection: string, number: string}>
     * }
     */
    public function extract(NormalizedQuestion $question): array
    {
        $text = mb_strtolower(SourceRetriever::asciiDigits($question->original));
        $references = [];
        $surahs = [];

        // Named verses, such as Ayatul Kursi.
        foreach (config('islamic_lexicon.named_verses') as $name => $key) {
            if (str_contains($text, mb_strtolower($name))) {
                [$s, $a] = array_map('intval', explode(':', $key));
                $references[] = ['surah' => $s, 'ayah_from' => $a, 'ayah_to' => $a, 'via' => 'named_verse'];
            }
        }

        // 2:255, 2:1-5, ২:১৫৩
        if (preg_match_all('/\b(\d{1,3})\s*:\s*(\d{1,3})(?:\s*[-–]\s*(\d{1,3}))?\b/u', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $this->addReference($references, (int) $hit[1], (int) $hit[2], isset($hit[3]) && $hit[3] !== '' ? (int) $hit[3] : null, 'reference');
            }
        }

        // "surah 2 ayah 255", "surah baqarah 255", "baqarah er 255 ayat", "সূরা বাকারা ২৫৫"
        $words = preg_split('/[^\p{L}\p{M}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $standalone = config('islamic_lexicon.standalone_surahs');
        for ($i = 0, $n = count($words); $i < $n; $i++) {
            $afterSurahWord = $i > 0 && in_array($words[$i - 1], self::SURAH_WORDS, true);
            $surah = null;
            $width = 1;
            if ($afterSurahWord && preg_match('/^\d{1,3}$/', $words[$i]) && (int) $words[$i] >= 1 && (int) $words[$i] <= QuranStructure::SURAHS) {
                $surah = (int) $words[$i];
            } else {
                foreach ([2, 1] as $w) {
                    // A name is words only; a following number is the ayah, not part of the name.
                    if ($i + $w > $n || preg_grep('/^\d+$/', array_slice($words, $i, $w)) !== []) {
                        continue;
                    }
                    $found = $this->surahByName(implode(' ', array_slice($words, $i, $w)));
                    if ($found !== null && ($afterSurahWord || in_array($found, $standalone, true))) {
                        $surah = $found;
                        $width = $w;
                        break;
                    }
                }
            }
            if ($surah === null) {
                continue;
            }
            $surahs[] = $surah;
            // An ayah number shortly after the surah: skip words like "ayat", "er", "no".
            $j = $i + $width;
            while ($j < $n && $j <= $i + $width + 2 && in_array($words[$j], self::AYAH_WORDS, true)) {
                $j++;
            }
            if ($j < $n && preg_match('/^\d{1,3}$/', $words[$j])) {
                $to = $j + 1 < $n && preg_match('/^\d{1,3}$/', $words[$j + 1]) && str_contains($text, $words[$j].'-'.$words[$j + 1]) ? (int) $words[$j + 1] : null;
                $this->addReference($references, $surah, (int) $words[$j], $to, 'surah_name');
            }
            $i += $width - 1;
        }

        $hadith = [];
        foreach (self::HADITH_COLLECTIONS as $alias => $collection) {
            if (preg_match('/'.preg_quote($alias, '/').'\D{0,16}?(\d{1,5})\b/u', $text, $h)) {
                $hadith[] = ['collection' => $collection, 'number' => $h[1]];
            }
        }

        return [
            // "surah 2:255" is found by two patterns; keep the first of each location.
            'references' => array_values(array_reduce($references, function (array $kept, array $r) {
                $kept["{$r['surah']}:{$r['ayah_from']}-{$r['ayah_to']}"] ??= $r;

                return $kept;
            }, [])),
            'surahs' => array_values(array_unique($surahs)),
            'concepts' => $question->concepts(),
            'prophets' => $question->prophets(),
            'hadith' => $hadith,
        ];
    }

    /** Folds transliteration differences: Al-Baqara, baqarah and bakara give the same key. */
    public static function surahKey(string $name): string
    {
        $name = mb_strtolower(trim($name));
        if (preg_match('/\p{Bengali}/u', $name)) {
            return BanglishQuestionNormalizer::banglaKey(preg_replace('/\s+/u', '', preg_replace('/^(আল|আর|আন|আস|আত)[\s\-]+/u', '', $name)));
        }
        $name = preg_replace('/^(aal-i-|al-|an-|ar-|as-|ash-|at-|az-|ad-|adh-|al\s+|an\s+|ar\s+|as\s+)/', '', $name);
        $name = preg_replace('/[^a-z]/', '', $name);
        $name = strtr($name, ['q' => 'k']);
        $name = str_replace(['aa', 'ee', 'oo', 'ou', 'iy'], ['a', 'i', 'u', 'u', 'i'], $name);
        $name = str_replace('e', 'i', $name);

        return rtrim($name, 'h') ?: $name;
    }

    public function surahByName(string $name): ?int
    {
        if ($this->surahKeys === null) {
            $this->surahKeys = [];
            foreach (config('islamic_lexicon.surah_aliases') as $surah => $aliases) {
                foreach ($aliases as $alias) {
                    $this->surahKeys[self::surahKey($alias)] = (int) $surah;
                }
            }
            foreach (QuranSurah::query()->pluck('name_transliterated', 'id') as $id => $transliterated) {
                $key = self::surahKey((string) $transliterated);
                // Very short keys ("nas", "nur") would match ordinary words; aliases cover those.
                if (strlen($key) >= 4) {
                    $this->surahKeys[$key] ??= (int) $id;
                }
            }
        }
        $key = self::surahKey($name);

        return $key !== '' ? ($this->surahKeys[$key] ?? null) : null;
    }

    private function addReference(array &$references, int $surah, int $from, ?int $to, string $via): void
    {
        if (! QuranStructure::exists($surah, $from)) {
            return;
        }
        $to = $to !== null && $to >= $from && QuranStructure::exists($surah, $to) ? min($to, $from + 9) : $from;
        $references[] = ['surah' => $surah, 'ayah_from' => $from, 'ayah_to' => $to, 'via' => $via];
    }
}
