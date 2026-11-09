<?php

namespace App\Services\Quran\QuranEnc;

use App\Services\Quran\QuranStructure;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Downloads a QuranEnc edition surah by surah into a private staging folder, one file per surah,
 * exactly as QuranEnc returned it. Downloading is what QuranEnc's terms allow; storing the text
 * in the app's database is a separate step that goes through the licence gate and the importer.
 *
 * Resumable: a manifest records each completed surah with its SHA-256, so a later run fetches
 * only what is missing or does not match. Each version gets its own folder, so a new version never
 * mixes with an old one. Any incomplete or inconsistent response stops the run before it is saved.
 */
class QuranEncSync
{
    public function __construct(private QuranEncClient $client) {}

    public function stagingDir(string $key, string $version): string
    {
        // Keys and versions come from QuranEnc's list, but are still kept to safe path characters.
        $safe = fn (string $s) => preg_replace('/[^A-Za-z0-9._-]/', '_', $s);

        return storage_path('app/quran/quranenc/'.$safe($key).'/'.$safe($version).'/staging');
    }

    /**
     * @param  array{key: string, version: string, last_update?: int}  $meta  QuranEnc's own list entry
     * @param  callable(int $surah, string $state): void|null  $progress
     * @return array{dir: string, fetched: int, resumed: int, verses: int}
     */
    public function stage(array $meta, bool $restart = false, ?callable $progress = null): array
    {
        $dir = $this->stagingDir($meta['key'], $meta['version']);
        if ($restart && is_dir($dir)) {
            File::deleteDirectory($dir);
        }
        File::ensureDirectoryExists($dir);
        $this->writeJson("{$dir}/translations.json", ['translations' => [$meta]]);

        $manifestPath = "{$dir}/manifest.json";
        $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : null;
        if (! is_array($manifest) || ($manifest['version'] ?? null) !== $meta['version'] || ($manifest['last_update'] ?? null) !== ($meta['last_update'] ?? null)) {
            $manifest = ['key' => $meta['key'], 'version' => $meta['version'], 'last_update' => $meta['last_update'] ?? null, 'started_at' => now()->toIso8601String(), 'surahs' => []];
        }

        $fetched = $resumed = $verses = 0;
        for ($surah = 1; $surah <= QuranStructure::SURAHS; $surah++) {
            $file = sprintf('%s/sura-%03d.json', $dir, $surah);
            $entry = $manifest['surahs'][$surah] ?? null;
            if ($entry && is_file($file) && hash_file('sha256', $file) === $entry['sha256']) {
                $this->check($surah, json_decode((string) file_get_contents($file), true));
                $resumed++;
                $verses += $entry['verses'];
                $progress && $progress($surah, 'resumed');

                continue;
            }

            $data = $this->client->get("/translation/sura/{$meta['key']}/{$surah}");
            $count = $this->check($surah, $data);
            $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->writeAtomic($file, $body);
            $manifest['surahs'][$surah] = ['sha256' => hash('sha256', $body), 'verses' => $count, 'fetched_at' => now()->toIso8601String()];
            $this->writeJson($manifestPath, $manifest);
            $fetched++;
            $verses += $count;
            $progress && $progress($surah, 'fetched');
        }

        if ($verses !== QuranStructure::VERSES) {
            throw new RuntimeException("Staged {$verses} verses, expected ".QuranStructure::VERSES.'. Nothing was imported.');
        }
        $manifest['completed_at'] = now()->toIso8601String();
        $this->writeJson($manifestPath, $manifest);

        return compact('dir', 'fetched', 'resumed', 'verses');
    }

    /** Validates one surah response. Returns its verse count, or throws without saving anything. */
    private function check(int $surah, mixed $data): int
    {
        $rows = is_array($data) ? ($data['result'] ?? null) : null;
        if (! is_array($rows)) {
            throw new RuntimeException("Surah {$surah}: the response has no verse list.");
        }
        $expected = QuranStructure::verseCount($surah);
        if (count($rows) !== $expected) {
            throw new RuntimeException("Surah {$surah}: QuranEnc returned ".count($rows)." verses, expected {$expected}.");
        }
        foreach (array_values($rows) as $i => $row) {
            $ref = "{$surah}:".($i + 1);
            if ((int) ($row['sura'] ?? 0) !== $surah || (int) ($row['aya'] ?? 0) !== $i + 1) {
                throw new RuntimeException("Surah {$surah}: expected verse {$ref} in position ".($i + 1).', got '.($row['sura'] ?? '?').':'.($row['aya'] ?? '?').'.');
            }
            if (! is_string($row['translation'] ?? null) || trim($row['translation']) === '') {
                throw new RuntimeException("Verse {$ref}: the translation is missing.");
            }
        }

        return $expected;
    }

    private function writeAtomic(string $path, string $body): void
    {
        $tmp = $path.'.part';
        file_put_contents($tmp, $body, LOCK_EX);
        rename($tmp, $path);
    }

    private function writeJson(string $path, array $data): void
    {
        $this->writeAtomic($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
