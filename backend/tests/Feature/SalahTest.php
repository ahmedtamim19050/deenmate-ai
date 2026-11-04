<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PointTransaction;
use App\Models\SalahRecord;
use App\Models\User;
use App\Services\Prayer\PrayerTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalahTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_confirm_creates_record_and_awards_per_rakat(): void
    {
        $user = $this->actingUser();

        $this->postJson('/api/v1/salah/confirm', $this->salahPayload())
            ->assertCreated()
            ->assertJsonPath('points_awarded', 4)
            ->assertJsonPath('data.total_rakats', 4)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('balance', 4);
        $this->assertSame(1, SalahRecord::where('user_id', $user->id)->count());
    }

    public function test_requires_explicit_confirmation(): void
    {
        $this->actingUser();
        $payload = $this->salahPayload();
        unset($payload['confirmed']);

        $this->postJson('/api/v1/salah/confirm', $payload)->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->postJson('/api/v1/salah/confirm', ['confirmed' => false] + $payload)->assertUnprocessable();
        $this->assertSame(0, SalahRecord::count());
    }

    public function test_retry_with_same_key_returns_the_original_result(): void
    {
        $user = $this->actingUser();
        $payload = $this->salahPayload();

        $id = $this->postJson('/api/v1/salah/confirm', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/salah/confirm', $payload)->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('balance', 4);

        $this->assertSame(1, PointTransaction::where('user_id', $user->id)->count());
    }

    public function test_second_record_for_same_prayer_and_date_is_rejected(): void
    {
        $this->actingUser();
        $this->postJson('/api/v1/salah/confirm', $this->salahPayload())->assertCreated();
        $this->postJson('/api/v1/salah/confirm', $this->salahPayload())->assertUnprocessable()->assertJsonValidationErrors('prayer');
    }

    public function test_edit_reverses_old_points_and_writes_audit_record(): void
    {
        $user = $this->actingUser();
        $id = $this->postJson('/api/v1/salah/confirm', $this->salahPayload())->json('data.id');

        $this->putJson("/api/v1/salah/{$id}", ['items' => [['label' => 'Fard', 'category' => 'fard', 'rakats' => 2]]])
            ->assertOk()
            ->assertJsonPath('points_awarded', 2)
            ->assertJsonPath('data.total_rakats', 2)
            ->assertJsonPath('data.points', 2)
            ->assertJsonPath('balance', 2);

        $types = PointTransaction::where('user_id', $user->id)->orderBy('id')->pluck('points')->all();
        $this->assertSame([4, -4, 2], $types);
        $this->assertTrue(AuditLog::where('action', 'salah.updated')->exists());
    }

    public function test_reverse_removes_points_and_keeps_ledger_then_prayer_can_be_rerecorded(): void
    {
        $user = $this->actingUser();
        $id = $this->postJson('/api/v1/salah/confirm', $this->salahPayload())->json('data.id');

        $this->postJson("/api/v1/salah/{$id}/reverse")->assertOk()->assertJsonPath('data.points_reversed', 4)->assertJsonPath('balance', 0);
        $this->postJson("/api/v1/salah/{$id}/reverse")->assertOk()->assertJsonPath('data.points_reversed', 0);

        $this->assertSame(2, PointTransaction::where('user_id', $user->id)->count());
        $this->getJson('/api/v1/prayer/history')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/salah/confirm', $this->salahPayload())->assertCreated();
    }

    public function test_other_users_cannot_touch_a_record(): void
    {
        $this->actingUser();
        $id = $this->postJson('/api/v1/salah/confirm', $this->salahPayload())->json('data.id');

        $this->actingUser();
        $this->putJson("/api/v1/salah/{$id}", ['items' => [['label' => 'Fard', 'category' => 'fard', 'rakats' => 2]]])->assertForbidden();
        $this->postJson("/api/v1/salah/{$id}/reverse")->assertForbidden();
        $this->getJson('/api/v1/prayer/history')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_item_validation(): void
    {
        $this->actingUser();
        $bad = fn (array $items) => $this->salahPayload(['items' => $items]);

        $this->postJson('/api/v1/salah/confirm', $bad([]))->assertUnprocessable();
        $this->postJson('/api/v1/salah/confirm', $bad([['label' => 'X', 'category' => 'unknown', 'rakats' => 2]]))->assertUnprocessable();
        $this->postJson('/api/v1/salah/confirm', $bad([['label' => 'X', 'category' => 'fard', 'rakats' => 0]]))->assertUnprocessable();
        $this->postJson('/api/v1/salah/confirm', $this->salahPayload(['prayer' => 'not-a-prayer']))->assertUnprocessable();
        $this->postJson('/api/v1/salah/confirm', $this->salahPayload(['prayer_date' => now()->addDays(5)->toDateString()]))->assertUnprocessable();
    }

    public function test_summary_never_reports_missed_prayers(): void
    {
        $this->actingUser();
        $this->postJson('/api/v1/salah/confirm', $this->salahPayload())->assertCreated();

        $json = $this->getJson('/api/v1/salah/summary')->assertOk()->assertJsonPath('data.records', 1)->json();
        $this->assertStringNotContainsStringIgnoringCase('missed', json_encode($json));
    }

    public function test_templates_are_editable_validated_and_restorable(): void
    {
        $this->actingUser();
        $this->getJson('/api/v1/prayer/templates')->assertOk()->assertJsonCount(12, 'data');

        $row = ['prayer' => 'isha', 'template_key' => 'witr', 'label' => 'Witr', 'category' => 'witr', 'rakats' => 1, 'is_enabled' => true];
        $this->putJson('/api/v1/prayer/templates', ['templates' => [$row]])->assertOk()->assertJsonPath('data.0.rakats', 1)->assertJsonCount(1, 'data');

        $this->putJson('/api/v1/prayer/templates', ['templates' => [$row, $row]])->assertUnprocessable();
        $this->putJson('/api/v1/prayer/templates', ['templates' => [['rakats' => 50] + $row]])->assertUnprocessable();

        $this->postJson('/api/v1/prayer/templates/restore')->assertOk()->assertJsonCount(12, 'data');
    }

    public function test_default_templates_match_the_brief(): void
    {
        $counts = fn (string $p) => array_column(PrayerTemplateDefaults::DEFAULTS[$p], 'rakats');
        $this->assertSame([2, 2], $counts('fajr'));
        $this->assertSame([4, 4, 2], $counts('dhuhr'));
        $this->assertSame([4, 4], $counts('asr'));
        $this->assertSame([3, 2], $counts('maghrib'));
        $this->assertSame('witr', PrayerTemplateDefaults::DEFAULTS['isha'][2]['category']);
    }
}
