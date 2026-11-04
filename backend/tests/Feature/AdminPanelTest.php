<?php

namespace Tests\Feature;

use App\Filament\Pages\AiUsage;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\IslamicSource;
use App\Models\Lesson;
use App\Models\PointTransaction;
use App\Models\SalahRecord;
use App\Models\User;
use Database\Seeders\AppSettingSeeder;
use Database\Seeders\StarterCurriculumSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(?string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['admin_role' => $role])->save();

        return $user;
    }

    public static function roles(): array
    {
        return array_map(fn ($r) => [$r], array_keys(User::ROLES));
    }

    /** Every resource page renders for each role that may see it, and is refused otherwise. */
    #[DataProvider('roles')]
    public function test_every_resource_obeys_its_role_list(string $role): void
    {
        $this->seedReferenceData();
        $this->seed([AppSettingSeeder::class, StarterCurriculumSeeder::class]);
        $user = $this->admin($role);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            $url = $resource::getUrl('index');
            $expected = $resource::canAccess() ? 200 : 403;
            $this->get($url)->assertStatus($expected);
        }
    }

    public function test_app_users_cannot_enter_the_panel(): void
    {
        $this->actingAs($this->admin(null));
        $this->get('/admin')->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    public function test_dashboard_renders_for_each_role_without_worship_data(): void
    {
        foreach (array_keys(User::ROLES) as $role) {
            $this->actingAs($this->admin($role));
            $this->get('/admin')->assertOk();
        }
    }

    public function test_no_admin_resource_exposes_private_worship_records(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $models = array_map(fn ($r) => $r::getModel(), Filament::getPanel('admin')->getResources());

        $this->assertNotContains(SalahRecord::class, $models);
        $this->assertNotContains(ActivityLog::class, $models);
        $this->assertNotContains(PointTransaction::class, $models);
    }

    public function test_content_editor_and_support_are_refused_user_and_settings_changes(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->seed(AppSettingSeeder::class);

        $this->actingAs($this->admin(User::ROLE_CONTENT_EDITOR));
        $this->get(\App\Filament\Resources\Users\UserResource::getUrl('index'))->assertForbidden();
        $this->get(\App\Filament\Resources\AuditLogs\AuditLogResource::getUrl('index'))->assertForbidden();

        $this->actingAs($this->admin(User::ROLE_SUPPORT_STAFF));
        $this->assertFalse(\App\Filament\Resources\Users\UserResource::canEdit(User::factory()->create()));
        $this->get(\App\Filament\Resources\AppSettings\AppSettingResource::getUrl('index'))->assertForbidden();
    }

    public function test_scholar_reviewer_cannot_manage_system_security(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin(User::ROLE_SCHOLAR_REVIEWER));

        $this->get(\App\Filament\Resources\Users\UserResource::getUrl('index'))->assertForbidden();
        $this->get(\App\Filament\Resources\AppSettings\AppSettingResource::getUrl('index'))->assertForbidden();
        $this->get(\App\Filament\Resources\IslamicSources\IslamicSourceResource::getUrl('index'))->assertOk();
    }

    public function test_ai_usage_page_is_limited_to_system_roles(): void
    {
        $this->actingAs($this->admin(User::ROLE_CONTENT_EDITOR));
        $this->assertFalse(AiUsage::canAccess());
        $this->get('/admin/ai-usage')->assertForbidden();

        $this->actingAs($this->admin(User::ROLE_SYSTEM_ADMIN));
        $this->assertTrue(AiUsage::canAccess());
        $this->get('/admin/ai-usage')->assertOk();
    }

    public function test_admin_changes_are_written_to_the_audit_log(): void
    {
        $admin = $this->admin(User::ROLE_SUPER_ADMIN);
        $this->actingAs($admin, 'web');

        $course = Course::create(['slug' => 'x', 'title' => 'x', 'category' => 'basics']);

        $log = AuditLog::query()->where('action', 'admin.Course.created')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame($course->id, (int) $log->subject_id);
    }

    public function test_editing_an_approved_lesson_returns_it_to_pending(): void
    {
        $course = Course::create(['slug' => 'x', 'title' => 'x', 'category' => 'basics']);
        $lesson = $course->lessons()->create(['title' => 't', 'body' => 'b', 'review_status' => 'approved']);

        $lesson->update(['body' => 'changed']);

        $this->assertSame('pending', $lesson->refresh()->review_status);
    }

    public function test_editing_a_passage_returns_an_approved_source_to_pending(): void
    {
        $source = IslamicSource::create(['title' => 's', 'source_type' => 'article', 'reference_identifier' => 'a', 'verification_status' => 'approved']);
        $chunk = $source->chunks()->create(['chunk_index' => 0, 'reference' => 'r', 'content' => 'c']);
        $source->forceFill(['verification_status' => 'approved'])->save();

        $chunk->update(['content' => 'changed']);

        $this->assertSame('pending', $source->refresh()->verification_status);
    }

    public function test_review_command_requires_a_real_reviewer(): void
    {
        $source = IslamicSource::create(['title' => 's', 'source_type' => 'article', 'reference_identifier' => 'a']);
        $editor = $this->admin(User::ROLE_CONTENT_EDITOR);
        $reviewer = $this->admin(User::ROLE_SCHOLAR_REVIEWER);

        $this->artisan('sources:review', ['source' => $source->id, 'reviewer' => $editor->email])->assertFailed();
        $this->assertSame('pending', $source->refresh()->verification_status);

        $this->artisan('sources:review', ['source' => $source->id, 'reviewer' => $reviewer->email])->assertSuccessful();
        $source->refresh();
        $this->assertSame('approved', $source->verification_status);
        $this->assertSame($reviewer->id, $source->reviewer_id);
        $this->assertNotNull($source->reviewed_at);
    }
}
