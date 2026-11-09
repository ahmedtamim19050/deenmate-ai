<?php

namespace App\Console\Commands;

use App\Services\Quran\ArabicTextImporter;
use App\Services\Quran\Tanzil\TanzilClient;
use App\Services\Quran\Tanzil\TanzilMetadata;
use App\Services\Quran\Tanzil\TanzilText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Imports the Arabic Quran from Tanzil (Uthmani, plus Simple Clean for search) with full
 * validation. Safe to repeat. It never deletes, and it only overwrites stored text that differs
 * from Tanzil when --replace-text is given.
 */
class ImportArabicQuran extends Command
{
    protected $signature = 'quran:import-arabic
        {--dry-run : Validate and report what would change, without writing Quran data}
        {--replace-text : Replace stored verse text that differs from Tanzil}
        {--file= : A local Tanzil Uthmani XML file instead of downloading}
        {--clean-file= : A local Tanzil Simple Clean XML file}
        {--metadata-file= : A local Tanzil quran-data.xml file}';

    protected $description = 'Import and verify the Arabic Quran text from the Tanzil Project';

    public function handle(TanzilClient $client, ArabicTextImporter $importer): int
    {
        try {
            $uthmaniXml = $this->read('file', fn () => $client->text('uthmani', TanzilClient::UTHMANI_OPTIONS), 'Uthmani text');
            $cleanXml = $this->read('clean-file', fn () => $client->text('simple-clean'), 'Simple Clean text');
            $metaXml = $this->read('metadata-file', fn () => $client->metadata(), 'metadata');
            $uthmani = TanzilText::parse($uthmaniXml);
            $clean = TanzilText::parse($cleanXml);
            $metadata = TanzilMetadata::parse($metaXml);
        } catch (Throwable $e) {
            $this->error('Could not load the Tanzil data: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('dry-run')) {
            // Keep the exact files imported, with Tanzil's copyright block intact.
            $stamp = now()->format('Ymd_His');
            Storage::disk('local')->put("quran/tanzil/{$stamp}/quran-uthmani.xml", $uthmaniXml);
            Storage::disk('local')->put("quran/tanzil/{$stamp}/quran-simple-clean.xml", $cleanXml);
            Storage::disk('local')->put("quran/tanzil/{$stamp}/quran-data.xml", $metaXml);
        }

        $this->line("Source: {$uthmani->edition}. {$uthmani->copyright}");
        $result = $importer->import(
            $uthmani,
            $clean,
            $metadata,
            $this->sourceAttributes(),
            dryRun: (bool) $this->option('dry-run'),
            replaceText: (bool) $this->option('replace-text'),
        );

        if (! $result->ok) {
            $this->error('Validation failed. Nothing was written. Problems ('.count($result->problems).'):');
            foreach (array_slice($result->problems, 0, 50) as $p) {
                $this->line("  - {$p}");
            }
            $this->line("Import log #{$result->log->id}.");

            return self::FAILURE;
        }

        $mode = $this->option('dry-run') ? 'Dry run (nothing written)' : 'Imported';
        $this->info("{$mode}: {$result->inserted} new verses, {$result->updated} updated, {$result->unchanged} unchanged.");
        if ($result->differences) {
            $count = count($result->differences);
            $sample = implode(', ', array_slice($result->differences, 0, 15));
            $result->replacedText
                ? $this->warn("{$count} verses had different stored text and were replaced with Tanzil's: {$sample}".($count > 15 ? ', …' : ''))
                : $this->warn("{$count} verses have stored text that differs from Tanzil and were left unchanged: {$sample}".($count > 15 ? ', …' : '').'. Review them, then run again with --replace-text to use Tanzil\'s text.');
        }
        if ($result->divisionMismatches) {
            $this->warn(count($result->divisionMismatches).' juz or page values differ from Tanzil and were kept. See the import log.');
        }
        $this->line("Import log #{$result->log->id}.");

        return self::SUCCESS;
    }

    private function read(string $option, callable $download, string $label): string
    {
        $path = $this->option($option);
        if ($path) {
            if (! is_file($path)) {
                throw new \RuntimeException("{$label} file not found: {$path}");
            }

            return (string) file_get_contents($path);
        }
        $this->line("Downloading Tanzil {$label}…");

        return $download();
    }

    private function sourceAttributes(): array
    {
        return [
            'slug' => 'tanzil',
            'name' => 'Tanzil Project',
            'website' => 'https://tanzil.net',
            'api_endpoint' => config('deenmate.quran.tanzil.download_url'),
            'license_name' => 'Creative Commons Attribution 3.0 (verbatim copies only)',
            'license_url' => 'https://tanzil.net/docs/text_license',
            'attribution_text' => 'Quran text: Tanzil Quran Text, Copyright (C) 2007-2026 Tanzil Project, licensed under Creative Commons Attribution 3.0. Changing the text is not allowed. https://tanzil.net',
        ];
    }
}
