<?php

namespace App\Console\Commands;

use App\Services\Quran\QuranEnc\QuranEncReader;
use App\Services\Quran\TranslationImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Imports a QuranEnc.com translation. A real import runs only once the edition's licence is
 * confirmed in config; a dry run validates everything and writes no translations.
 */
class ImportTranslation extends Command
{
    protected $signature = 'quran:import-translation
        {key : QuranEnc key, such as bengali_zakaria or english_rwwad}
        {--source-dir= : A folder of saved QuranEnc API responses instead of downloading}
        {--dry-run : Validate and report without writing translations}
        {--acknowledge=* : Verse keys whose errors a reviewer has accepted; they are imported as flagged records}';

    protected $description = 'Import a translation edition from QuranEnc.com (licence must be confirmed first)';

    public function handle(TranslationImporter $importer): int
    {
        $key = $this->argument('key');
        $local = config("deenmate.quran.quranenc.editions.{$key}");
        if (! $local) {
            $this->error("{$key} is not a configured QuranEnc edition.");

            return self::FAILURE;
        }
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && blank($local['licence_confirmed'] ?? null)) {
            $this->error("{$key}: the licence is not confirmed. Get written permission from QuranEnc.com and record its reference in config first. A --dry-run is allowed meanwhile.");

            return self::FAILURE;
        }

        try {
            $reader = $this->option('source-dir') ? QuranEncReader::fromDirectory($this->option('source-dir')) : new QuranEncReader;
            $meta = $reader->metadata($key);
            if ($meta === null) {
                $this->error("QuranEnc's translation list does not include {$key}, so its version cannot be verified. QuranEnc's terms require citing the version: ask QuranEnc for it.");

                return self::FAILURE;
            }
            $verses = $reader->verses($key);
        } catch (Throwable $e) {
            $this->error('Could not read QuranEnc data: '.$e->getMessage());

            return self::FAILURE;
        }

        $edition = QuranEncReader::editionAttributes($meta, $local);
        $this->line("{$edition['name']} (version {$edition['version']}, updated {$edition['source_updated_at']}) -> {$edition['identifier']}");
        $log = $importer->import($edition, $verses, $dryRun, $this->option('acknowledge'));

        if ($log->status === 'failed') {
            $this->error('The import was refused. Nothing was written. Problems:');
            foreach (array_slice($log->error_details['validation'] ?? [$log->error_details['exception'] ?? ''], 0, 40) as $p) {
                $this->line("  - {$p}");
            }
            $this->line("Import log #{$log->id}.");

            return self::FAILURE;
        }
        if (! $dryRun) {
            // The exact data imported, kept as QuranEnc published it.
            Storage::disk('local')->put("quran/quranenc/{$key}/{$edition['version']}/verses.json", json_encode($verses, JSON_UNESCAPED_UNICODE));
        }
        $flags = $log->options['flags'] ?? 0;
        $this->info(($dryRun ? 'Dry run (nothing written): ' : 'Imported: ')."{$log->imported_records} new, {$log->updated_records} changed, {$log->skipped_records} unchanged verses; {$flags} flags for review.");
        $this->line("Import log #{$log->id}.");

        return self::SUCCESS;
    }
}
