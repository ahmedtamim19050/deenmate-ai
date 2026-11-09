<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\DataExportRequest;
use App\Models\PointTransaction;
use App\Models\SalahRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
        Storage::fake('local');
    }

    public function test_export_contains_personal_data_and_is_owner_only(): void
    {
        $this->actingUser();
        $this->postJson('/api/v1/salah/confirm', $this->salahPayload())->assertCreated();

        $id = $this->postJson('/api/v1/privacy/export')->assertAccepted()->json('data.id');
        $this->getJson("/api/v1/privacy/export/{$id}")->assertOk()->assertJsonPath('data.status', 'ready');

        $download = $this->get("/api/v1/privacy/export/{$id}/download")->assertOk();
        $json = json_decode($download->streamedContent(), true);
        $this->assertCount(1, $json['salah_records']);
        $this->assertArrayHasKey('ledger', $json['points']);

        $this->actingUser();
        $this->getJson("/api/v1/privacy/export/{$id}")->assertNotFound();
        $this->getJson("/api/v1/privacy/export/{$id}/download")->assertNotFound();
    }

    public function test_export_file_is_not_on_a_public_disk(): void
    {
        $this->actingUser();
        $this->postJson('/api/v1/privacy/export');

        $export = DataExportRequest::first();
        Storage::disk('local')->assertExists($export->file_path);
        $this->assertStringNotContainsString('public', $export->file_path);
    }

    public function test_delete_account_needs_password_and_removes_personal_data(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $this->actingAs($user, 'sanctum');
        $this->postJson('/api/v1/salah/confirm', $this->salahPayload())->assertCreated();
        $this->postJson('/api/v1/privacy/export');

        $this->postJson('/api/v1/privacy/delete-account', ['password' => 'wrong', 'confirm' => true])->assertUnprocessable();
        $this->assertNotNull(User::find($user->id));

        $this->postJson('/api/v1/privacy/delete-account', ['password' => 'correct-password', 'confirm' => true])->assertOk();

        $this->assertNull(User::withTrashed()->find($user->id));
        $this->assertSame(0, SalahRecord::withTrashed()->count());
        $this->assertSame(0, ActivityLog::count());
        $this->assertSame(0, PointTransaction::count());
        $this->assertSame([], Storage::disk('local')->allFiles('exports'));
    }

    public function test_deleting_an_account_leaves_no_worship_detail_in_the_audit_log(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $this->actingAs($user, 'sanctum');
        $id = $this->postJson('/api/v1/salah/confirm', $this->salahPayload())->json('data.id');
        $this->putJson("/api/v1/salah/{$id}", ['items' => [['label' => 'Fard', 'category' => 'fard', 'rakats' => 2]]])->assertOk();
        $this->assertTrue(AuditLog::where('action', 'salah.updated')->exists());

        $this->postJson('/api/v1/privacy/delete-account', ['password' => 'correct-password', 'confirm' => true])->assertOk();

        $this->assertFalse(AuditLog::where('action', 'like', 'salah.%')->exists());
        $this->assertFalse(AuditLog::where('action', 'like', 'activity.%')->exists());
        $marker = AuditLog::where('action', 'account.deleted')->firstOrFail();
        $this->assertNull($marker->actor_id);
        $this->assertNull($marker->ip_address);
        $this->assertNull($marker->subject_id);
    }

    public function test_preferences_default_to_private(): void
    {
        $this->actingUser();
        $this->getJson('/api/v1/profile/preferences')->assertOk()
            ->assertJsonPath('data.ai_personalization_enabled', false)
            ->assertJsonMissingPath('data.ads_personalized');
    }
}
