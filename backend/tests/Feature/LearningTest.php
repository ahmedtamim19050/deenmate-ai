<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuranSurah;
use App\Models\QuranTranslationEdition;
use App\Models\QuranVerse;
use App\Services\Quran\BasmalaSeparator;
use Database\Seeders\StarterCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class LearningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
        $this->seed(StarterCurriculumSeeder::class);
    }

    public function test_guests_can_browse_courses_but_answers_stay_on_the_server(): void
    {
        $this->getJson('/api/v1/courses')->assertOk()->assertJsonCount(5, 'data');
        $lesson = Lesson::whereHas('quiz')->first();

        $response = $this->getJson("/api/v1/lessons/{$lesson->id}")->assertOk();
        $this->assertStringNotContainsString('correct_index', $response->getContent());
        $this->assertSame('pending', $response->json('data.review_status'));
    }

    public function test_seeded_lessons_are_not_presented_as_reviewed(): void
    {
        $this->assertSame(0, Lesson::where('review_status', '!=', 'pending')->count());
        $this->assertSame(0, Course::where('review_status', '!=', 'pending')->count());
    }

    public function test_unpublished_content_is_hidden(): void
    {
        $lesson = Lesson::first();
        $lesson->update(['is_published' => false]);
        $this->getJson("/api/v1/lessons/{$lesson->id}")->assertNotFound();
    }

    public function test_lesson_completion_awards_once(): void
    {
        $user = $this->actingUser();
        $lesson = Lesson::first();
        $body = ['local_date' => now()->toDateString()];

        $this->postJson("/api/v1/lessons/{$lesson->id}/complete", $body)->assertOk()->assertJsonPath('points_awarded', 3)->assertJsonPath('duplicate', false);
        $this->postJson("/api/v1/lessons/{$lesson->id}/complete", $body)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame(3, (int) $user->pointBalance()->value('balance'));
        $this->assertSame(1, $user->userAchievements()->count());
    }

    public function test_quiz_scored_on_server_and_pass_awards_once(): void
    {
        $user = $this->actingUser();
        $quiz = Lesson::whereHas('quiz')->first()->quiz;
        $question = $quiz->questions()->first();
        $wrong = ($question->correct_index + 1) % count($question->options);
        $submit = fn (int $answer, ?string $key = null) => $this->postJson("/api/v1/quizzes/{$quiz->id}/attempts", [
            'idempotency_key' => $key ?? (string) Str::uuid(),
            'local_date' => now()->toDateString(),
            'answers' => [$question->id => $answer],
        ]);

        $submit($wrong)->assertCreated()->assertJsonPath('data.passed', false);
        $this->assertSame(0, (int) $user->pointBalance()->value('balance'));

        $submit($question->correct_index)->assertCreated()->assertJsonPath('data.passed', true);
        $submit($question->correct_index)->assertCreated();
        $this->assertSame(2, (int) $user->pointBalance()->value('balance'));
    }

    public function test_quran_endpoints_return_only_imported_text_with_source(): void
    {
        $surah = QuranSurah::create(['id' => 1, 'name_arabic' => 'x', 'name_transliterated' => 'Al-Fatihah', 'name_english' => 'The Opening', 'revelation_place' => 'meccan', 'verse_count' => 1]);
        $verse = QuranVerse::create(['surah_id' => 1, 'verse_number' => 1, 'text_arabic' => 'arabic', 'text_source' => 'test-source']);
        $edition = QuranTranslationEdition::create(['identifier' => 'en.test', 'language' => 'en', 'name' => 'Test', 'license' => 'test']);
        $verse->translations()->create(['quran_translation_edition_id' => $edition->id, 'text' => 'translated']);

        $this->getJson('/api/v1/quran/surahs')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/quran/surahs/1?edition=en.test')->assertOk()
            ->assertJsonPath('data.verses.0.translation', 'translated')
            ->assertJsonPath('data.verses.0.text_source', 'test-source')
            ->assertJsonPath('data.edition.identifier', 'en.test');
        $this->getJson('/api/v1/quran/search?q=transl')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_quran_import_refuses_an_incomplete_dataset(): void
    {
        Http::fake(['*' => Http::response(['data' => [
            'edition' => ['identifier' => 'x', 'name' => 'x'],
            'surahs' => [['number' => 1, 'name' => 'x', 'englishName' => 'x', 'englishNameTranslation' => 'x', 'revelationType' => 'Meccan', 'ayahs' => [['numberInSurah' => 1, 'text' => 'x']]]],
        ]])]);

        try {
            $this->artisan('quran:import');
            $this->fail('import should refuse');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Integrity check failed', $e->getMessage());
        }
        $this->assertSame(0, QuranVerse::count());
    }

    public function test_basmala_is_separated_from_first_verse_only_where_the_dataset_prepends_it(): void
    {
        $b = 'بِسْمِ ٱللَّهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ';
        $surah = fn (int $n, string $first) => ['number' => $n, 'ayahs' => [['numberInSurah' => 1, 'text' => $first]]];
        $out = app(BasmalaSeparator::class)->separate(['surahs' => [
            $surah(1, $b),
            $surah(2, $b.' الٓمٓ'),
            $surah(9, 'بَرَآءَةٌ مِّنَ ٱللَّهِ'),
            $surah(95, 'بِّسْمِ ٱللَّهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ وَٱلتِّينِ وَٱلزَّيْتُونِ'),
        ]], enforceComplete: false);

        $this->assertSame($b, $out['surahs'][0]['ayahs'][0]['text']);
        $this->assertSame('الٓمٓ', $out['surahs'][1]['ayahs'][0]['text']);
        $this->assertSame('بَرَآءَةٌ مِّنَ ٱللَّهِ', $out['surahs'][2]['ayahs'][0]['text']);
        $this->assertSame('وَٱلتِّينِ وَٱلزَّيْتُونِ', $out['surahs'][3]['ayahs'][0]['text']);
    }

    public function test_import_refuses_when_a_surah_lacks_the_expected_basmala(): void
    {
        $this->expectException(RuntimeException::class);
        app(BasmalaSeparator::class)->separate(['surahs' => [
            ['number' => 1, 'ayahs' => [['numberInSurah' => 1, 'text' => 'بِسْمِ ٱللَّهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ']]],
            ['number' => 2, 'ayahs' => [['numberInSurah' => 1, 'text' => 'الٓمٓ']]],
        ]]);
    }

    public function test_bookmarks_are_owner_only(): void
    {
        $this->actingUser();
        $id = $this->postJson('/api/v1/bookmarks', ['target_type' => 'verse', 'target_id' => 1])->assertCreated()->json('data.id');

        $this->actingUser();
        $this->getJson('/api/v1/bookmarks')->assertJsonCount(0, 'data');
        $this->deleteJson("/api/v1/bookmarks/{$id}")->assertForbidden();
    }
}
