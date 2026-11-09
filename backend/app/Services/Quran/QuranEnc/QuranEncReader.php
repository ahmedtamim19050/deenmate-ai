<?php

namespace App\Services\Quran\QuranEnc;

use App\Services\Quran\QuranStructure;
use RuntimeException;

/**
 * Reads a translation from QuranEnc.com's public API (https://quranenc.com/api/v1), or from a
 * folder of saved API responses ("sura-001.json" … "sura-114.json", and optionally
 * "translations.json" from the list endpoint). Text and footnotes are returned exactly as
 * published. Edition metadata (title, version, last update) comes only from QuranEnc's own list.
 *
 * QuranEnc's terms: no modification, name the publisher and QuranEnc.com, cite the version,
 * keep the transcript information, keep to the latest version, report notes to QuranEnc, and no
 * inappropriate advertising next to the translation.
 */
class QuranEncReader
{
    public function __construct(private ?string $directory = null) {}

    public static function fromDirectory(string $directory): self
    {
        if (! is_dir($directory)) {
            throw new RuntimeException("Folder not found: {$directory}");
        }

        return new self($directory);
    }

    /**
     * QuranEnc's own metadata for [key], or null when its list does not include it.
     *
     * @return array{key:string, language_iso_code:string, version:string, last_update:int, title:string, description:string}|null
     */
    public function metadata(string $key): ?array
    {
        // A saved folder never goes online: without its saved list, the version is unknown.
        if ($this->directory) {
            $file = $this->directory.'/translations.json';
            $list = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        } else {
            $list = $this->get('/translations/list');
        }
        foreach ($list['translations'] ?? [] as $t) {
            if (($t['key'] ?? null) === $key) {
                return $t;
            }
        }

        return null;
    }

    /**
     * Every verse as "surah:verse" => [text, footnotes]. Throws if a surah is missing, a verse
     * is missing or repeated, or a response names a different surah than requested.
     *
     * @return array<string, array{text: string, footnotes: ?string}>
     */
    public function verses(string $key): array
    {
        $out = [];
        for ($surah = 1; $surah <= QuranStructure::SURAHS; $surah++) {
            $data = $this->directory
                ? $this->readFile(sprintf('%s/sura-%03d.json', $this->directory, $surah))
                : $this->get("/translation/sura/{$key}/{$surah}");
            $rows = $data['result'] ?? null;
            if (! is_array($rows)) {
                throw new RuntimeException("QuranEnc returned no verses for surah {$surah}.");
            }
            foreach ($rows as $row) {
                if ((int) ($row['sura'] ?? 0) !== $surah) {
                    throw new RuntimeException("The response for surah {$surah} contains verse {$row['sura']}:{$row['aya']}.");
                }
                $ref = QuranStructure::key($surah, (int) $row['aya']);
                if (isset($out[$ref])) {
                    throw new RuntimeException("Verse {$ref} appears twice in QuranEnc's data.");
                }
                $notes = $row['footnotes'] ?? null;
                $out[$ref] = ['text' => (string) $row['translation'], 'footnotes' => ($notes === null || $notes === '') ? null : (string) $notes];
            }
        }

        return $out;
    }

    /** The attribution QuranEnc's terms ask for, built only from QuranEnc's own metadata. */
    public static function editionAttributes(array $meta, array $local): array
    {
        $key = $meta['key'];
        $url = "https://quranenc.com/en/browse/{$key}";

        return [
            'identifier' => $local['identifier'],
            'language' => $meta['language_iso_code'],
            'name' => $meta['title'],
            'translator' => $local['translator'] ?? null,
            'publisher' => 'QuranEnc.com',
            // QuranEnc does not name a separate copyright holder; none is invented here.
            'copyright_holder' => $local['copyright_holder'] ?? null,
            'attribution_text' => "{$meta['title']}, version {$meta['version']}. {$meta['description']} Source: QuranEnc.com ({$url}).",
            'source_url' => $url,
            'license' => 'QuranEnc.com terms: may be re-published unmodified, naming the publisher and QuranEnc.com, citing the version, keeping it updated, and without inappropriate advertising.',
            'license_url' => $url,
            'version' => $meta['version'],
            'source_updated_at' => isset($meta['last_update']) ? date('Y-m-d H:i:s', (int) $meta['last_update']) : null,
            'import_source' => 'quranenc',
            'source_key' => $key,
        ];
    }

    private function readFile(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Missing file: {$path}");
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    private function get(string $path): array
    {
        return app(QuranEncClient::class)->get($path);
    }
}
