<?php

namespace App\Services\Quran;

use App\Services\Quran\Tanzil\TanzilMetadata;
use App\Services\Quran\Tanzil\TanzilText;

/**
 * Checks a dataset against the fixed structure of the Quran before anything is written.
 * Every problem names the exact surah or verse. Nothing here alters text to make a check pass.
 */
class QuranIntegrityValidator
{
    /** @return list<string> problems; empty means the dataset is complete and well formed */
    public function validate(TanzilText $text, ?TanzilMetadata $metadata = null, ?TanzilText $searchText = null): array
    {
        $problems = [];
        foreach ($text->duplicates as $key) {
            $problems[] = "Duplicate verse {$key}.";
        }

        $surahs = array_keys($text->verses);
        sort($surahs);
        if ($surahs !== range(1, QuranStructure::SURAHS)) {
            $missing = array_diff(range(1, QuranStructure::SURAHS), $surahs);
            $extra = array_diff($surahs, range(1, QuranStructure::SURAHS));
            if ($missing) {
                $problems[] = 'Missing surahs: '.implode(', ', $missing).'.';
            }
            if ($extra) {
                $problems[] = 'Unexpected surah numbers: '.implode(', ', $extra).'.';
            }
        }

        $total = 0;
        foreach ($text->verses as $surah => $verses) {
            if ($surah < 1 || $surah > QuranStructure::SURAHS) {
                continue;
            }
            $expected = QuranStructure::verseCount($surah);
            $numbers = array_keys($verses);
            if ($numbers !== range(1, count($numbers))) {
                $problems[] = "Surah {$surah}: verses are not numbered 1 to ".count($numbers).' in order.';
            }
            if (count($verses) !== $expected) {
                $missing = array_diff(range(1, $expected), $numbers);
                $problems[] = "Surah {$surah} has ".count($verses)." verses, expected {$expected}"
                    .($missing ? '; missing '.implode(', ', array_map(fn ($v) => "{$surah}:{$v}", array_slice($missing, 0, 20))) : '').'.';
            }
            if ($metadata !== null && ($metadata->surahs[$surah]['ayas'] ?? null) !== $expected) {
                $problems[] = "Surah {$surah}: the metadata lists ".($metadata->surahs[$surah]['ayas'] ?? 'no')." verses, expected {$expected}.";
            }
            foreach ($verses as $verse => $t) {
                $total++;
                foreach ($this->textProblems($t) as $p) {
                    $problems[] = "Verse {$surah}:{$verse}: {$p}";
                }
                if ($searchText !== null && ! isset($searchText->verses[$surah][$verse])) {
                    $problems[] = "Verse {$surah}:{$verse} is missing from the search text.";
                }
            }
        }
        if ($total !== QuranStructure::VERSES) {
            $problems[] = "The dataset has {$total} verses, expected ".QuranStructure::VERSES.'.';
        }

        if ($metadata !== null) {
            if (count($metadata->juzStarts) !== QuranStructure::JUZ || array_keys($metadata->juzStarts) !== range(1, QuranStructure::JUZ)) {
                $problems[] = 'The metadata lists '.count($metadata->juzStarts).' juz, expected '.QuranStructure::JUZ.'.';
            }
            foreach ($metadata->juzStarts as $juz => [$s, $v]) {
                if (! QuranStructure::exists($s, $v)) {
                    $problems[] = "Juz {$juz} starts at {$s}:{$v}, which is not a verse.";
                }
            }
        }

        if ($searchText !== null) {
            $searchTotal = array_sum(array_map('count', $searchText->verses));
            if ($searchTotal !== QuranStructure::VERSES) {
                $problems[] = "The search text has {$searchTotal} verses, expected ".QuranStructure::VERSES.'.';
            }
        }

        return $problems;
    }

    /** @return list<string> */
    public function textProblems(string $text): array
    {
        $problems = [];
        if (trim($text) === '') {
            return ['the text is empty.'];
        }
        if (! mb_check_encoding($text, 'UTF-8')) {
            $problems[] = 'the text is not valid UTF-8.';
        }
        if (str_contains($text, "\u{FFFD}")) {
            $problems[] = 'the text contains a replacement character, so it was corrupted before import.';
        }
        if (! preg_match('/\p{Arabic}/u', $text)) {
            $problems[] = 'the text contains no Arabic letters.';
        }
        if (preg_match('/[\p{Latin}\p{Cyrillic}\p{Han}]/u', $text)) {
            $problems[] = 'the text contains non-Arabic letters.';
        }

        return $problems;
    }
}
