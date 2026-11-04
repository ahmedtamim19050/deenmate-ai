<?php

namespace App\Services\Quran;

use RuntimeException;

/**
 * The upstream dataset prepends the Basmala to verse 1 of every surah except 1 and 9,
 * though it is not part of those verses' text. Only those four opening words are removed,
 * and the import stops if the expected prefix is not found exactly where it should be.
 * Al-Fatihah 1:1 and the Basmala inside An-Naml 27:30 are left alone.
 */
class BasmalaSeparator
{
    private const WORDS = 4;

    public static function hasHeading(int $surah): bool
    {
        return ! in_array($surah, [1, 9], true);
    }

    public function separate(array $data, bool $enforceComplete = true): array
    {
        $opening = null;
        foreach ($data['surahs'] as $s) {
            if ($s['number'] === 1) {
                $opening = $s['ayahs'][0]['text'];
            }
        }
        if ($opening === null) {
            throw new RuntimeException('Integrity check failed: Al-Fatihah is missing.');
        }
        $expected = $this->skeleton($this->words($opening));

        foreach ($data['surahs'] as $i => $s) {
            if (! self::hasHeading($s['number'])) {
                continue;
            }
            $words = $this->words($s['ayahs'][0]['text']);
            if (count($words) <= self::WORDS || $this->skeleton(array_slice($words, 0, self::WORDS)) !== $expected) {
                if ($enforceComplete) {
                    throw new RuntimeException("Integrity check failed: surah {$s['number']} does not begin with the expected Basmala.");
                }

                continue;
            }
            $data['surahs'][$i]['ayahs'][0]['text'] = implode(' ', array_slice($words, self::WORDS));
        }

        return $data;
    }

    private function words(string $text): array
    {
        return preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** Compares letters only, as vowel marks vary slightly between surahs in the dataset. */
    private function skeleton(array $words): array
    {
        return array_map(fn (string $w) => preg_replace('/[\p{Mn}\x{0640}]/u', '', $w), $words);
    }
}
