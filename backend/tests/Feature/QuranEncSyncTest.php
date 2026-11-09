<?php

namespace Tests\Feature;

use App\Models\QuranTranslation;
use App\Models\QuranTranslationEdition as Edition;
use App\Models\QuranTranslationRevision;
use App\Services\Quran\QuranEnc\QuranEncClient;
use App\Services\Quran\QuranStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** A fake QuranEnc API with placeholder text; no real translation is stored in the tests. */
class QuranEncSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;

    private array $calls = [];

    /** Surah numbers that fail on their first request, with the status to return. */
    private array $failOnce = [];

    private array $broken = [];

    private string $version = '1.0.21';

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir().'/quranenc-sync-'.uniqid();
        $this->app->useStoragePath($this->storage);
        // No real waiting between requests in tests.
        $this->app->instance(QuranEncClient::class, new QuranEncClient(fn (int $ms) => null));
        config(['deenmate.quran.quranenc.editions.english_rwwad.licence_confirmed' => null]);

        $now = now();
        $surahs = $verses = [];
        foreach (QuranStructure::VERSE_COUNTS as $i => $count) {
            $s = $i + 1;
            $surahs[] = ['id' => $s, 'name_arabic' => "س{$s}", 'name_transliterated' => "S{$s}", 'name_english' => "S{$s}", 'revelation_place' => 'meccan', 'verse_count' => $count, 'created_at' => $now, 'updated_at' => $now];
            for ($v = 1; $v <= $count; $v++) {
                $verses[] = ['surah_id' => $s, 'verse_number' => $v, 'verse_key' => "{$s}:{$v}", 'text_arabic' => "نص {$s} {$v}", 'text_source' => 'test', 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::table('quran_surahs')->insert($surahs);
        foreach (array_chunk($verses, 500) as $chunk) {
            DB::table('quran_verses')->insert($chunk);
        }

        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $this->calls[] = $path;
            if (str_ends_with($path, '/translations/list')) {
                return Http::response(['translations' => [[
                    'key' => 'english_rwwad', 'language_iso_code' => 'en', 'version' => $this->version, 'last_update' => 1794213356,
                    'title' => 'English Translation - Rowwad Translation Center', 'description' => 'Translated by the Rowwad Translation Center.',
                ]]]);
            }
            if (preg_match('#/translation/sura/english_rwwad/(\d+)$#', $path, $m)) {
                $s = (int) $m[1];
                if (isset($this->failOnce[$s])) {
                    $status = $this->failOnce[$s];
                    unset($this->failOnce[$s]);

                    return Http::response(['error' => 'busy'], $status, $status === 429 ? ['Retry-After' => '1'] : []);
                }
                $rows = [];
                for ($v = 1; $v <= QuranStructure::verseCount($s); $v++) {
                    $rows[] = ['id' => "{$s}{$v}", 'sura' => (string) $s, 'aya' => (string) $v, 'arabic_text' => 'نص', 'translation' => "Translation {$s} {$v}".($v === 1 ? ' [1]' : ''), 'footnotes' => $v === 1 ? '[1] A note.' : ''];
                }
                if (isset($this->broken[$s])) {
                    array_pop($rows);
                }

                return Http::response(['result' => $rows]);
            }

            return Http::response([], 404);
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function suraCalls(): int
    {
        return count(array_filter($this->calls, fn ($p) => str_contains($p, '/translation/sura/')));
    }

    public function test_dry_run_stages_every_surah_and_writes_no_translations(): void
    {
        $this->artisan('quran:sync-translation', ['key' => 'english_rwwad', '--dry-run' => true])
            ->expectsOutputToContain('Staged 6236 verses in 114 surahs (114 surahs downloaded, 0 reused')
            ->expectsOutputToContain('Dry run (nothing written): 6236 new')
            ->assertSuccessful();

        $this->assertSame(114, $this->suraCalls());
        $dir = $this->storage.'/app/quran/quranenc/english_rwwad/1.0.21/staging';
        $this->assertCount(114, glob("{$dir}/sura-*.json"));
        $manifest = json_decode(file_get_contents("{$dir}/manifest.json"), true);
        $this->assertCount(114, $manifest['surahs']);
        $this->assertArrayHasKey('completed_at', $manifest);
        $this->assertSame(0, Edition::count());
        $this->assertSame(0, QuranTranslation::count());
    }

    public function test_an_interrupted_sync_resumes_where_it_stopped(): void
    {
        $this->broken[50] = true;
        $this->artisan('quran:sync-translation', ['key' => 'english_rwwad', '--dry-run' => true])
            ->expectsOutputToContain('Surah 50: QuranEnc returned 44 verses, expected 45')
            ->assertFailed();
        $this->assertSame(50, $this->suraCalls());
        $this->assertFileDoesNotExist($this->storage.'/app/quran/quranenc/english_rwwad/1.0.21/staging/sura-050.json', 'bad data is never saved');

        unset($this->broken[50]);
        $this->calls = [];
        $this->artisan('quran:sync-translation', ['key' => 'english_rwwad', '--dry-run' => true])
            ->expectsOutputToContain('(65 surahs downloaded, 49 reused')
            ->assertSuccessful();
        $this->assertSame(65, $this->suraCalls());
    }

    public function test_server_errors_and_rate_limits_are_retried(): void
    {
        $this->failOnce = [3 => 500, 7 => 429, 9 => 503];
        $this->artisan('quran:sync-translation', ['key' => 'english_rwwad', '--dry-run' => true])->assertSuccessful();
        $this->assertSame(117, $this->suraCalls(), 'three retried requests');
    }

    public function test_a_repeated_sync_downloads_nothing_and_a_repeated_import_creates_no_revision(): void
    {
        config(['deenmate.quran.quranenc.editions.english_rwwad.licence_confirmed' => 'Test permission letter']);
        $this->artisan('quran:sync-translation', ['key' => 'english_rwwad'])->assertSuccessful();
        $edition = Edition::where('identifier', 'en.rwwad')->first();
        $this->assertSame(Edition::STATUS_IMPORTED, $edition->review_status, 'never approved automatically');
        $this->assertFalse($edition->is_active);
        $this->assertSame(QuranStructure::VERSES, $edition->translations()->count());
        $this->assertSame('[1] A note.', QuranTranslation::whereHas('verse', fn ($q) => $q->where('verse_key', '2:1'))->value('footnotes_raw'));
        $checksum = $edition->content_checksum;

        $this->calls = [];
        $this->artisan('quran:sync-translation', ['key' => 'english_rwwad'])
            ->expectsOutputToContain('(0 surahs downloaded, 114 reused')
            ->assertSuccessful();
        $this->assertSame(0, $this->suraCalls());
        $this->assertSame(1, QuranTranslationRevision::count());
        $this->assertSame(QuranStructure::VERSES, QuranTranslation::count(), 'no duplicate translations');
        $this->assertSame($checksum, $edition->refresh()->content_checksum);
    }

    public function test_without_a_confirmed_licence_nothing_is_written_to_the_database(): void
    {
        $this->artisan('quran:sync-translation', ['key' => 'english_rwwad'])
            ->expectsOutputToContain('licence is not confirmed')
            ->assertFailed();
        $this->assertSame(0, Edition::count());
        $this->assertSame(0, QuranTranslation::count());
    }

    public function test_a_new_version_is_staged_separately(): void
    {
        $this->artisan('quran:sync-translation', ['key' => 'english_rwwad', '--dry-run' => true])->assertSuccessful();
        $this->version = '1.0.22';
        $this->calls = [];
        $this->artisan('quran:sync-translation', ['key' => 'english_rwwad', '--dry-run' => true])
            ->expectsOutputToContain('(114 surahs downloaded, 0 reused')
            ->assertSuccessful();
        $this->assertDirectoryExists($this->storage.'/app/quran/quranenc/english_rwwad/1.0.21/staging');
        $this->assertDirectoryExists($this->storage.'/app/quran/quranenc/english_rwwad/1.0.22/staging');
    }

    public function test_a_tampered_staged_file_is_downloaded_again(): void
    {
        $this->artisan('quran:sync-translation', ['key' => 'english_rwwad', '--dry-run' => true])->assertSuccessful();
        file_put_contents($this->storage.'/app/quran/quranenc/english_rwwad/1.0.21/staging/sura-002.json', '{"result":[]}');
        $this->calls = [];
        $this->artisan('quran:sync-translation', ['key' => 'english_rwwad', '--dry-run' => true])
            ->expectsOutputToContain('(1 surahs downloaded, 113 reused')
            ->assertSuccessful();
    }
}
