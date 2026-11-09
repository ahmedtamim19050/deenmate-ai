<?php

namespace App\Services\Quran\Translations;

/**
 * Splits a published footnote field into notes keyed by their markers, for retrieval. It never
 * changes text: the raw field is stored as well and stays the authoritative copy.
 *
 * Only the markers that appear in the verse text are used as split points, in their order, so a
 * numbered citation inside a note (such as "[বুখারী ৩]") is never mistaken for a marker. Text
 * before the first marker, such as a surah introduction, is kept as a note with no marker.
 */
final class FootnoteParser
{
    /** Bracketed numbers in Western, Bengali or Arabic-Indic digits: [2], [১], [٢]. */
    private const MARKER = '/\[[0-9\x{09E6}-\x{09EF}\x{0660}-\x{0669}]{1,3}\]/u';

    /** @return list<string> markers in order of appearance */
    public static function markers(string $text): array
    {
        preg_match_all(self::MARKER, $text, $m);

        return array_values(array_unique($m[0]));
    }

    /** @return list<array{marker: ?string, position: int, text: string}> */
    public static function parse(string $verseText, ?string $footnotes): array
    {
        if ($footnotes === null || trim($footnotes) === '') {
            return [];
        }
        $cuts = [];
        $from = 0;
        foreach (self::markers($verseText) as $marker) {
            $at = mb_strpos($footnotes, $marker, $from);
            if ($at === false) {
                continue;
            }
            $cuts[] = [$marker, $at];
            $from = $at + mb_strlen($marker);
        }

        $notes = [];
        $position = 0;
        $preambleEnd = $cuts[0][1] ?? mb_strlen($footnotes);
        $preamble = trim(mb_substr($footnotes, 0, $preambleEnd));
        if ($preamble !== '') {
            $notes[] = ['marker' => null, 'position' => $position++, 'text' => $preamble];
        }
        foreach ($cuts as $i => [$marker, $at]) {
            $start = $at + mb_strlen($marker);
            $end = $cuts[$i + 1][1] ?? mb_strlen($footnotes);
            $notes[] = ['marker' => $marker, 'position' => $position++, 'text' => trim(mb_substr($footnotes, $start, $end - $start))];
        }

        return $notes;
    }
}
