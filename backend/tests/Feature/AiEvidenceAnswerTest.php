<?php

namespace Tests\Feature;

use App\Models\IslamicSource;
use App\Models\User;
use App\Services\AI\Contracts\LlmProvider;
use App\Services\AI\LlmException;
use App\Services\AI\LlmResponse;
use App\Services\AI\Providers\UnconfiguredProvider;
use App\Services\AI\SourceRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EvidenceFakeProvider implements LlmProvider
{
    public array $calls = [];

    public ?LlmException $throw = null;

    public function __construct(public array $reply = []) {}

    public function name(): string
    {
        return 'fake';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function complete(string $system, array $messages, array $jsonSchema): LlmResponse
    {
        $this->calls[] = compact('system', 'messages');
        if ($this->throw) {
            throw $this->throw;
        }

        return new LlmResponse(json_encode($this->reply), 10, 20, 'fake-model');
    }
}

/** Placeholder passages only: no real Quran translation, Tafsir or Hadith text is stored in tests. */
class AiEvidenceAnswerTest extends TestCase
{
    use RefreshDatabase;

    private const PRAYER_TIMES = 'Placeholder: prayer is prescribed for the believers at set times.';

    protected function setUp(): void
    {
        parent::setUp();
        $now = now();
        DB::table('quran_surahs')->insert([
            ['id' => 2, 'name_arabic' => 'البقرة', 'name_transliterated' => 'Al-Baqara', 'name_english' => 'The Cow', 'revelation_place' => 'medinan', 'verse_count' => 286, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name_arabic' => 'النساء', 'name_transliterated' => 'An-Nisaa', 'name_english' => 'The Women', 'revelation_place' => 'medinan', 'verse_count' => 176, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('quran_verses')->insert([
            ['surah_id' => 4, 'verse_number' => 103, 'verse_key' => '4:103', 'text_arabic' => 'نص ٤ ١٠٣', 'text_source' => 'test', 'created_at' => $now, 'updated_at' => $now],
            ['surah_id' => 2, 'verse_number' => 255, 'verse_key' => '2:255', 'text_arabic' => 'نص ٢ ٢٥٥', 'text_source' => 'test', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /** @param array<string, string> $chunks reference => text */
    private function source(array $chunks, string $type = 'quran', string $language = 'en', string $status = 'approved', array $extra = []): IslamicSource
    {
        $source = IslamicSource::create($extra + [
            'title' => "Test {$type} {$language}", 'source_type' => $type, 'reference_identifier' => "{$type}:".uniqid(),
            'language' => $language, 'author' => 'Test Translator', 'version' => '1.0',
            'attribution' => 'Translation by Test Translator, example.org', 'license_url' => 'https://example.org/licence',
        ]);
        $i = 0;
        foreach ($chunks as $reference => $text) {
            $row = is_array($text) ? $text : ['content' => $text];
            $source->chunks()->create($row + ['chunk_index' => $i++, 'reference' => $reference]);
        }
        $source->forceFill(['verification_status' => $status])->save();

        return $source;
    }

    private function useProvider(array $overrides = []): EvidenceFakeProvider
    {
        $fake = new EvidenceFakeProvider($overrides + [
            'answer' => 'নামাজ নির্দিষ্ট সময়ে পড়া ফরজ [S1]।',
            'cited_source_ids' => ['S1'],
            'scholarly_differences' => '',
            'needs_scholar_referral' => false,
            'insufficient_sources' => false,
        ]);
        $this->app->instance(LlmProvider::class, $fake);

        return $fake;
    }

    private function ask(string $message, ?string $conversation = null)
    {
        return $this->postJson('/api/v1/ai/chat', ['message' => $message, 'conversation_id' => $conversation])->assertOk();
    }

    public function test_banglish_question_returns_a_structured_bangla_answer_with_exact_english_evidence(): void
    {
        $this->source(['Quran 4:103' => self::PRAYER_TIMES]);
        $fake = $this->useProvider();
        $this->actingUser();

        $response = $this->ask('namaz koy wakt?')
            ->assertJsonPath('data.status', 'answered')
            ->assertJsonPath('data.question.original', 'namaz koy wakt?')
            ->assertJsonPath('data.question.language', 'banglish')
            ->assertJsonPath('data.question.intent', 'salah')
            ->assertJsonPath('data.answer.language', 'bn')
            ->assertJsonPath('data.answer.text', 'নামাজ নির্দিষ্ট সময়ে পড়া ফরজ [S1]।')
            ->assertJsonPath('data.evidence.0.type', 'quran')
            ->assertJsonPath('data.evidence.0.surah_number', 4)
            ->assertJsonPath('data.evidence.0.surah_name', 'An-Nisaa')
            ->assertJsonPath('data.evidence.0.ayah_number', 103)
            ->assertJsonPath('data.evidence.0.arabic', 'نص ٤ ١٠٣')
            ->assertJsonPath('data.evidence.0.translation', self::PRAYER_TIMES)
            ->assertJsonPath('data.evidence.0.translator', 'Test Translator')
            ->assertJsonPath('data.evidence.0.version', '1.0')
            ->assertJsonPath('data.evidence.0.attribution', 'Translation by Test Translator, example.org')
            ->assertJsonPath('data.evidence.0.checksum', hash('sha256', self::PRAYER_TIMES))
            ->assertJsonPath('data.limitations.0.code', 'translation_language');

        $this->assertGreaterThan(0.5, $response->json('data.confidence'));
        // The explanation and the quotation are kept apart: the model text is not the translation.
        $this->assertStringNotContainsString(self::PRAYER_TIMES, $response->json('data.answer.text'));
        // The Bangla limitation message is used for a Bangla answer.
        $this->assertStringContainsString('বাংলা অনুবাদ', $response->json('data.limitations.0.message'));
        $turn = end($fake->calls[0]['messages'])['content'];
        $this->assertStringContainsString('Write answer and scholarly_differences in Bangla', $turn);
        $this->assertStringContainsString('found_by="topic"', $turn);
    }

    public function test_an_exact_quotation_is_accepted(): void
    {
        $this->source(['Quran 4:103' => self::PRAYER_TIMES]);
        $this->useProvider(['answer' => 'The verse says “prayer is prescribed for the believers at set times” [S1].']);
        $this->actingUser();

        $this->ask('What are the prayer times in the Quran?')->assertJsonPath('data.status', 'answered');
    }

    public function test_a_misquotation_or_a_made_up_translation_in_quotes_is_rejected(): void
    {
        $this->source(['Quran 4:103' => self::PRAYER_TIMES]);
        $this->actingUser();

        $this->useProvider(['answer' => 'The verse says "prayer is required of every believer at all hours" [S1].']);
        $this->ask('What are the prayer times in the Quran?')->assertJsonPath('data.status', 'insufficient_sources')->assertJsonPath('data.evidence', []);

        // A Bangla rendering in quotation marks would read as a published translation.
        $this->useProvider(['answer' => 'আয়াতে বলা হয়েছে "নির্দিষ্ট সময়ে নামাজ মুমিনদের ওপর ফরজ করা হয়েছে" [S1]।']);
        $this->ask('namaz koy wakt?')->assertJsonPath('data.status', 'insufficient_sources');
    }

    public function test_an_answer_in_the_language_of_an_approved_translation_has_no_language_limitation(): void
    {
        $this->source(['Quran 4:103' => self::PRAYER_TIMES]);
        $this->source(['Quran 4:103' => 'স্থানধারক: নির্দিষ্ট সময়ে নামাজ।'], language: 'bn');
        $this->useProvider();
        $this->actingUser();

        $response = $this->ask('namaz koy wakt?')->assertJsonPath('data.status', 'answered');

        // One passage per verse, from the edition in the answer's language.
        $this->assertSame('bn', $response->json('data.evidence.0.language'));
        $this->assertSame(['Quran 4:103'], collect($response->json('data.evidence'))->pluck('reference')->all());
        $this->assertNotContains('translation_language', collect($response->json('data.limitations'))->pluck('code')->all());
    }

    public function test_without_a_provider_recognised_verses_are_still_shown_as_matches_not_as_an_answer(): void
    {
        $this->app->instance(LlmProvider::class, new UnconfiguredProvider);
        $this->source(['Quran 4:103' => self::PRAYER_TIMES, 'Quran 2:255' => 'Placeholder words about believers and prayer.']);
        $this->actingUser();

        $response = $this->ask('namaz koy wakt?')
            ->assertJsonPath('data.status', 'not_configured')
            ->assertJsonPath('data.evidence.0.reference', 'Quran 4:103')
            ->assertJsonPath('data.limitations.0.code', 'ai_unavailable');
        $this->assertStringContainsString(self::PRAYER_TIMES, $response->json('data.content'));
        $this->assertLessThanOrEqual(0.5, $response->json('data.confidence'));
        // Passages that only share words with the question are not shown without a model.
        $this->assertNotContains('Quran 2:255', collect($response->json('data.evidence'))->pluck('reference')->all());

        $this->ask('tell me something about believers')->assertJsonPath('data.status', 'not_configured')->assertJsonPath('data.evidence', []);
    }

    public function test_provider_failure_still_shows_recognised_verses(): void
    {
        $this->source(['Quran 2:255' => 'Placeholder text of the verse.']);
        $this->useProvider()->throw = new LlmException('down', true);
        $this->actingUser();

        $this->ask('2:255')->assertJsonPath('data.status', 'provider_error')->assertJsonPath('data.evidence.0.reference', 'Quran 2:255');
    }

    public function test_tafsir_and_hadith_are_kept_separate_with_their_own_citation_fields(): void
    {
        $this->source(['Quran 2:255' => 'Placeholder text of the verse.']);
        $this->source(['Tafsir 2:254-256' => ['content' => 'Placeholder commentary on these verses.', 'surah_number' => 2, 'ayah_from' => 254, 'ayah_to' => 256]], type: 'tafsir');
        $this->source(['Test Collection 7' => ['content' => 'Placeholder narration about the verse of the throne.', 'citation' => ['collection' => 'Test Collection', 'book' => 'Book of Tests', 'hadith_number' => '7', 'grade' => 'Sahih', 'grade_source' => 'Test grader']]], type: 'hadith');
        $this->useProvider(['answer' => 'The verse [S1], a commentary [S2] and a narration [S3].', 'cited_source_ids' => ['S1', 'S2', 'S3']]);
        $this->actingUser();

        $evidence = collect($this->ask('2:255 er tafsir ar hadith ki? verse throne')->assertJsonPath('data.status', 'answered')->json('data.evidence'))->keyBy('type');

        $this->assertSame(255, $evidence['quran']['ayah_number']);
        $this->assertSame([2, 254, 256], [$evidence['tafsir']['surah_number'], $evidence['tafsir']['ayah_from'], $evidence['tafsir']['ayah_to']]);
        $this->assertSame('tafsir_for_verse', $evidence['tafsir']['match']);
        $this->assertSame(['Test Collection', 'Book of Tests', '7', 'Sahih', 'Test grader'], [
            $evidence['hadith']['collection'], $evidence['hadith']['book'], $evidence['hadith']['hadith_number'], $evidence['hadith']['grade'], $evidence['hadith']['grade_source'],
        ]);
        $this->assertArrayNotHasKey('translation', $evidence['hadith']);
    }

    public function test_unapproved_tafsir_and_hadith_never_appear(): void
    {
        $this->source(['Quran 2:255' => 'Placeholder text of the verse.']);
        $this->source(['Tafsir 2:255' => ['content' => 'Unreviewed commentary.', 'surah_number' => 2, 'ayah_from' => 255, 'ayah_to' => 255]], type: 'tafsir', status: 'pending');
        $this->source(['Collection 1' => 'Unreviewed narration about the verse.'], type: 'hadith', status: 'rejected');

        $found = app(SourceRetriever::class)->search('2:255 er tafsir ar hadith ki? verse narration');

        $this->assertSame(['quran'], $found->map->type()->unique()->values()->all());
    }

    public function test_tafsir_question_without_approved_tafsir_says_so(): void
    {
        $this->source(['Quran 2:255' => 'Placeholder text of the verse.']);
        $this->useProvider(['answer' => 'This verse describes Allah [S1].']);
        $this->actingUser();

        $codes = collect($this->ask('2:255 er tafsir ki?')->json('data.limitations'))->pluck('code')->all();

        $this->assertContains('no_approved_tafsir', $codes);
    }

    public function test_follow_up_question_uses_the_previous_verse_and_says_so(): void
    {
        $this->source(['Quran 2:255' => 'Placeholder text of the verse.']);
        $fake = $this->useProvider(['answer' => 'This verse describes Allah [S1].']);
        $this->actingUser();

        $id = $this->ask('2:255 er mane ki?')->json('conversation_id');
        $second = $this->ask('eta aro bujhiye dao', $id)->assertJsonPath('data.status', 'answered');

        $this->assertSame('Quran 2:255', $second->json('data.evidence.0.reference'));
        $this->assertContains('context_from_conversation', collect($second->json('data.limitations'))->pluck('code')->all());
        $this->assertStringContainsString('Earlier turns in this conversation are context, not evidence', end($fake->calls[1]['messages'])['content']);
    }

    public function test_ruling_question_is_marked_as_not_a_fatwa(): void
    {
        $this->source(['Quran 2:183' => 'Placeholder text about fasting.']);
        $this->useProvider(['answer' => 'Fasting is described here [S1].']);
        $this->actingUser();

        $response = $this->ask('roja vangar karon ki?')->assertJsonPath('data.question.intent', 'fasting');

        $this->assertContains('not_a_fatwa', collect($response->json('data.limitations'))->pluck('code')->all());
    }

    public function test_responses_never_expose_keys_reviewers_prompts_or_internal_ids(): void
    {
        config(['deenmate.ai.api_key' => 'sk-test-secret-key']);
        $reviewer = User::factory()->create(['email' => 'reviewer-private@example.org']);
        $this->source(['Quran 4:103' => self::PRAYER_TIMES], extra: ['reviewer_id' => $reviewer->id, 'review_notes' => 'Private reviewer note']);
        $this->useProvider();
        $this->actingUser();

        $json = $this->ask('namaz koy wakt?')->getContent();

        foreach (['sk-test-secret-key', 'reviewer-private@example.org', 'Private reviewer note', 'reviewer_id', 'islamic_source_id', 'You are the learning assistant'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }

    public function test_a_user_cannot_read_or_continue_another_users_conversation(): void
    {
        $this->source(['Quran 4:103' => self::PRAYER_TIMES]);
        $this->useProvider();
        $this->actingUser();
        $id = $this->ask('namaz koy wakt?')->json('conversation_id');

        $this->actingUser();
        $this->getJson("/api/v1/ai/conversations/{$id}")->assertNotFound();
        $this->postJson('/api/v1/ai/chat', ['message' => 'eta aro bujhiye dao', 'conversation_id' => $id])->assertNotFound();
    }

    public function test_conversation_history_returns_the_structured_parts(): void
    {
        $this->source(['Quran 4:103' => self::PRAYER_TIMES]);
        $this->useProvider();
        $this->actingUser();
        $id = $this->ask('namaz koy wakt?')->json('conversation_id');

        $this->getJson("/api/v1/ai/conversations/{$id}")
            ->assertJsonPath('data.messages.0.role', 'user')
            ->assertJsonPath('data.messages.0.evidence', [])
            ->assertJsonPath('data.messages.1.evidence.0.reference', 'Quran 4:103')
            ->assertJsonPath('data.messages.1.answer.language', 'bn');
    }
}
