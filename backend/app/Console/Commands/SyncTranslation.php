<?php

namespace App\Console\Commands;

use App\Services\Quran\QuranEnc\QuranEncClient;
use App\Services\Quran\QuranEnc\QuranEncSync;
use Illuminate\Console\Command;
use Throwable;

/**
 * Synchronises a QuranEnc.com edition: downloads every surah into a private staging folder
 * (resumable, rate limited, retried), validates it, then hands it to quran:import-translation,
 * which applies the licence gate, the damage checks, revision history and checksum verification.
 * Until the licence is confirmed, it stops after staging and validation.
 */
class SyncTranslation extends Command
{
    protected $signature = 'quran:sync-translation
        {key : QuranEnc key, such as english_rwwad}
        {--dry-run : Stage and validate only, without writing translations}
        {--restart : Discard this version\'s staged files and download everything again}';

    protected $description = 'Download a QuranEnc edition (resumable) and import it once its licence is confirmed';

    public function handle(QuranEncClient $client, QuranEncSync $sync): int
    {
        $key = $this->argument('key');
        if (! config("deenmate.quran.quranenc.editions.{$key}")) {
            $this->error("{$key} is not a configured QuranEnc edition.");

            return self::FAILURE;
        }

        try {
            $list = $client->get('/translations/list');
            $meta = collect($list['translations'] ?? [])->firstWhere('key', $key);
            if (! $meta || blank($meta['version'] ?? null)) {
                $this->error("QuranEnc's translation list does not include {$key} with a version, so it cannot be synchronised. Its terms require citing the version.");

                return self::FAILURE;
            }
            $this->line("Source: {$meta['title']}, version {$meta['version']}, updated ".date('Y-m-d H:i:s', (int) $meta['last_update']).'.');

            $done = 0;
            $result = $sync->stage($meta, (bool) $this->option('restart'), function (int $surah, string $state) use (&$done) {
                $done++;
                if ($surah % 19 === 0 || $surah === 114) {
                    $this->line("  {$done}/114 surahs staged (last: {$surah}, {$state})");
                }
            });
        } catch (Throwable $e) {
            $this->error('Synchronisation stopped: '.$e->getMessage());
            $this->line('Files already staged are kept, and the next run resumes from where this one stopped. Nothing was imported.');

            return self::FAILURE;
        }

        $this->info("Staged {$result['verses']} verses in 114 surahs ({$result['fetched']} surahs downloaded, {$result['resumed']} reused from an earlier run).");
        $this->line("Staging folder: {$result['dir']}");

        // The existing importer applies every safeguard, including the licence gate.
        return $this->call('quran:import-translation', [
            'key' => $key,
            '--source-dir' => $result['dir'],
            '--dry-run' => (bool) $this->option('dry-run'),
        ]);
    }
}
