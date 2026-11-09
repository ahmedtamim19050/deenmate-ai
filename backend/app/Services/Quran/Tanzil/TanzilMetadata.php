<?php

namespace App\Services\Quran\Tanzil;

use RuntimeException;

/** Surah names, verse counts, juz and page boundaries from Tanzil's quran-data.xml. */
final class TanzilMetadata
{
    /**
     * @param  array<int, array{ayas:int, name:string, tname:string, ename:string, type:string, order:int}>  $surahs
     * @param  array<int, array{0:int,1:int}>  $juzStarts  juz number => [surah, verse]
     * @param  array<int, array{0:int,1:int}>  $pageStarts  page number => [surah, verse]
     */
    public function __construct(
        public readonly array $surahs,
        public readonly array $juzStarts,
        public readonly array $pageStarts,
    ) {}

    public static function parse(string $xml): self
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
            if ($doc === false) {
                throw new RuntimeException('Tanzil metadata is not valid XML.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $surahs = [];
        foreach ($doc->xpath('//sura') ?: [] as $s) {
            $surahs[(int) $s['index']] = [
                'ayas' => (int) $s['ayas'],
                'name' => (string) $s['name'],
                'tname' => (string) $s['tname'],
                'ename' => (string) $s['ename'],
                'type' => strtolower((string) $s['type']),
                'order' => (int) $s['order'],
            ];
        }
        $starts = function (string $tag) use ($doc): array {
            $out = [];
            foreach ($doc->xpath("//{$tag}") ?: [] as $n) {
                $out[(int) $n['index']] = [(int) $n['sura'], (int) $n['aya']];
            }
            ksort($out);

            return $out;
        };

        return new self($surahs, $starts('juz'), $starts('page'));
    }

    /** The division (juz or page) that [surah]:[verse] falls in, from a list of start points. */
    public static function divisionOf(array $starts, int $surah, int $verse): ?int
    {
        $found = null;
        foreach ($starts as $index => [$s, $v]) {
            if ($s < $surah || ($s === $surah && $v <= $verse)) {
                $found = $index;
            } else {
                break;
            }
        }

        return $found;
    }
}
