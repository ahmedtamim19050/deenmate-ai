<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_activity_awards_server_calculated_points(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/activities', $this->activityPayload(['quantity' => 3]))
            ->assertCreated()
            ->assertJsonPath('points_awarded', 6)
            ->assertJsonPath('balance', 6);
    }

    public function test_client_sent_points_are_ignored(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/activities', $this->activityPayload(['quantity' => 1, 'points' => 9999, 'points_awarded' => 9999]))
            ->assertCreated()
            ->assertJsonPath('points_awarded', 2);
    }

    public function test_client_sent_user_id_is_ignored(): void
    {
        $me = $this->actingUser();
        $other = User::factory()->create();

        $this->postJson('/api/v1/activities', $this->activityPayload(['user_id' => $other->id]))->assertCreated();

        $this->assertSame(1, ActivityLog::where('user_id', $me->id)->count());
        $this->assertSame(0, ActivityLog::where('user_id', $other->id)->count());
    }

    public function test_same_idempotency_key_never_awards_twice(): void
    {
        $user = $this->actingUser();
        $payload = $this->activityPayload(['quantity' => 2]);

        $this->postJson('/api/v1/activities', $payload)->assertCreated()->assertJsonPath('duplicate', false);
        $this->postJson('/api/v1/activities', $payload)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('points_awarded', 4);

        $this->assertSame(1, ActivityLog::where('user_id', $user->id)->count());
        $this->assertSame(1, PointTransaction::where('user_id', $user->id)->count());
        $this->assertSame(4, (int) $user->pointBalance()->value('balance'));
    }

    public function test_idempotency_keys_are_scoped_to_each_user(): void
    {
        $key = (string) Str::uuid();
        $this->actingUser();
        $this->postJson('/api/v1/activities', $this->activityPayload(['idempotency_key' => $key]))->assertCreated();
        $this->actingUser();
        $this->postJson('/api/v1/activities', $this->activityPayload(['idempotency_key' => $key]))->assertCreated();

        $this->assertSame(2, ActivityLog::count());
    }

    public function test_salah_cannot_be_created_through_the_generic_endpoint(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/activities', $this->activityPayload(['type' => 'salah_rakat', 'source' => 'manual', 'confirmed' => true]))
            ->assertUnprocessable();
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_fasting_cannot_be_recorded_automatically(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/activities', $this->activityPayload(['type' => 'fasting', 'quantity' => 1, 'source' => 'in_app']))
            ->assertUnprocessable()->assertJsonValidationErrors('source');
    }

    public function test_manual_records_need_explicit_confirmation(): void
    {
        $this->actingUser();
        $payload = $this->activityPayload(['type' => 'good_deed', 'quantity' => 1, 'source' => 'manual']);

        $this->postJson('/api/v1/activities', $payload)->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->postJson('/api/v1/activities', $payload + ['confirmed' => true])->assertCreated()->assertJsonPath('points_awarded', 5);
    }

    public function test_one_fasting_record_per_day(): void
    {
        $this->actingUser();
        $fast = fn () => $this->activityPayload(['type' => 'fasting', 'quantity' => 1, 'source' => 'manual', 'confirmed' => true, 'metadata' => ['fast_type' => 'voluntary']]);

        $this->postJson('/api/v1/activities', $fast())->assertCreated()->assertJsonPath('points_awarded', 10);
        $this->postJson('/api/v1/activities', $fast())->assertUnprocessable();
    }

    public function test_quantity_above_the_type_limit_is_rejected(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/activities', $this->activityPayload(['quantity' => 605]))->assertUnprocessable();
    }

    public function test_tasbih_points_follow_the_per_ten_rule(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/activities', $this->activityPayload(['type' => 'dhikr_count', 'quantity' => 33]))
            ->assertCreated()->assertJsonPath('points_awarded', 3);
    }

    public function test_sync_processes_items_independently_and_safely_on_retry(): void
    {
        $user = $this->actingUser();
        $good = $this->activityPayload(['quantity' => 1]);
        $bad = $this->activityPayload(['type' => 'fasting', 'quantity' => 1, 'source' => 'in_app']);

        $first = $this->postJson('/api/v1/activities/sync', ['items' => [$good, $bad]])->assertOk();
        $first->assertJsonPath('data.0.status', 'created')->assertJsonPath('data.1.status', 'rejected');

        $this->postJson('/api/v1/activities/sync', ['items' => [$good]])->assertOk()->assertJsonPath('data.0.status', 'duplicate');
        $this->assertSame(2, (int) $user->pointBalance()->value('balance'));
    }

    public function test_user_cannot_edit_or_delete_another_users_activity(): void
    {
        $this->actingUser();
        $id = $this->postJson('/api/v1/activities', $this->activityPayload())->json('data.id');

        $this->actingUser();
        $this->putJson("/api/v1/activities/{$id}", ['quantity' => 1])->assertForbidden();
        $this->deleteJson("/api/v1/activities/{$id}")->assertForbidden();
    }

    public function test_daily_summary_follows_edits_and_removal(): void
    {
        $user = $this->actingUser();
        $id = $this->postJson('/api/v1/activities', $this->activityPayload(['quantity' => 5]))->json('data.id');
        $this->assertSame(5, $user->refresh()->activityLogs()->first()->quantity);

        $this->putJson("/api/v1/activities/{$id}", ['quantity' => 2])->assertOk()->assertJsonPath('points_awarded', 4);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.today.quran_pages', 2)->assertJsonPath('data.points.balance', 4);

        $this->deleteJson("/api/v1/activities/{$id}")->assertOk()->assertJsonPath('data.points_reversed', 4);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.today.quran_pages', 0)->assertJsonPath('data.points.balance', 0);
    }
}
