<?php

namespace Tests\Feature;

use App\Models\QuranTranslation;
use App\Models\QuranTranslationEdition as Edition;
use App\Models\QuranTranslationRevision;
use App\Services\Quran\ClearQuran\ClearQuranReader;
use App\Services\Quran\QuranStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** Placeholder text in the official file layout; no real translation is stored in the tests. */
class ClearQuranImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');
        $this->dir = sys_get_temp_dir().'/clearquran-test-'.uniqid();
        mkdir($this->dir);
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
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function zip(string $licence = 'CC BY-ND 4.0 (https://creativecommons.org/licenses/by-nd/4.0/)', ?callable $edit = null): string
    {
        $lines = [];
        foreach (QuranStructure::VERSE_COUNTS as $i => $count) {
            for ($v = 1; $v <= $count; $v++) {
                $lines[] = ($i + 1)."|{$v}|Placeholder “text” ".($i + 1)." {$v}—end.";
            }
        }
        if ($edit) {
            $lines = $edit($lines);
        }
        $text = implode("\n", $lines)."\n\n#===\n# Name: Clear Quran (Allah Edition)\n# Translator: Talal Itani\n# Language: English\n# Last Update: October 9, 2026\n# Source: https://ClearQuran.com\n# License: {$licence}\n#===\n";
        $path = "{$this->dir}/clearquran-txt-allah-edition.zip";
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('clearquran-allah-edition.txt', $text);
        $zip->addFromString('_readme.txt', '# License: CC BY-ND 4.0');
        $zip->close();

        return $path;
    }

    public function test_reader_returns_text_exactly_and_reads_the_header(): void
    {
        $r = ClearQuranReader::fromString("1|1|  Spaced “quote”—dash.  \r\n# Last Update: October 9, 2026\n");
        $this->assertSame(['1:1' => '  Spaced “quote”—dash.  '], $r->verses());
        $this->assertSame('October 9, 2026', $r->meta()['Last Update']);

        $this->expectException(\RuntimeException::class);
        ClearQuranReader::fromString("1|1|a\n1|1|b\n");
    }

    public function test_dry_run_then_import_with_credit_version_and_review_state(): void
    {
        $zip = $this->zip();
        $this->artisan('quran:import-clearquran', ['zip' => $zip, '--dry-run' => true])
            ->expectsOutputToContain('Dry run (nothing written): 6236 new')
            ->assertSuccessful();
        $this->assertSame(0, Edition::count());

        $this->artisan('quran:import-clearquran', ['zip' => $zip])->expectsOutputToContain('Imported: 6236 new')->assertSuccessful();
        $edition = Edition::where('identifier', 'en.clearquran')->first();
        $this->assertSame('Clear Quran (Allah Edition), Translation by Talal Itani, ClearQuran.com', $edition->name);
        $this->assertSame('2026-10-09', $edition->version);
        $this->assertSame('Talal Itani', $edition->copyright_holder);
        $this->assertStringContainsString('https://clearquran.com', $edition->attribution_text);
        $this->assertSame(Edition::STATUS_IMPORTED, $edition->review_status, 'never approved automatically');
        $this->assertFalse($edition->is_active);
        $this->assertSame('Placeholder “text” 2 255—end.', QuranTranslation::whereHas('verse', fn ($q) => $q->where('verse_key', '2:255'))->value('text'));
        $this->assertSame(1, QuranTranslationRevision::count());

        $this->artisan('quran:import-clearquran', ['zip' => $zip])->assertSuccessful();
        $this->assertSame(1, QuranTranslationRevision::count(), 'repeat import changes nothing');
        $this->assertSame(QuranStructure::VERSES, QuranTranslation::count());
    }

    public function test_a_changed_licence_line_stops_the_import(): void
    {
        $this->artisan('quran:import-clearquran', ['zip' => $this->zip('CC BY-NC-ND 4.0')])
            ->expectsOutputToContain("The file's License is")
            ->assertFailed();
        $this->assertSame(0, Edition::count());
    }

    public function test_a_missing_verse_stops_the_import(): void
    {
        $zip = $this->zip(edit: fn (array $lines) => array_values(array_filter($lines, fn ($l) => ! str_starts_with($l, '2|255|'))));
        $this->artisan('quran:import-clearquran', ['zip' => $zip])->expectsOutputToContain('2:255')->assertFailed();
        $this->assertSame(0, QuranTranslation::count());
    }
}
