<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function seedReferenceData(): void
    {
        $this->seed([ActivitySeeder::class, AchievementSeeder::class]);
    }

    protected function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    protected function activityPayload(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'quran_page',
            'quantity' => 3,
            'source' => 'in_app',
            'idempotency_key' => (string) Str::uuid(),
            'occurred_at' => now()->toIso8601String(),
            'local_date' => now()->toDateString(),
        ];
    }

    protected function salahPayload(array $overrides = []): array
    {
        return $overrides + [
            'prayer' => 'fajr',
            'prayer_date' => now()->toDateString(),
            'idempotency_key' => (string) Str::uuid(),
            'confirmed' => true,
            'items' => [
                ['template_key' => 'sunnah_before', 'label' => 'Sunnah', 'category' => 'sunnah', 'rakats' => 2],
                ['template_key' => 'fard', 'label' => 'Fard', 'category' => 'fard', 'rakats' => 2],
            ],
        ];
    }
}
