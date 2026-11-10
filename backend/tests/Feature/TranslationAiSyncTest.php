<?php

namespace Tests\Feature;

use App\Models\IslamicSource;
use App\Models\QuranTranslation;
use App\Models\QuranTranslationEdition as Edition;
use App\Models\QuranTranslationFlag;
use App\Models\User;
use App\Services\AI\Contracts\LlmProvider;
use App\Services\AI\EvidenceBuilder;
use App\Services\AI\SourceRetriever;
use App\Services\Quran\QuranLookup;
use App\Services\Quran\QuranStructure;
use App\Services\Quran\TranslationImporter;
use App\Services\Quran\Translations\TranslationAiSync;
use App\Services\Quran\Translations\TranslationReviewWorkflow;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/** Placeholder text with Clear Quran's metadata; no real translation is stored in the tests. */
class TranslationAiSyncTest extends TestCase
{
    use RefreshDatabase;

    private const EDITION = [
        'identifier' => 'en.clearquran',
        'language' => 'en',
        'name' => 'Clear Quran (Allah Edition), Translation by Talal Itani, ClearQuran.com',
        'translator' => 'Talal Itani',
        'publisher' => 'ClearQuran.com',
        'copyright_holder' => 'Talal Itani',
        'attribution_text' => 'Translation by Talal Itani, ClearQuran.com (https://clearquran.com).',
        'source_url' => 'https://clearquran.com/download/',
        'license' => 'CC BY-ND 4.0, plus the copyright holder\'s permission to store and distribute unchanged with credit.',
        'license_url' => 'https://creativecommons.org/licenses/by-nd/4.0/',
        'version' => '2026-10-09',
        'import_source' => 'clearquran',
    ];

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
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
        $this->reviewer = User::factory()->create();
        $this->reviewer->forceFill(['admin_role' => User::ROLE_SCHOLAR_REVIEWER])->save();
    }

    private function import(string $word = 'Placeholder', array $override = []): Edition
    {
        $verses = [];
        foreach (QuranStructure::VERSE_COUNTS as $i => $count) {
            for ($v = 1; $v <= $count; $v++) {
                $verses[($i + 1).":{$v}"] = "{$word} “text” ".($i + 1)." {$v}—end.";
            }
        }
        app(TranslationImporter::class)->import(self::EDITION, array_replace($verses, $override));

        return Edition::where('identifier', 'en.clearquran')->firstOrFail();
    }

    private function approve(Edition $edition): Edition
    {
        $workflow = app(TranslationReviewWorkflow::class);
        if ($edition->review_status !== Edition::STATUS_UNDER_REVIEW) {
            $workflow->transition($edition, Edition::STATUS_UNDER_REVIEW, $this->reviewer);
        }
        $workflow->transition($edition, Edition::STATUS_APPROVED, $this->reviewer, 'Checked against the official download.');

        return $edition->refresh();
    }

    private function source(): ?IslamicSource
    {
        return IslamicSource::where('reference_identifier', 'edition:en.clearquran')->first();
    }

    private function search(string $q = 'Placeholder'): int
    {
        return app(SourceRetriever::class)->search($q)->count();
    }

    public function test_approval_creates_one_source_with_every_verse_exactly_and_its_attribution(): void
    {
        $edition = $this->approve($this->import(override: ['2:255' => 'Allah—there is no god except He, the Living, the Everlasting.']));

        $this->assertSame(TranslationAiSync::STATUS_SYNCED, $edition->ai_sync_status);
        $this->assertSame($edition->content_checksum, $edition->ai_synced_checksum);
        $source = $this->source();
        $this->assertSame('approved', $source->verification_status);
        $this->assertSame($edition->islamic_source_id, $source->id);
        $this->assertSame('Clear Quran (Allah Edition), Translation by Talal Itani, ClearQuran.com, version 2026-10-09', $source->title);
        $this->assertSame('Talal Itani', $source->author);
        $this->assertSame('ClearQuran.com', $source->publisher);
        $this->assertSame('https://clearquran.com/download/', $source->url);
        $this->assertSame('https://creativecommons.org/licenses/by-nd/4.0/', $source->license);

        $chunks = $source->chunks()->orderBy('chunk_index')->get(['reference', 'content']);
        $this->assertCount(QuranStructure::VERSES, $chunks);
        $stored = QuranTranslation::query()->join('quran_verses as v', 'v.id', '=', 'quran_translations.quran_verse_id')
            ->where('quran_translation_edition_id', $edition->id)->pluck('quran_translations.text', 'v.verse_key');
        foreach ($chunks as $c) {
            $this->assertSame($stored[substr($c->reference, 6)], $c->content, $c->reference);
        }
        $this->assertSame('Quran 2:255', $chunks[262 - 1]->reference);
        $this->assertSame('Allah—there is no god except He, the Living, the Everlasting.', $chunks[262 - 1]->content);
        $this->assertGreaterThan(0, $this->search('Everlasting'));
    }

    public function test_repeated_approval_and_resync_never_duplicate_records(): void
    {
        $edition = $this->approve($this->import());
        app(TranslationAiSync::class)->sync($edition);
        app(TranslationAiSync::class)->sync($edition);
        $workflow = app(TranslationReviewWorkflow::class);
        $workflow->transition($edition, Edition::STATUS_UNDER_REVIEW, $this->reviewer);
        $this->approve($edition->refresh());

        $this->assertSame(1, IslamicSource::where('reference_identifier', 'edition:en.clearquran')->count());
        $this->assertSame(QuranStructure::VERSES, $this->source()->chunks()->count());
    }

    public function test_revoking_disables_at_once_and_reapproval_restores(): void
    {
        $edition = $this->approve($this->import());
        $this->assertGreaterThan(0, $this->search());

        foreach ([Edition::STATUS_INACTIVE => 'pending', Edition::STATUS_REJECTED => 'rejected'] as $status => $sourceStatus) {
            $edition = $this->approve($edition->refresh());
            $workflow = app(TranslationReviewWorkflow::class);
            $status === Edition::STATUS_REJECTED
                ? ($workflow->transition($edition, Edition::STATUS_UNDER_REVIEW, $this->reviewer) && $workflow->transition($edition->refresh(), Edition::STATUS_REJECTED, $this->reviewer, 'No longer suitable.'))
                : $workflow->transition($edition, Edition::STATUS_INACTIVE, $this->reviewer);
            $edition->refresh();
            $this->assertSame(TranslationAiSync::STATUS_REVOKED, $edition->ai_sync_status, $status);
            $this->assertSame($sourceStatus, $this->source()->verification_status, $status);
            $this->assertSame(0, $this->search(), "not searchable once {$status}");
            $this->assertSame(QuranStructure::VERSES, $this->source()->chunks()->count(), 'records kept for provenance');
            $workflow->transition($edition, Edition::STATUS_UNDER_REVIEW, $this->reviewer);
        }

        $this->approve($edition->refresh());
        $this->assertGreaterThan(0, $this->search(), 'reapproval restores it');
    }

    public function test_a_reimport_withdraws_the_ai_copy_until_reviewed_again(): void
    {
        $edition = $this->approve($this->import());
        $this->import('Revised');
        $edition->refresh();
        $this->assertSame(Edition::STATUS_IMPORTED, $edition->review_status);
        $this->assertSame(0, $this->search('Placeholder'));
        $this->assertSame(0, $this->search('Revised'));
    }

    public function test_unapproved_editions_are_never_searchable_even_if_their_source_is_approved_by_hand(): void
    {
        $edition = $this->import();
        $source = IslamicSource::create(['title' => 'x', 'source_type' => 'quran', 'reference_identifier' => 'edition:en.clearquran', 'language' => 'en']);
        $source->chunks()->create(['chunk_index' => 0, 'reference' => 'Quran 1:1', 'content' => 'Placeholder text']);
        $source->forceFill(['verification_status' => 'approved'])->save();

        $this->assertSame(Edition::STATUS_IMPORTED, $edition->review_status);
        $this->assertSame(0, $this->search());
        $this->assertCount(0, app(QuranLookup::class)->searchTranslation('Placeholder', 'en.clearquran'));
    }

    public function test_open_flags_block_approval_and_sync(): void
    {
        $edition = $this->import();
        QuranTranslationFlag::create(['quran_translation_edition_id' => $edition->id, 'quran_verse_id' => DB::table('quran_verses')->value('id'), 'severity' => 'warning', 'code' => 'test', 'detail' => 'check me']);
        app(TranslationReviewWorkflow::class)->transition($edition, Edition::STATUS_UNDER_REVIEW, $this->reviewer);
        $this->assertThrows(fn () => app(TranslationReviewWorkflow::class)->transition($edition->refresh(), Edition::STATUS_APPROVED, $this->reviewer, 'ok'), DomainException::class);

        // Even if the status were forced past the workflow, the sync itself refuses.
        DB::table('quran_translation_editions')->where('id', $edition->id)->update(['review_status' => 'approved', 'reviewed_by' => $this->reviewer->id, 'review_notes' => 'forced']);
        $this->assertFalse(app(TranslationAiSync::class)->sync($edition->refresh()));
        $this->assertSame(TranslationAiSync::STATUS_FAILED, $edition->refresh()->ai_sync_status);
        $this->assertStringContainsString('flags', $edition->ai_sync_error);
        $this->assertSame(0, $this->search());
    }

    public function test_a_failed_sync_exposes_nothing(): void
    {
        $edition = $this->import();
        $writes = 0;
        DB::listen(function ($query) use (&$writes) {
            if (str_contains($query->sql, 'islamic_source_chunks') && str_starts_with(strtolower(ltrim($query->sql)), 'insert') && ++$writes === 3) {
                throw new RuntimeException('simulated failure halfway through the copy');
            }
        });
        $edition = $this->approve($edition);

        $this->assertSame(TranslationAiSync::STATUS_FAILED, $edition->ai_sync_status);
        $this->assertStringContainsString('simulated failure', $edition->ai_sync_error);
        $this->assertNull($this->source(), 'the half-written copy was rolled back');
        $this->assertSame(0, $this->search());
    }

    public function test_stale_or_revoked_records_are_rejected_at_query_time(): void
    {
        $edition = $this->approve($this->import());
        $this->assertGreaterThan(0, $this->search());

        // A copy made from different content than the edition now holds.
        DB::table('quran_translation_editions')->where('id', $edition->id)->update(['ai_synced_checksum' => str_repeat('0', 64)]);
        $this->assertSame(0, $this->search(), 'stale copy');

        // Status changed behind the app's back: the source row still says approved, but the edition does not.
        DB::table('quran_translation_editions')->where('id', $edition->id)->update(['ai_synced_checksum' => $edition->content_checksum, 'review_status' => 'inactive']);
        $this->assertSame('approved', $this->source()->verification_status);
        $this->assertSame(0, $this->search(), 'revoked edition');
    }

    public function test_other_sources_are_unaffected(): void
    {
        $hadith = IslamicSource::create(['title' => 'Test hadith', 'source_type' => 'hadith', 'reference_identifier' => 'collection:test', 'language' => 'en']);
        $hadith->chunks()->create(['chunk_index' => 0, 'reference' => 'Nawawi 1', 'content' => 'Placeholder hadith wording']);
        $hadith->forceFill(['verification_status' => 'approved'])->save();
        $before = $hadith->chunks()->pluck('content')->all();

        $edition = $this->approve($this->import());
        app(TranslationReviewWorkflow::class)->transition($edition, Edition::STATUS_INACTIVE, $this->reviewer);

        $hadith->refresh();
        $this->assertSame('approved', $hadith->verification_status);
        $this->assertSame($before, $hadith->chunks()->pluck('content')->all());
        $this->assertGreaterThan(0, app(SourceRetriever::class)->search('hadith wording')->count());
    }

    public function test_ai_answers_quote_the_translation_with_its_title_and_version(): void
    {
        $this->approve($this->import(override: ['2:153' => 'O you who believe, seek help through patience and prayer.']));
        $fake = new FakeProvider([
            'answer' => 'The verse calls for patience and prayer [S1].',
            'cited_source_ids' => ['S1'],
            'scholarly_differences' => '',
            'needs_scholar_referral' => false,
            'insufficient_sources' => false,
        ]);
        $this->app->instance(LlmProvider::class, $fake);
        $this->actingUser();

        $content = $this->postJson('/api/v1/ai/chat', ['message' => 'What does the Quran say about patience and prayer?'])
            ->assertOk()->assertJsonPath('data.status', 'answered')->json('data.content');

        $this->assertStringContainsString("O you who believe, seek help through patience and prayer.\n— Translation by Talal Itani, ClearQuran.com (https://clearquran.com). Version 2026-10-09.", $content);
        $this->assertStringContainsString('never present it as a translation', $fake->calls[0]['messages'][array_key_last($fake->calls[0]['messages'])]['content']);
    }

    public function test_sync_writes_one_document_per_surah_and_structured_checksummed_verse_chunks(): void
    {
        $edition = $this->approve($this->import(override: ['2:255' => 'Allah—there is no god except He, the Living, the Everlasting.']));
        $source = $this->source();

        $this->assertSame(114, $source->documents()->count());
        $this->assertSame('2026-10-09', $source->version);
        $this->assertSame(self::EDITION['attribution_text'], $source->attribution);
        $this->assertSame(self::EDITION['license_url'], $source->license_url);
        $this->assertSame($edition->content_checksum, $source->content_checksum);

        $chunk = $source->chunks()->where('reference', 'Quran 2:255')->first();
        $this->assertSame([2, 255, 255], [$chunk->surah_number, $chunk->ayah_from, $chunk->ayah_to]);
        $this->assertSame('Allah—there is no god except He, the Living, the Everlasting.', $chunk->content);
        $this->assertSame(hash('sha256', $chunk->content), $chunk->content_checksum);
        $this->assertSame('surah:2', $chunk->document->document_key);
        $this->assertSame(0, $source->chunks()->whereNull('surah_number')->count());

        // A resync rewrites the same rows instead of adding new ones.
        app(TranslationAiSync::class)->sync($edition);
        $this->assertSame(QuranStructure::VERSES, $source->chunks()->count());
        $this->assertSame(114, $source->documents()->count());
    }

    public function test_banglish_question_finds_english_verses_and_evidence_carries_full_provenance(): void
    {
        $this->approve($this->import(override: ['4:103' => 'Prayer is prescribed for the believers at specific times.']));

        $found = app(SourceRetriever::class)->search('namaz koy wakt?');
        $references = $found->map(fn ($c) => $c->chunk->reference)->all();
        $this->assertContains('Quran 4:103', $references);

        $evidence = collect(app(EvidenceBuilder::class)->build($found))->firstWhere('reference', 'Quran 4:103');
        $this->assertSame('quran', $evidence['type']);
        $this->assertSame('topic', $evidence['match']);
        $this->assertSame(4, $evidence['surah_number']);
        $this->assertSame('S4', $evidence['surah_name']);
        $this->assertSame(103, $evidence['ayah_number']);
        $this->assertSame('نص 4 103', $evidence['arabic']);
        $this->assertSame('Prayer is prescribed for the believers at specific times.', $evidence['translation']);
        $this->assertSame('Talal Itani', $evidence['translator']);
        $this->assertSame(self::EDITION['name'], $evidence['edition']);
        $this->assertSame('2026-10-09', $evidence['version']);
        $this->assertSame(self::EDITION['attribution_text'], $evidence['attribution']);
        $this->assertSame(self::EDITION['license_url'], $evidence['license_url']);
        $this->assertSame('edition:en.clearquran', $evidence['source_key']);
        $this->assertSame(hash('sha256', $evidence['translation']), $evidence['checksum']);
        foreach (['reviewer_id', 'reviewed_by', 'review_notes', 'source_id', 'islamic_source_id'] as $private) {
            $this->assertArrayNotHasKey($private, $evidence);
        }
    }

    public function test_exact_references_in_any_form_return_that_verse_first(): void
    {
        $this->approve($this->import(override: ['2:255' => 'Allah—there is no god except He, the Living, the Everlasting.']));
        $retriever = app(SourceRetriever::class);

        foreach (['2:255', '২:২৫৫', 'ayatul kursi er mane ki?', 'surah baqarah ayat 255', 'সূরা বাকারা ২৫৫', 'What is surah 2 verse 255?'] as $question) {
            $first = $retriever->search($question)->first();
            $this->assertSame('Quran 2:255', $first?->chunk->reference, $question);
            $this->assertSame('reference', $first->match, $question);
        }
        $range = $retriever->search('2:1-3')->map(fn ($c) => $c->chunk->reference)->take(3)->all();
        $this->assertSame(['Quran 2:1', 'Quran 2:2', 'Quran 2:3'], $range);
    }

    public function test_revoking_removes_the_edition_from_the_next_question(): void
    {
        $edition = $this->approve($this->import());
        $this->assertNotEmpty(app(SourceRetriever::class)->search('2:255'));

        app(TranslationReviewWorkflow::class)->transition($edition, Edition::STATUS_INACTIVE, $this->reviewer);

        $this->assertEmpty(app(SourceRetriever::class)->search('2:255'));
        $this->assertEmpty(app(SourceRetriever::class)->eligibleSources());
    }
}
