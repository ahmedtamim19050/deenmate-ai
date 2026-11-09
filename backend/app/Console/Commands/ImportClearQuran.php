<?php

namespace App\Console\Commands;

use App\Services\Quran\ClearQuran\ClearQuranReader;
use App\Services\Quran\TranslationImporter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Imports Talal Itani's Clear Quran from ClearQuran.com's official plain-text download, through
 * the existing TranslationImporter (damage checks, revisions, checksum read-back, review states).
 * Licence: CC BY-ND 4.0 plus the copyright holder's published permission to store and distribute,
 * on condition that no words are changed and the credit is shown. The import stops if the file's
 * own licence line no longer says so.
 */
class ImportClearQuran extends Command
{
    protected $signature = 'quran:import-clearquran
        {zip : Path to the official clearquran-txt-*-edition.zip from https://clearquran.com/download/}
        {--edition=allah : allah or god}
        {--dry-run : Validate and report without writing translations}';

    protected $description = 'Import the Clear Quran (Talal Itani) from its official download';

    public function handle(TranslationImporter $importer): int
    {
        $editionKey = $this->option('edition');
        $local = config("deenmate.quran.clearquran.editions.{$editionKey}");
        $licence = config('deenmate.quran.clearquran.licence');
        if (! $local || blank($licence['permission_url'] ?? null)) {
            $this->error('Unknown edition, or no licence evidence recorded in config.');

            return self::FAILURE;
        }

        try {
            $reader = ClearQuranReader::fromZip($this->argument('zip'), $local['file']);
        } catch (Throwable $e) {
            $this->error('Could not read the download: '.$e->getMessage());

            return self::FAILURE;
        }
        $meta = $reader->meta();
        // The file's own header must match what the published permission covers.
        foreach (['Name' => $local['name'], 'Translator' => 'Talal Itani', 'License' => $licence['file_licence_line']] as $field => $expected) {
            if (($meta[$field] ?? null) !== $expected) {
                $this->error("The file's {$field} is \"".($meta[$field] ?? '').'", expected "'.$expected.'". Nothing was imported; check the current licence first.');

                return self::FAILURE;
            }
        }
        try {
            $updated = Carbon::createFromFormat('F j, Y', $meta['Last Update'] ?? '')->startOfDay();
        } catch (Throwable) {
            $this->error('The file has no readable "Last Update" date, so its edition cannot be identified.');

            return self::FAILURE;
        }

        $version = $updated->format('Y-m-d');
        $credit = $licence['credit'];
        $edition = [
            'identifier' => $local['identifier'],
            'language' => 'en',
            // The required credit is part of the name, so it shows wherever the edition is listed.
            'name' => "{$meta['Name']}, {$credit}",
            'translator' => 'Talal Itani',
            'publisher' => 'ClearQuran.com',
            'copyright_holder' => 'Talal Itani',
            'attribution_text' => "{$credit} (https://clearquran.com). {$meta['Name']}, last updated {$meta['Last Update']}.",
            'source_url' => $licence['permission_url'],
            'license' => $licence['summary'],
            'license_url' => $licence['licence_url'],
            'version' => $version,
            'source_updated_at' => $updated->toDateTimeString(),
            'import_source' => 'clearquran',
            'source_key' => "{$editionKey}-edition",
        ];
        $this->line("{$meta['Name']}, last updated {$meta['Last Update']} -> {$edition['identifier']} (".count($reader->verses()).' verses read)');

        $log = $importer->import($edition, $reader->verses(), (bool) $this->option('dry-run'));
        if ($log->status === 'failed') {
            $this->error('The import was refused. Nothing was written. Problems:');
            foreach (array_slice($log->error_details['validation'] ?? [$log->error_details['exception'] ?? ''], 0, 40) as $p) {
                $this->line("  - {$p}");
            }

            return self::FAILURE;
        }
        if (! $this->option('dry-run')) {
            // Keep the exact file imported, for traceability.
            Storage::disk('local')->put("quran/clearquran/{$editionKey}/{$version}/".basename($this->argument('zip')), file_get_contents($this->argument('zip')));
        }
        $flags = $log->options['flags'] ?? 0;
        $this->info(($this->option('dry-run') ? 'Dry run (nothing written): ' : 'Imported: ')."{$log->imported_records} new, {$log->updated_records} changed, {$log->skipped_records} unchanged verses; {$flags} flags for review. Checksum {$log->options['checksum']}.");
        $this->line("Import log #{$log->id}.");

        return self::SUCCESS;
    }
}
