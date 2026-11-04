<?php

namespace Tests\Feature;

use App\Models\PrayerSchedule;
use App\Models\User;
use App\Models\UserPrayerSetting;
use App\Services\Prayer\PrayerTimeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrayerTimesTest extends TestCase
{
    use RefreshDatabase;

    private function settings(array $overrides = []): UserPrayerSetting
    {
        return new UserPrayerSetting($overrides + [
            'latitude' => 21.4225, 'longitude' => 39.8262, 'timezone' => 'Asia/Riyadh',
            'calculation_method' => 'MAKKAH', 'asr_method' => 'standard', 'high_latitude_rule' => 'angle_based',
            'fajr_offset' => 0, 'sunrise_offset' => 0, 'dhuhr_offset' => 0, 'asr_offset' => 0, 'maghrib_offset' => 0, 'isha_offset' => 0,
        ]);
    }

    public function test_times_are_ordered_and_on_the_requested_local_date(): void
    {
        $times = app(PrayerTimeService::class)->calculate($this->settings(), '2026-03-20');

        $order = ['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'];
        for ($i = 1; $i < count($order); $i++) {
            $this->assertTrue($times[$order[$i - 1]]->lt($times[$order[$i]]), "{$order[$i - 1]} before {$order[$i]}");
        }
        foreach (['fajr', 'dhuhr', 'isha'] as $p) {
            $this->assertSame('2026-03-20', $times[$p]->setTimezone('Asia/Riyadh')->toDateString());
        }
    }

    public function test_hanafi_asr_is_later_than_standard(): void
    {
        $service = app(PrayerTimeService::class);
        $standard = $service->calculate($this->settings(), '2026-03-20')['asr'];
        $hanafi = $service->calculate($this->settings(['asr_method' => 'hanafi']), '2026-03-20')['asr'];

        $this->assertTrue($hanafi->gt($standard));
    }

    public function test_manual_offset_moves_only_that_prayer(): void
    {
        $service = app(PrayerTimeService::class);
        $base = $service->calculate($this->settings(), '2026-03-20');
        $moved = $service->calculate($this->settings(['fajr_offset' => 10]), '2026-03-20');

        $this->assertSame(10.0, abs(round($base['fajr']->diffInMinutes($moved['fajr']))));
        $this->assertTrue($base['dhuhr']->equalTo($moved['dhuhr']));
    }

    public function test_method_changes_the_result(): void
    {
        $service = app(PrayerTimeService::class);
        $mwl = $service->calculate($this->settings(['calculation_method' => 'MWL']), '2026-03-20')['fajr'];
        $isna = $service->calculate($this->settings(['calculation_method' => 'ISNA']), '2026-03-20')['fajr'];

        $this->assertFalse($mwl->equalTo($isna));
    }

    public function test_today_reports_unconfigured_until_a_location_is_saved_and_gps_is_optional(): void
    {
        $this->actingUser();
        $this->getJson('/api/v1/prayer/today')->assertOk()->assertJsonPath('data.configured', false);

        $this->putJson('/api/v1/prayer/settings', [
            'location_mode' => 'city', 'city' => 'Dhaka', 'country' => 'BD',
            'latitude' => 23.8103, 'longitude' => 90.4125, 'timezone' => 'Asia/Dhaka',
            'calculation_method' => 'KARACHI', 'asr_method' => 'hanafi',
        ])->assertOk();

        $this->getJson('/api/v1/prayer/today?date=2026-06-01')->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.date', '2026-06-01')
            ->assertJsonPath('data.timezone', 'Asia/Dhaka');
    }

    public function test_schedule_is_recalculated_when_settings_change(): void
    {
        $user = $this->actingUser();
        $base = ['latitude' => 23.8103, 'longitude' => 90.4125, 'timezone' => 'Asia/Dhaka', 'calculation_method' => 'KARACHI'];
        $this->putJson('/api/v1/prayer/settings', $base)->assertOk();
        $first = $this->getJson('/api/v1/prayer/today?date=2026-06-01')->json('data.times.fajr');

        $this->putJson('/api/v1/prayer/settings', ['calculation_method' => 'ISNA'])->assertOk();
        $second = $this->getJson('/api/v1/prayer/today?date=2026-06-01')->json('data.times.fajr');

        $this->assertNotSame($first, $second);
        $this->assertSame(1, PrayerSchedule::where('user_id', $user->id)->count());
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $this->actingUser();
        $this->putJson('/api/v1/prayer/settings', ['latitude' => 120])->assertUnprocessable();
        $this->putJson('/api/v1/prayer/settings', ['timezone' => 'Mars/Base'])->assertUnprocessable();
        $this->putJson('/api/v1/prayer/settings', ['calculation_method' => 'NOPE'])->assertUnprocessable();
    }
}
