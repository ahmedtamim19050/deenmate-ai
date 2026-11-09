<?php

namespace App\Services\Quran;

/**
 * The fixed shape of the Quran, kept in code as an independent reference so an imported
 * dataset is never trusted to describe itself.
 */
final class QuranStructure
{
    public const SURAHS = 114;

    public const VERSES = 6236;

    public const JUZ = 30;

    /** Verses in each surah, surah 1 first. */
    public const VERSE_COUNTS = [
        7, 286, 200, 176, 120, 165, 206, 75, 129, 109, 123, 111, 43, 52, 99, 128, 111, 110, 98, 135, 112, 78, 118, 64,
        77, 227, 93, 88, 69, 60, 34, 30, 73, 54, 45, 83, 182, 88, 75, 85, 54, 53, 89, 59, 37, 35, 38, 29, 18, 45, 60, 49,
        62, 55, 78, 96, 29, 22, 24, 13, 14, 11, 11, 18, 12, 12, 30, 52, 52, 44, 28, 28, 20, 56, 40, 31, 50, 40, 46, 42,
        29, 19, 36, 25, 22, 17, 19, 26, 30, 20, 15, 21, 11, 8, 8, 19, 5, 8, 8, 11, 11, 8, 3, 9, 5, 4, 7, 3, 6, 3, 5, 4,
        5, 6,
    ];

    public static function verseCount(int $surah): int
    {
        return self::VERSE_COUNTS[$surah - 1];
    }

    public static function key(int $surah, int $verse): string
    {
        return "{$surah}:{$verse}";
    }

    /** @return array{0:int,1:int}|null */
    public static function parseKey(string $key): ?array
    {
        if (! preg_match('/^\s*(\d{1,3})\s*:\s*(\d{1,3})\s*$/', $key, $m)) {
            return null;
        }
        $surah = (int) $m[1];
        $verse = (int) $m[2];

        return self::exists($surah, $verse) ? [$surah, $verse] : null;
    }

    public static function exists(int $surah, int $verse): bool
    {
        return $surah >= 1 && $surah <= self::SURAHS && $verse >= 1 && $verse <= self::verseCount($surah);
    }
}
