<?php

namespace Tests\Feature;

use App\Models\IslamicSource;
use App\Models\QuranImportLog;
use App\Models\QuranTranslation;
use App\Models\QuranTranslationEdition as Edition;
use App\Models\QuranTranslationFlag;
use App\Models\QuranTranslationRevision;
use App\Models\User;
use App\Services\AI\SourceRetriever;
use App\Services\Quran\QuranLookup;
use App\Services\Quran\QuranStructure;
use App\Services\Quran\TranslationImporter;
use App\Services\Quran\Translations\FootnoteParser;
use App\Services\Quran\Translations\TranslationContent;
use App\Services\Quran\Translations\TranslationHistory;
use App\Services\Quran\Translations\TranslationReviewWorkflow;
use App\Services\Quran\Translations\TranslationTextInspector;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/** Placeholder text throughout: no real translation is stored in the tests. */
class TranslationHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const EDITION = [
        'identifier' => 'bn.test',
        'language' => 'bn',
        'name' => 'Test Bangla edition',
        'translator' => 'Test Translator',
        'publisher' => 'Test Publisher',
        'license' => 'Test licence',
        'attribution_text' => 'Test Bangla edition, version 1.0. Source: test.',
        'version' => '1.0',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $now = now();
        $surahs = [];
        $verses = [];
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

    /** @return array<string, array{text: string, footnotes: ?string}> */
    private function edition(string $word = 'অনুবাদ', array $override = []): array
    {
        $out = [];
        foreach (QuranStructure::VERSE_COUNTS as $i => $count) {
            for ($v = 1; $v <= $count; $v++) {
                $out[($i + 1).":{$v}"] = ['text' => "{$word} ".($i + 1)." {$v}", 'footnotes' => null];
            }
        }

        return array_replace($out, $override);
    }

    private function reviewer(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['admin_role' => User::ROLE_SCHOLAR_REVIEWER])->save();

        return $user;
    }

    private function storedChecksum(): string
    {
        return TranslationContent::stored(Edition::where('identifier', 'bn.test')->value('id'))->checksum();
    }

    // ---- 1. Corruption detection ----

    public function test_inspector_catches_every_known_corruption_form(): void
    {
        $i = new TranslationTextInspector;
        $codes = fn (string $text, ?string $notes = null, string $lang = 'bn') => array_map(fn ($x) => $x->code, array_filter($i->inspect($lang, $text, $notes), fn ($x) => $x->isError()));

        $this->assertContains('known_corruption_pattern', $codes('আল্লাহ কতৃꦣ2453; নির্ধারিত'), 'stray Javanese letter fused to a reference');
        $this->assertContains('known_corruption_pattern', $codes('সামর্থ?2480;য়েছে'), 'question mark fused to a reference');
        $this->assertContains('known_corruption_pattern', $codes('দারিদ্রে?480; কারণে'), 'reference followed by a space');
        $this->assertContains('known_corruption_pattern', $codes('আল্লাহ তা˦#2503;দরকে'), 'modifier letter fused to a reference');
        $this->assertContains('stray_combining_mark', $codes('নূহ (আঃ)͠ও'), 'Latin combining mark after punctuation');
        $this->assertContains('replacement_character', $codes("নষ্ট \u{FFFD} লেখা"));
        $this->assertContains('mixed_script_word', $codes('যা তোমরf ভালবাস'));
        $this->assertContains('mixed_script_word', $codes('যাকে সبজদা করানো'));
        $this->assertContains('unexpected_script', $codes('সূরা আল-আহযাব ৫૧'), 'Gujarati digit in Bangla');
        $this->assertContains('html_entity_in_text', $codes('আল্লাহ &amp; রাসূল'));
        $this->assertContains('control_character', $codes("লেখা\x07লেখা"));
        $this->assertContains('invalid_utf8', $codes("\xC3\x28"));
        $this->assertContains('unsafe_markup', $codes('ঠিক আছে', '<script>x</script>'));
    }

    public function test_inspector_accepts_legitimate_text(): void
    {
        $i = new TranslationTextInspector;
        $legit = [
            ['bn', 'সকল ‘হাম্‌দ’ [১] আল্লাহ্‌র [২], যিনি সৃষ্টিকুলের রব।', "[১] ব্যাখ্যা। [দেখুন, বুখারী: ৩]\n\n[২] <b>আরও</b> কথা ﴿بِسْمِ ٱللَّهِ﴾ এখানে।"],
            ['bn', 'সূরা আল ফাজর ১৫২০; আয়াত।', null],
            ['bn', '(সাঃ) এবং ২:২৫৫ আয়াতুল কুরসী — “উদ্ধৃতি”', null],
            ['en', 'He said, “Saʿīd and Shuʿayb went to the Kaʿba.”', '[1] See al-Ṭabarī; Q 2:255.'],
            ['en', 'In the name of Allah, the Most Compassionate (ﷺ).', null],
        ];
        foreach ($legit as [$lang, $text, $notes]) {
            $errors = array_filter($i->inspect($lang, $text, $notes), fn ($x) => $x->isError());
            $this->assertSame([], array_map(fn ($x) => $x->detail, array_values($errors)), $text);
        }
    }

    // ---- 2. Footnotes ----

    public function test_footnotes_are_kept_verbatim_and_indexed_by_the_markers_in_the_text(): void
    {
        $raw = "<b>ভূমিকা</b>\n\n[১] প্রথম টীকা [বুখারী ৩]।\n[২] দ্বিতীয় টীকা।";
        $notes = FootnoteParser::parse('কথা [১] আরও [২]', $raw);
        $this->assertSame([null, '[১]', '[২]'], array_column($notes, 'marker'));
        $this->assertSame('<b>ভূমিকা</b>', $notes[0]['text']);
        $this->assertSame('প্রথম টীকা [বুখারী ৩]।', $notes[1]['text'], 'a citation inside a note is not a marker');

        $content = $this->edition(override: ['2:255' => ['text' => 'আয়াত [১] শেষ [২]', 'footnotes' => $raw]]);
        $log = app(TranslationImporter::class)->import(self::EDITION, $content);
        $this->assertSame(QuranImportLog::STATUS_COMPLETED, $log->status);

        $t = QuranTranslation::query()->whereHas('verse', fn ($q) => $q->where('verse_key', '2:255'))->first();
        $this->assertSame('আয়াত [১] শেষ [২]', $t->text, 'markers stay in the text');
        $this->assertSame($raw, $t->footnotes_raw, 'the published footnote field is kept exactly');
        $this->assertSame([null, '[১]', '[২]'], $t->footnotes->pluck('marker')->all());
        $this->assertSame('bn.test', $t->footnotes->first()->translation->edition->identifier);
    }

    // ---- 3. Metadata ----

    public function test_attribution_metadata_is_required_and_stored(): void
    {
        foreach (['license', 'attribution_text'] as $field) {
            try {
                app(TranslationImporter::class)->import([$field => ''] + self::EDITION, $this->edition());
                $this->fail("{$field} should be required");
            } catch (\InvalidArgumentException) {
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        app(TranslationImporter::class)->import(['import_source' => 'quranenc', 'version' => null] + self::EDITION, $this->edition());
    }

    // ---- 4. History and rollback ----

    public function test_reimport_keeps_every_earlier_revision_and_rollback_restores_it_exactly(): void
    {
        $importer = app(TranslationImporter::class);
        $importer->import(self::EDITION, $this->edition('প্রথম'));
        $first = $this->storedChecksum();

        $again = $importer->import(self::EDITION, $this->edition('প্রথম'));
        $this->assertTrue($again->options['unchanged']);
        $this->assertSame(1, QuranTranslationRevision::count(), 'identical content creates no revision');

        $importer->import(self::EDITION, $this->edition('দ্বিতীয়'));
        $edition = Edition::where('identifier', 'bn.test')->first();
        $this->assertSame(2, $edition->current_revision);
        $this->assertNotSame($first, $this->storedChecksum());
        $this->assertSame($first, QuranTranslationRevision::where('revision', 1)->value('checksum'), 'revision 1 still holds the first content');

        $log = app(TranslationHistory::class)->rollback($edition, 1, $this->reviewer());
        $this->assertSame(QuranImportLog::STATUS_COMPLETED, $log->status);
        $this->assertSame($first, $this->storedChecksum(), 'rollback restores the exact earlier content');
        $edition->refresh();
        $this->assertSame(3, $edition->current_revision);
        $this->assertSame('rollback', QuranTranslationRevision::where('revision', 3)->value('kind'));
        $this->assertSame(Edition::STATUS_UNDER_REVIEW, $edition->review_status);
        $this->assertSame(QuranStructure::VERSES, QuranTranslation::count(), 'no translation row is lost');
    }

    public function test_content_found_before_history_existed_is_saved_as_a_baseline(): void
    {
        $edition = Edition::create(['identifier' => 'bn.test', 'language' => 'bn', 'name' => 'Legacy', 'license' => 'x', 'is_active' => true]);
        $rows = [];
        foreach (DB::table('quran_verses')->pluck('id') as $id) {
            $rows[] = ['quran_verse_id' => $id, 'quran_translation_edition_id' => $edition->id, 'text' => "পুরোনো {$id}", 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 500) as $c) {
            DB::table('quran_translations')->insert($c);
        }
        $legacy = $this->storedChecksum();

        app(TranslationImporter::class)->import(self::EDITION, $this->edition('নতুন'));

        $this->assertSame('baseline', QuranTranslationRevision::where('revision', 1)->value('kind'));
        $this->assertSame($legacy, QuranTranslationRevision::where('revision', 1)->value('checksum'));
        app(TranslationHistory::class)->rollback($edition->refresh(), 1, $this->reviewer());
        $this->assertSame($legacy, $this->storedChecksum());
    }

    public function test_a_failed_import_leaves_the_stored_edition_unchanged(): void
    {
        app(TranslationImporter::class)->import(self::EDITION, $this->edition('প্রথম'));
        $before = $this->storedChecksum();

        $failing = new class(app(TranslationTextInspector::class), app(TranslationHistory::class)) extends TranslationImporter
        {
            protected function verifyStored(int $editionId, string $expected): void
            {
                throw new RuntimeException('simulated failure during write');
            }
        };
        try {
            $failing->import(self::EDITION, $this->edition('দ্বিতীয়'));
            $this->fail('the import should have failed');
        } catch (RuntimeException) {
        }

        $this->assertSame($before, $this->storedChecksum());
        $this->assertSame(1, QuranTranslationRevision::count(), 'the failed revision was rolled back too');
        $this->assertSame(QuranImportLog::STATUS_FAILED, QuranImportLog::latest('id')->first()->status);
    }

    public function test_a_blocking_error_writes_nothing_and_names_the_verse(): void
    {
        $log = app(TranslationImporter::class)->import(self::EDITION, $this->edition(override: ['12:76' => ['text' => 'কিু栦#2438;ল্লাহ', 'footnotes' => null]]));

        $this->assertSame(QuranImportLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('12:76', json_encode($log->error_details, JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, Edition::count());
        $this->assertSame(0, QuranTranslation::count());
    }

    // ---- 6. Review workflow ----

    public function test_acknowledged_errors_are_imported_as_flags_that_block_approval_until_resolved(): void
    {
        $bad = ['9:24' => ['text' => 'যা তোমরf ভালবাস', 'footnotes' => null]];
        $log = app(TranslationImporter::class)->import(self::EDITION, $this->edition(override: $bad), acknowledged: ['9:24']);
        $this->assertSame(QuranImportLog::STATUS_COMPLETED, $log->status);
        $this->assertSame('যা তোমরf ভালবাস', QuranTranslation::whereHas('verse', fn ($q) => $q->where('verse_key', '9:24'))->value('text'), 'never repaired');

        $edition = Edition::where('identifier', 'bn.test')->first();
        $this->assertSame(Edition::STATUS_IMPORTED, $edition->review_status);
        $this->assertFalse($edition->is_active);
        $workflow = app(TranslationReviewWorkflow::class);
        $reviewer = $this->reviewer();
        $workflow->transition($edition, Edition::STATUS_UNDER_REVIEW, $reviewer);

        try {
            $workflow->transition($edition, Edition::STATUS_APPROVED, $reviewer, 'Looks good');
            $this->fail('approval must wait for the flag');
        } catch (DomainException $e) {
            $this->assertStringContainsString('unresolved flags', $e->getMessage());
        }
        $flags = QuranTranslationFlag::query()->where('quran_verse_id', DB::table('quran_verses')->where('verse_key', '9:24')->value('id'))->get();
        $this->assertContains('error', $flags->pluck('severity')->all(), 'the acknowledged error is kept as a flag');
        $workflow->resolveFlags($edition, $flags->pluck('id')->all(), $reviewer, 'Source typo; reported to the publisher.');
        $workflow->transition($edition, Edition::STATUS_APPROVED, $reviewer, 'Checked against the printed edition.');
        $this->assertSame(Edition::STATUS_APPROVED, $edition->refresh()->review_status);
        $this->assertFalse($edition->is_active, 'approval does not switch it on by itself');
        $workflow->activate($edition, $reviewer);
        $this->assertTrue($edition->refresh()->is_active);
        $this->assertSame([Edition::STATUS_UNDER_REVIEW, Edition::STATUS_APPROVED, Edition::STATUS_APPROVED], $edition->reviews()->pluck('to_status')->all());
        $this->assertSame($reviewer->id, $edition->reviews()->first()->reviewer_id);
    }

    public function test_review_rules(): void
    {
        app(TranslationImporter::class)->import(self::EDITION, $this->edition());
        $edition = Edition::where('identifier', 'bn.test')->first();
        $workflow = app(TranslationReviewWorkflow::class);
        $reviewer = $this->reviewer();

        $this->assertThrows(fn () => $workflow->transition($edition, Edition::STATUS_APPROVED, $reviewer, 'skip'), DomainException::class);
        $this->assertThrows(fn () => $workflow->transition($edition, Edition::STATUS_UNDER_REVIEW, User::factory()->create()), DomainException::class);
        $this->assertThrows(fn () => $workflow->activate($edition, $reviewer), DomainException::class);
        $workflow->transition($edition, Edition::STATUS_UNDER_REVIEW, $reviewer);
        $this->assertThrows(fn () => $workflow->transition($edition, Edition::STATUS_APPROVED, $reviewer, ''), DomainException::class);

        $workflow->transition($edition, Edition::STATUS_APPROVED, $reviewer, 'ok');
        $workflow->activate($edition, $reviewer);
        app(TranslationImporter::class)->import(self::EDITION, $this->edition('নতুন'));
        $edition->refresh();
        $this->assertSame(Edition::STATUS_IMPORTED, $edition->review_status, 'new content needs a new review');
        $this->assertFalse($edition->is_active, 'and is hidden until then');
    }

    public function test_only_approved_editions_reach_ai_retrieval_and_lookup(): void
    {
        app(TranslationImporter::class)->import(self::EDITION, $this->edition(override: ['2:153' => ['text' => 'ধৈর্য ও নামাযের মাধ্যমে সাহায্য', 'footnotes' => null]]));
        $source = IslamicSource::create(['title' => 'Test', 'source_type' => 'quran', 'reference_identifier' => 'edition:bn.test', 'language' => 'bn']);
        $source->chunks()->create(['chunk_index' => 0, 'reference' => 'Quran 2:153', 'content' => 'ধৈর্য ও নামাযের মাধ্যমে সাহায্য']);
        $source->forceFill(['verification_status' => 'approved'])->save();

        $this->assertCount(0, app(SourceRetriever::class)->search('ধৈর্য'), 'an approved source is still held back while its edition is unreviewed');
        $this->assertCount(0, app(QuranLookup::class)->searchTranslation('ধৈর্য', 'bn.test'));

        $edition = Edition::where('identifier', 'bn.test')->first();
        $workflow = app(TranslationReviewWorkflow::class);
        $reviewer = $this->reviewer();
        $workflow->transition($edition, Edition::STATUS_UNDER_REVIEW, $reviewer);
        $workflow->transition($edition, Edition::STATUS_APPROVED, $reviewer, 'ok');

        $this->assertCount(1, app(SourceRetriever::class)->search('ধৈর্য'));
        $this->assertSame('2:153', app(QuranLookup::class)->searchTranslation('ধৈর্য', 'bn.test')->first()['reference']);
    }

    // ---- 5. QuranEnc reader and command ----

    private function quranEncFolder(?array $meta, array $override = []): string
    {
        $dir = sys_get_temp_dir().'/quranenc-test-'.uniqid();
        mkdir($dir);
        if ($meta) {
            file_put_contents("{$dir}/translations.json", json_encode(['translations' => [$meta]]));
        }
        foreach (QuranStructure::VERSE_COUNTS as $i => $count) {
            $s = $i + 1;
            $result = [];
            for ($v = 1; $v <= $count; $v++) {
                $result[] = $override["{$s}:{$v}"] ?? ['id' => "{$s}{$v}", 'sura' => (string) $s, 'aya' => (string) $v, 'arabic_text' => 'نص', 'translation' => "Translation {$s} {$v}", 'footnotes' => ''];
            }
            file_put_contents(sprintf('%s/sura-%03d.json', $dir, $s), json_encode(['result' => $result], JSON_UNESCAPED_UNICODE));
        }

        return $dir;
    }

    public function test_quranenc_dry_run_validates_everything_and_writes_no_translations(): void
    {
        config(['deenmate.quran.quranenc.editions.english_rwwad.licence_confirmed' => null]);
        $meta = ['key' => 'english_rwwad', 'language_iso_code' => 'en', 'version' => '1.0.21', 'last_update' => 1794213356, 'title' => 'English Translation - Rowwad Translation Center', 'description' => 'Translated by the team of the Rowwad Translation Center.'];
        $dir = $this->quranEncFolder($meta, ['1:1' => ['sura' => '1', 'aya' => '1', 'translation' => 'In the name of Allah [1]', 'footnotes' => '[1] A note.']]);

        $this->artisan('quran:import-translation', ['key' => 'english_rwwad', '--source-dir' => $dir])
            ->expectsOutputToContain('licence is not confirmed')->assertFailed();
        $this->artisan('quran:import-translation', ['key' => 'english_rwwad', '--source-dir' => $dir, '--dry-run' => true])
            ->expectsOutputToContain('version 1.0.21')->expectsOutputToContain('Dry run (nothing written): 6236 new')->assertSuccessful();
        $this->assertSame(0, Edition::count());
        $this->assertSame(0, QuranTranslation::count());

        config(['deenmate.quran.quranenc.editions.english_rwwad.licence_confirmed' => 'Test permission letter']);
        $this->artisan('quran:import-translation', ['key' => 'english_rwwad', '--source-dir' => $dir])->assertSuccessful();
        $edition = Edition::where('identifier', 'en.rwwad')->first();
        $this->assertSame('1.0.21', $edition->version);
        $this->assertSame('QuranEnc.com', $edition->publisher);
        $this->assertSame('quranenc', $edition->import_source);
        $this->assertStringContainsString('version 1.0.21', $edition->attribution_text);
        $this->assertSame(Edition::STATUS_IMPORTED, $edition->review_status);
        $this->assertFalse($edition->is_active);
        $this->assertSame('[1] A note.', QuranTranslation::whereHas('verse', fn ($q) => $q->where('verse_key', '1:1'))->value('footnotes_raw'));
    }

    public function test_quranenc_edition_without_listed_version_is_refused(): void
    {
        $dir = $this->quranEncFolder(null);
        $this->artisan('quran:import-translation', ['key' => 'bengali_zakaria', '--source-dir' => $dir, '--dry-run' => true])
            ->expectsOutputToContain('version cannot be verified')->assertFailed();
    }
}
