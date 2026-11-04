<?php

namespace Tests\Feature;

use App\Filament\Resources\AiPrompts\Pages\ManageAiPrompts;
use App\Filament\Resources\AiResponseFeedback\Pages\ManageAiResponseFeedback;
use App\Filament\Resources\Courses\Pages\ManageCourses;
use App\Filament\Resources\IslamicSources\Pages\ManageIslamicSources;
use App\Filament\Resources\Lessons\Pages\ManageLessons;
use App\Filament\Resources\PointRules\Pages\ManagePointRules;
use App\Filament\Resources\Quizzes\Pages\ManageQuizzes;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\ActivityType;
use App\Models\AiConversation;
use App\Models\AiPrompt;
use App\Models\AiResponseFeedback;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\IslamicSource;
use App\Models\Lesson;
use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\ScholarReview;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\AppSettingSeeder;
use Database\Seeders\StarterCurriculumSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** Opens forms and runs actions for real, which rendering a list page does not exercise. */
class AdminActionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['admin_role' => $role])->save();
        $this->actingAs($user);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->seedReferenceData();
        $this->seed([AppSettingSeeder::class, AchievementSeeder::class, StarterCurriculumSeeder::class]);
    }

    /** Every create and edit form in the panel must at least open without an error. */
    public function test_every_create_and_edit_form_opens(): void
    {
        $this->admin(User::ROLE_SUPER_ADMIN);

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            $page = $resource::getPages()['index']->getPage();
            $test = Livewire::test($page);
            $test->assertSuccessful();

            if ($resource::canCreate()) {
                $test->mountAction('create')->assertHasNoActionErrors();
            }

            $record = $resource::getEloquentQuery()->first();
            if ($record !== null && $resource::canEdit($record) && ! isset($resource::getPages()['edit'])) {
                Livewire::test($page)->mountAction(TestAction::make('edit')->table($record))->assertHasNoActionErrors();
            }
        }
    }

    public function test_an_editor_can_create_a_course_through_the_form_and_it_starts_pending(): void
    {
        $this->admin(User::ROLE_CONTENT_EDITOR);

        Livewire::test(ManageCourses::class)
            ->callAction('create', data: [
                'title' => 'New course',
                'slug' => 'new-course',
                'category' => 'basics',
                'level' => 'beginner',
                'language' => 'en',
                'sort_order' => 1,
                'is_published' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('pending', Course::where('slug', 'new-course')->firstOrFail()->review_status);
        $this->assertTrue(AuditLog::where('action', 'admin.Course.created')->exists());
    }

    public function test_an_editor_cannot_see_the_review_action_but_a_reviewer_can(): void
    {
        $lesson = Lesson::first();

        $this->admin(User::ROLE_CONTENT_EDITOR);
        Livewire::test(ManageLessons::class)->assertActionHidden(TestAction::make('review')->table($lesson));

        $reviewer = $this->admin(User::ROLE_SCHOLAR_REVIEWER);
        Livewire::test(ManageLessons::class)
            ->callAction(TestAction::make('review')->table($lesson), data: ['status' => 'approved', 'notes' => 'Checked'])
            ->assertHasNoActionErrors();

        $this->assertSame('approved', $lesson->refresh()->review_status);
        $review = ScholarReview::where('reviewable_id', $lesson->id)->firstOrFail();
        $this->assertSame($reviewer->id, $review->reviewer_id);
        $this->assertSame('Checked', $review->notes);
    }

    public function test_a_reviewer_approving_a_source_records_who_and_when(): void
    {
        $source = IslamicSource::create(['title' => 'S', 'source_type' => 'article', 'reference_identifier' => 'x']);
        $reviewer = $this->admin(User::ROLE_SCHOLAR_REVIEWER);

        Livewire::test(ManageIslamicSources::class)
            ->callAction(TestAction::make('review')->table($source), data: ['status' => 'approved'])
            ->assertHasNoActionErrors();

        $source->refresh();
        $this->assertSame('approved', $source->verification_status);
        $this->assertSame($reviewer->id, $source->reviewer_id);
        $this->assertNotNull($source->reviewed_at);
    }

    public function test_an_editor_cannot_set_verification_status_through_the_source_form(): void
    {
        $this->admin(User::ROLE_CONTENT_EDITOR);

        Livewire::test(ManageIslamicSources::class)
            ->callAction('create', data: [
                'title' => 'Draft',
                'source_type' => 'article',
                'reference_identifier' => 'draft-1',
                'language' => 'en',
                'verification_status' => 'approved',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('pending', IslamicSource::where('reference_identifier', 'draft-1')->firstOrFail()->verification_status);
    }

    public function test_only_a_super_admin_can_assign_roles_and_adjust_points(): void
    {
        $target = User::factory()->create();

        $this->admin(User::ROLE_SYSTEM_ADMIN);
        Livewire::test(ManageUsers::class)
            ->assertActionHidden(TestAction::make('role')->table($target))
            ->assertActionHidden(TestAction::make('adjustPoints')->table($target));

        $super = $this->admin(User::ROLE_SUPER_ADMIN);
        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('role')->table($target), data: ['admin_role' => User::ROLE_CONTENT_EDITOR])
            ->assertHasNoActionErrors();
        $this->assertSame(User::ROLE_CONTENT_EDITOR, $target->refresh()->admin_role);

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('adjustPoints')->table($target), data: ['points' => 25, 'reason' => 'Goodwill'])
            ->assertHasNoActionErrors();

        $tx = PointTransaction::where('user_id', $target->id)->firstOrFail();
        $this->assertSame('admin_adjustment', $tx->type);
        $this->assertSame($super->id, $tx->actor_id);
        $this->assertSame(25, (int) $target->pointBalance()->value('balance'));
        $this->assertTrue(AuditLog::where('action', 'admin.points_adjusted')->exists());
    }

    public function test_a_new_point_rule_supersedes_the_previous_version(): void
    {
        $this->admin(User::ROLE_SYSTEM_ADMIN);
        $type = ActivityType::where('key', 'quran_page')->firstOrFail();

        Livewire::test(ManagePointRules::class)
            ->callAction('create', data: ['activity_type_id' => $type->id, 'points' => 3, 'per_quantity' => 1])
            ->assertHasNoActionErrors();

        $rules = PointRule::where('activity_type_id', $type->id)->orderBy('version')->get();
        $this->assertCount(2, $rules);
        $this->assertFalse($rules[0]->is_active);
        $this->assertTrue($rules[1]->is_active);
        $this->assertSame(2, $rules[1]->version);
    }

    public function test_activating_a_prompt_deactivates_other_versions(): void
    {
        $this->admin(User::ROLE_SYSTEM_ADMIN);
        $old = AiPrompt::create(['name' => 'assistant_system', 'version' => 1, 'content' => 'a', 'is_active' => true]);
        $new = AiPrompt::create(['name' => 'assistant_system', 'version' => 2, 'content' => 'b', 'is_active' => false]);

        Livewire::test(ManageAiPrompts::class)
            ->callAction(TestAction::make('activate')->table($new))
            ->assertHasNoActionErrors();

        $this->assertFalse($old->refresh()->is_active);
        $this->assertTrue($new->refresh()->is_active);
    }

    public function test_feedback_screen_hides_who_sent_it(): void
    {
        $reporter = User::factory()->create(['name' => 'Private Person', 'email' => 'private@example.test']);
        $conversation = AiConversation::create(['uuid' => (string) Str::uuid(), 'user_id' => $reporter->id]);
        $message = $conversation->messages()->create(['role' => 'assistant', 'content' => 'An answer']);
        $feedback = AiResponseFeedback::create(['ai_message_id' => $message->id, 'user_id' => $reporter->id, 'rating' => 'incorrect']);

        $this->admin(User::ROLE_SCHOLAR_REVIEWER);
        Livewire::test(ManageAiResponseFeedback::class)
            ->assertSee('An answer')
            ->assertDontSee('Private Person')
            ->assertDontSee('private@example.test')
            ->callAction(TestAction::make('reviewed')->table($feedback));

        $this->assertSame('reviewed', $feedback->refresh()->status);
    }

    public function test_quiz_form_saves_questions(): void
    {
        $this->admin(User::ROLE_CONTENT_EDITOR);
        $lesson = Lesson::whereDoesntHave('quiz')->firstOrFail();

        Livewire::test(ManageQuizzes::class)
            ->callAction('create', data: [
                'lesson_id' => $lesson->id,
                'title' => 'Quiz',
                'pass_percent' => 60,
                'questions' => [
                    ['question' => 'Q?', 'options' => ['A', 'B'], 'correct_index' => 1, 'explanation' => 'Because'],
                ],
            ])
            ->assertHasNoActionErrors();

        $question = $lesson->refresh()->quiz->questions()->firstOrFail();
        $this->assertSame(['A', 'B'], $question->options);
        $this->assertSame(1, $question->correct_index);
    }
}
