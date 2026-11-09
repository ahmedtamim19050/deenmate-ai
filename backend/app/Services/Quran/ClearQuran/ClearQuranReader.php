<?php

namespace App\Services\Quran\ClearQuran;

use App\Services\Quran\QuranStructure;
use RuntimeException;
use ZipArchive;

/**
 * Reads Talal Itani's Clear Quran from the official plain-text download on ClearQuran.com
 * (https://clearquran.com/download/): one verse per line as "surah|verse|text", followed by a
 * "#" block naming the edition, translator, last update and licence. Text is returned exactly
 * as published, without trimming or normalising.
 */
class ClearQuranReader
{
    /** @var array<string, string> */
    private array $meta = [];

    /** @var array<string, string> */
    private array $verses = [];

    public static function fromZip(string $zipPath, string $entry): self
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Cannot open {$zipPath}.");
        }
        $text = $zip->getFromName($entry);
        $zip->close();
        if ($text === false) {
            throw new RuntimeException("{$entry} is not in {$zipPath}.");
        }

        return self::fromString($text);
    }

    public static function fromString(string $text): self
    {
        $reader = new self;
        foreach (preg_split('/\n/', $text) as $n => $line) {
            $line = rtrim($line, "\r");
            if ($line === '' || preg_match('/^#=+$/', $line)) {
                continue;
            }
            if (preg_match('/^#\s*([^:]+):\s*(.+)$/', $line, $m)) {
                $reader->meta[trim($m[1])] = trim($m[2]);

                continue;
            }
            if (! preg_match('/^(\d{1,3})\|(\d{1,3})\|(.*)$/s', $line, $m)) {
                throw new RuntimeException('Line '.($n + 1).' is neither a verse nor a header line.');
            }
            $key = QuranStructure::key((int) $m[1], (int) $m[2]);
            if (isset($reader->verses[$key])) {
                throw new RuntimeException("Verse {$key} appears twice.");
            }
            $reader->verses[$key] = $m[3];
        }

        return $reader;
    }

    /** @return array<string, string> e.g. Name, Translator, Language, Last Update, Source, License */
    public function meta(): array
    {
        return $this->meta;
    }

    /** @return array<string, string> "surah:verse" => text */
    public function verses(): array
    {
        return $this->verses;
    }
}
