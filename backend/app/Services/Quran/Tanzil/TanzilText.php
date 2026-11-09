<?php

namespace App\Services\Quran\Tanzil;

use RuntimeException;

/**
 * A parsed Tanzil XML text, kept exactly as published. Nothing in a verse is altered: Tanzil's
 * licence permits verbatim copies only. Verse 1 never contains the Basmala heading, which
 * Tanzil publishes separately as the "bismillah" attribute.
 */
final class TanzilText
{
    /**
     * @param  array<int, array<int, string>>  $verses  surah => verse number => text, in file order
     * @param  list<string>  $duplicates  verse keys that appeared more than once
     */
    public function __construct(
        public readonly string $edition,
        public readonly string $copyright,
        public readonly array $verses,
        public readonly array $duplicates,
    ) {}

    public static function parse(string $xml): self
    {
        $previous = libxml_use_internal_errors(true);
        try {
            // No network access and no entity expansion while parsing a downloaded file.
            $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
            if ($doc === false || $doc->getName() !== 'quran') {
                throw new RuntimeException('Not a Tanzil Quran XML file.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        preg_match('/^#\s*(Tanzil Quran Text \([^)]*\))/m', $xml, $edition);
        preg_match('/^#\s*(Copyright \(C\)[^\r\n]*)/m', $xml, $copyright);

        $verses = [];
        $duplicates = [];
        foreach ($doc->sura as $sura) {
            $s = (int) $sura['index'];
            foreach ($sura->aya as $aya) {
                $v = (int) $aya['index'];
                if (isset($verses[$s][$v])) {
                    $duplicates[] = "{$s}:{$v}";
                }
                $verses[$s][$v] = (string) $aya['text'];
            }
        }

        return new self($edition[1] ?? 'Tanzil Quran Text', $copyright[1] ?? '', $verses, $duplicates);
    }
}
