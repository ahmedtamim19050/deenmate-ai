<?php

namespace Tests\Feature;

use App\Models\QuranDataSource;
use App\Models\QuranImportLog;
use App\Models\QuranSurah;
use App\Models\QuranTafsir;
use App\Models\QuranTranslationEdition;
use App\Models\QuranVerse;
use App\Services\Quran\QuranLookup;
use App\Services\Quran\QuranStructure;
use App\Services\Quran\TafsirImporter;
use App\Services\Quran\TranslationImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uses a synthetic dataset with the Quran's real structure (114 surahs, 6,236 verses) and
 * placeholder Arabic text, so the importer's rules are tested without shipping Quran text here.
 */
class QuranKnowledgeImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->dir = sys_get_temp_dir().'/quran-import-test-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** Placeholder verse text: Arabic letters plus the verse's numbers, so every verse differs. */
    private static function text(int $s, int $v, bool $clean = false): string
    {
        return ($clean ? 'نص' : 'نَصّ').' '.$s.' '.$v;
    }

    private function textXml(?callable $edit = null, bool $clean = false): string
    {
        $suras = [];
        foreach (QuranStructure::VERSE_COUNTS as $i => $count) {
            $s = $i + 1;
            $ayas = [];
            for ($v = 1; $v <= $count; $v++) {
                $ayas[$v] = self::text($s, $v, $clean);
            }
            $suras[$s] = $ayas;
        }
        if ($edit) {
            $suras = $edit($suras);
        }
        $xml = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n<!--\n#  Tanzil Quran Text (Test, Version 1.1)\n#  Copyright (C) 2007-2026 Tanzil Project\n-->\n<quran>\n";
        foreach ($suras as $s => $ayas) {
            $xml .= "<sura index=\"{$s}\" name=\"s{$s}\">\n";
            foreach ($ayas as $v => $t) {
                $xml .= '<aya index="'.$v.'" text="'.htmlspecialchars($t, ENT_XML1).'" />'."\n";
            }
            $xml .= "</sura>\n";
        }

        return $xml."</quran>\n";
    }

    private function metadataXml(): string
    {
        $xml = "<quran>\n<suras>\n";
        foreach (QuranStructure::VERSE_COUNTS as $i => $count) {
            $s = $i + 1;
            $xml .= "<sura index=\"{$s}\" ayas=\"{$count}\" name=\"س{$s}\" tname=\"Surah {$s}\" ename=\"Name {$s}\" type=\"".($s % 2 ? 'Meccan' : 'Medinan')."\" order=\"{$s}\" />\n";
        }
        $xml .= "</suras>\n<juzs>\n";
        // Thirty juz starting at the first verse of surahs 1..30, enough to exercise the lookup.
        for ($j = 1; $j <= 30; $j++) {
            $xml .= "<juz index=\"{$j}\" sura=\"{$j}\" aya=\"1\" />\n";
        }
        $xml .= "</juzs>\n<pages>\n<page index=\"1\" sura=\"1\" aya=\"1\" />\n<page index=\"2\" sura=\"2\" aya=\"1\" />\n</pages>\n</quran>\n";

        return $xml;
    }

    private function files(?callable $edit = null): array
    {
        file_put_contents("{$this->dir}/u.xml", $this->textXml($edit));
        file_put_contents("{$this->dir}/c.xml", $this->textXml(null, true));
        file_put_contents("{$this->dir}/m.xml", $this->metadataXml());

        return ['--file' => "{$this->dir}/u.xml", '--clean-file' => "{$this->dir}/c.xml", '--metadata-file' => "{$this->dir}/m.xml"];
    }

    public function test_full_import_creates_every_surah_and_verse_with_source_and_log(): void
    {
        $this->artisan('quran:import-arabic', $this->files())->assertSuccessful();

        $this->assertSame(QuranStructure::SURAHS, QuranSurah::count());
        $this->assertSame(QuranStructure::VERSES, QuranVerse::count());
        $this->assertSame(QuranStructure::VERSES, QuranVerse::query()->distinct()->count('verse_key'));
        foreach (QuranStructure::VERSE_COUNTS as $i => $count) {
            $this->assertSame($count, QuranVerse::where('surah_id', $i + 1)->count(), 'surah '.($i + 1));
        }
        $verse = QuranVerse::where('verse_key', '2:255')->first();
        $this->assertSame(self::text(2, 255), $verse->text_arabic);
        $this->assertSame(self::text(2, 255, true), $verse->text_simple_clean);
        $this->assertSame(2, $verse->juz);
        $this->assertSame('tanzil', $verse->source->slug);
        $this->assertStringContainsString('Tanzil', $verse->text_source);
        $this->assertSame('Creative Commons Attribution 3.0 (verbatim copies only)', QuranDataSource::first()->license_name);

        $log = QuranImportLog::latest('id')->first();
        $this->assertSame(QuranImportLog::STATUS_COMPLETED, $log->status);
        $this->assertSame(QuranStructure::VERSES, $log->imported_records);
        Storage::disk('local')->assertExists(collect(Storage::disk('local')->allFiles('quran/tanzil'))->first());
    }

    public function test_running_twice_changes_nothing_and_creates_no_duplicates(): void
    {
        $files = $this->files();
        $this->artisan('quran:import-arabic', $files)->assertSuccessful();
        $before = QuranVerse::orderBy('id')->get(['id', 'text_arabic', 'updated_at'])->toArray();

        $this->artisan('quran:import-arabic', $files)->assertSuccessful();

        $this->assertSame(QuranStructure::VERSES, QuranVerse::count());
        $this->assertSame($before, QuranVerse::orderBy('id')->get(['id', 'text_arabic', 'updated_at'])->toArray());
        $log = QuranImportLog::latest('id')->first();
        $this->assertSame(0, $log->imported_records);
        $this->assertSame(0, $log->updated_records);
        $this->assertSame(QuranStructure::VERSES, $log->skipped_records);
    }

    public function test_a_missing_verse_fails_validation_naming_it_and_writes_nothing(): void
    {
        $files = $this->files(function (array $s) {
            unset($s[2][255]);

            return $s;
        });

        $this->artisan('quran:import-arabic', $files)
            ->expectsOutputToContain('missing 2:255')
            ->assertFailed();

        $this->assertSame(0, QuranVerse::count());
        $this->assertSame(0, QuranSurah::count());
        $log = QuranImportLog::latest('id')->first();
        $this->assertSame(QuranImportLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('2:255', json_encode($log->error_details));
    }

    public function test_corrupted_text_is_reported_by_reference(): void
    {
        $files = $this->files(function (array $s) {
            $s[18][10] = "نص \u{FFFD}";

            return $s;
        });

        $this->artisan('quran:import-arabic', $files)->expectsOutputToContain('Verse 18:10')->assertFailed();
        $this->assertSame(0, QuranVerse::count());
    }

    public function test_dry_run_writes_no_quran_data(): void
    {
        $this->artisan('quran:import-arabic', $this->files() + ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, QuranVerse::count());
        $this->assertSame(0, QuranSurah::count());
        $this->assertSame(0, QuranDataSource::count());
        $this->assertSame(QuranImportLog::STATUS_DRY_RUN, QuranImportLog::latest('id')->first()->status);
    }

    public function test_stored_text_that_differs_is_kept_unless_replacement_is_asked_for(): void
    {
        $files = $this->files();
        $this->artisan('quran:import-arabic', $files)->assertSuccessful();
        DB::table('quran_verses')->where('verse_key', '1:1')->update(['text_arabic' => 'نص قديم', 'text_source' => 'Older source']);

        $this->artisan('quran:import-arabic', $files)->expectsOutputToContain('left unchanged: 1:1')->assertSuccessful();
        $this->assertSame('نص قديم', QuranVerse::where('verse_key', '1:1')->value('text_arabic'));
        $this->assertSame('Older source', QuranVerse::where('verse_key', '1:1')->value('text_source'));

        $this->artisan('quran:import-arabic', $files + ['--replace-text' => true])->assertSuccessful();
        $this->assertSame(self::text(1, 1), QuranVerse::where('verse_key', '1:1')->value('text_arabic'));
        $this->assertSame(QuranStructure::VERSES, QuranVerse::count());
    }

    public function test_translation_import_requires_attribution_and_a_complete_edition(): void
    {
        $this->artisan('quran:import-arabic', $this->files())->assertSuccessful();
        $importer = app(TranslationImporter::class);
        $edition = ['identifier' => 'xx.test', 'language' => 'bn', 'name' => 'Test edition', 'translator' => 'Tester', 'source_url' => null, 'license' => 'Test licence', 'license_url' => null, 'quran_data_source_id' => null, 'attribution_text' => 'Test edition. Source: test.'];

        $texts = [];
        foreach (QuranStructure::VERSE_COUNTS as $i => $count) {
            for ($v = 1; $v <= $count; $v++) {
                $texts[($i + 1).":{$v}"] = 'অনুবাদ '.($i + 1).":{$v}";
            }
        }
        $partial = $texts;
        unset($partial['114:6']);
        $failed = $importer->import($edition, $partial);
        $this->assertSame(QuranImportLog::STATUS_FAILED, $failed->status);
        $this->assertStringContainsString('114:6', json_encode($failed->error_details, JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, QuranTranslationEdition::where('identifier', 'xx.test')->count());

        $ok = $importer->import($edition, $texts);
        $this->assertSame(QuranImportLog::STATUS_COMPLETED, $ok->status);
        $saved = QuranTranslationEdition::where('identifier', 'xx.test')->first();
        $this->assertFalse($saved->is_active, 'a new edition stays hidden until switched on');
        $this->assertSame(QuranStructure::VERSES, $saved->translations()->count());

        $again = $importer->import($edition, $texts);
        $this->assertSame(0, $again->imported_records);
        $this->assertSame(QuranStructure::VERSES, $saved->translations()->count());

        $this->expectException(\InvalidArgumentException::class);
        $importer->import(['license' => ''] + $edition, $texts);
    }

    public function test_tafsir_can_cover_a_verse_range_and_is_found_for_each_verse_in_it(): void
    {
        $this->artisan('quran:import-arabic', $this->files())->assertSuccessful();
        $importer = app(TafsirImporter::class);
        $tafsir = ['slug' => 'test-tafsir', 'name' => 'Test Tafsir', 'author' => 'Author', 'language' => 'bn', 'license_name' => 'Test licence', 'license_url' => null, 'quran_data_source_id' => null];

        $overlap = $importer->import($tafsir, [['surah' => 2, 'from' => 1, 'to' => 5, 'text' => 'a'], ['surah' => 2, 'from' => 5, 'text' => 'b']]);
        $this->assertSame(QuranImportLog::STATUS_FAILED, $overlap->status);
        $this->assertStringContainsString('2:5', json_encode($overlap->error_details));

        $log = $importer->import($tafsir, [['surah' => 2, 'from' => 1, 'to' => 5, 'text' => 'Explanation of 2:1-5'], ['surah' => 2, 'from' => 6, 'text' => 'Explanation of 2:6']]);
        $this->assertSame(QuranImportLog::STATUS_COMPLETED, $log->status);

        $lookup = app(QuranLookup::class);
        $this->assertCount(0, $lookup->tafsirFor('2:3'), 'inactive tafsir is never served');
        QuranTafsir::where('slug', 'test-tafsir')->update(['is_active' => true]);
        $found = $lookup->tafsirFor('2:3');
        $this->assertCount(1, $found);
        $this->assertSame('2:1-5', $found->first()['reference']);
        $this->assertSame('Author', $found->first()['author']);
        $this->assertSame('2:6', $lookup->tafsirFor('2:6')->first()['reference']);
        $this->assertCount(0, $lookup->tafsirFor('2:7'));
    }

    public function test_lookup_returns_exact_references(): void
    {
        $this->artisan('quran:import-arabic', $this->files())->assertSuccessful();
        $lookup = app(QuranLookup::class);

        $this->assertSame(self::text(2, 255), $lookup->verse('2:255')->text_arabic);
        $this->assertNull($lookup->verse('1:8'));
        $this->assertNull($lookup->verse('not a key'));
        $this->assertCount(286, $lookup->surah(2)->verses);

        $hits = $lookup->searchArabic('نَصّ 2 255');
        $this->assertSame('2:255', $hits->first()['reference']);
        $this->assertSame('نص 2 255', QuranLookup::normaliseArabic('نَصّ 2 255'));
    }
}
