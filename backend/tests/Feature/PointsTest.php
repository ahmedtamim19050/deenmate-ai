<?php

namespace Tests\Feature;

use App\Models\ActivityType;
use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\Points\PointsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_ledger_rows_cannot_be_updated_or_deleted(): void
    {
        $this->actingUser();
        $this->postJson('/api/v1/activities', $this->activityPayload())->assertCreated();
        $tx = PointTransaction::firstOrFail();

        try {
            $tx->update(['points' => 999]);
            $this->fail('update should throw');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(LogicException::class);
        $tx->delete();
    }

    public function test_reversal_appends_rows_and_keeps_history(): void
    {
        $user = $this->actingUser();
        $id = $this->postJson('/api/v1/activities', $this->activityPayload(['quantity' => 5]))->json('data.id');

        $this->deleteJson("/api/v1/activities/{$id}")->assertOk();
        $this->deleteJson("/api/v1/activities/{$id}")->assertOk()->assertJsonPath('data.points_reversed', 0);

        $rows = PointTransaction::where('user_id', $user->id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('earn', $rows[0]->type);
        $this->assertSame(10, $rows[0]->points);
        $this->assertSame('reverse', $rows[1]->type);
        $this->assertSame(-10, $rows[1]->points);
        $this->assertSame($rows[0]->id, $rows[1]->reverses_transaction_id);
        $this->assertSame(0, (int) $user->pointBalance()->value('balance'));
    }

    public function test_balance_is_never_negative(): void
    {
        $user = $this->actingUser();
        $id = $this->postJson('/api/v1/activities', $this->activityPayload(['quantity' => 5]))->json('data.id');
        $admin = User::factory()->create();
        app(PointsService::class)->adminAdjust($user, -8, 'test correction', $admin);
        $this->assertSame(2, (int) $user->pointBalance()->value('balance'));

        $this->deleteJson("/api/v1/activities/{$id}")->assertOk();

        $this->assertSame(0, (int) $user->pointBalance()->value('balance'));
        $this->assertSame(-2, PointTransaction::where('type', 'reverse')->first()->points);
    }

    public function test_new_rule_version_applies_to_new_activity_and_is_recorded(): void
    {
        $user = $this->actingUser();
        $type = ActivityType::where('key', 'quran_page')->first();
        $rule = PointRule::create(['activity_type_id' => $type->id, 'points' => 5, 'per_quantity' => 1]);

        $this->assertSame(2, $rule->version);
        $this->assertFalse(PointRule::where('activity_type_id', $type->id)->where('version', 1)->first()->is_active);

        $this->postJson('/api/v1/activities', $this->activityPayload(['quantity' => 2]))->assertCreated()->assertJsonPath('points_awarded', 10);
        $this->assertSame(2, PointTransaction::where('user_id', $user->id)->first()->rule_version);
    }

    public function test_daily_cap_limits_awards(): void
    {
        $this->actingUser();
        $type = ActivityType::where('key', 'dua')->first();
        PointRule::create(['activity_type_id' => $type->id, 'points' => 2, 'per_quantity' => 1, 'daily_cap' => 3]);
        $dua = fn () => $this->activityPayload(['type' => 'dua', 'quantity' => 1, 'source' => 'manual', 'confirmed' => true]);

        $this->postJson('/api/v1/activities', $dua())->assertJsonPath('points_awarded', 2);
        $this->postJson('/api/v1/activities', $dua())->assertJsonPath('points_awarded', 1);
        $this->postJson('/api/v1/activities', $dua())->assertJsonPath('points_awarded', 0);
    }

    public function test_achievement_is_awarded_once_and_stays_private(): void
    {
        $user = $this->actingUser();

        $this->postJson('/api/v1/activities', $this->activityPayload())->assertJsonPath('new_achievements.0.key', 'quran_starter');
        $this->postJson('/api/v1/activities', $this->activityPayload())->assertJsonCount(0, 'new_achievements');
        $this->assertSame(1, $user->userAchievements()->count());

        $this->actingUser();
        $this->getJson('/api/v1/achievements/mine')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_completing_a_goal_can_award_the_goal_achievement(): void
    {
        $user = $this->actingUser();
        $id = $this->postJson('/api/v1/goals', ['type' => 'custom', 'title' => 'My goal', 'target_value' => 1, 'period' => 'total'])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/goals/{$id}", ['status' => 'paused'])->assertOk()->assertJsonPath('data.status', 'paused');
        $this->putJson("/api/v1/goals/{$id}", ['status' => 'completed'])->assertOk();

        $this->assertSame(1, $user->userAchievements()->count());
    }

    public function test_no_leaderboard_route_exists(): void
    {
        $this->actingUser();
        $this->getJson('/api/v1/points/leaderboard')->assertNotFound();
        $this->getJson('/api/v1/leaderboard')->assertNotFound();
    }
}
