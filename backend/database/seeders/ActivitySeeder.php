<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use App\Models\PointRule;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    /** Point values are illustrative motivational settings, not Islamic reward calculations. */
    private const TYPES = [
        ['key' => 'salah_rakat', 'name' => 'Salah', 'category' => 'salah', 'unit' => 'rakat', 'in_app' => false, 'manual' => true, 'client' => false, 'daily' => false, 'max' => 40, 'points' => 1, 'per' => 1],
        ['key' => 'quran_page', 'name' => 'Quran reading', 'category' => 'quran', 'unit' => 'page', 'in_app' => true, 'manual' => true, 'client' => true, 'daily' => false, 'max' => 604, 'points' => 2, 'per' => 1],
        ['key' => 'dhikr_count', 'name' => 'Dhikr', 'category' => 'dhikr', 'unit' => 'count', 'in_app' => true, 'manual' => true, 'client' => true, 'daily' => false, 'max' => 100000, 'points' => 1, 'per' => 10],
        ['key' => 'dua', 'name' => 'Dua', 'category' => 'dua', 'unit' => 'record', 'in_app' => false, 'manual' => true, 'client' => true, 'daily' => false, 'max' => 50, 'points' => 1, 'per' => 1],
        ['key' => 'good_deed', 'name' => 'Good deed', 'category' => 'good_deed', 'unit' => 'record', 'in_app' => false, 'manual' => true, 'client' => true, 'daily' => false, 'max' => 1, 'points' => 5, 'per' => 1],
        ['key' => 'fasting', 'name' => 'Fasting', 'category' => 'fasting', 'unit' => 'day', 'in_app' => false, 'manual' => true, 'client' => true, 'daily' => true, 'max' => 1, 'points' => 10, 'per' => 1],
        ['key' => 'sunnah_act', 'name' => 'Sunnah practice', 'category' => 'sunnah', 'unit' => 'record', 'in_app' => false, 'manual' => true, 'client' => true, 'daily' => false, 'max' => 1, 'points' => 1, 'per' => 1],
        ['key' => 'lesson_complete', 'name' => 'Lesson completed', 'category' => 'learning', 'unit' => 'lesson', 'in_app' => true, 'manual' => false, 'client' => false, 'daily' => false, 'max' => 1, 'points' => 3, 'per' => 1],
        ['key' => 'quiz_complete', 'name' => 'Quiz completed', 'category' => 'quiz', 'unit' => 'quiz', 'in_app' => true, 'manual' => false, 'client' => false, 'daily' => false, 'max' => 1, 'points' => 2, 'per' => 1],
        ['key' => 'audio_learning', 'name' => 'Audio learning', 'category' => 'audio', 'unit' => 'minute', 'in_app' => true, 'manual' => false, 'client' => true, 'daily' => false, 'max' => 600, 'points' => 1, 'per' => 5],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $t) {
            $type = ActivityType::query()->updateOrCreate(['key' => $t['key']], [
                'name' => $t['name'],
                'category' => $t['category'],
                'unit' => $t['unit'],
                'allows_in_app_source' => $t['in_app'],
                'allows_manual_source' => $t['manual'],
                'client_creatable' => $t['client'],
                'one_per_day' => $t['daily'],
                'max_quantity' => $t['max'],
            ]);
            if (! PointRule::query()->where('activity_type_id', $type->id)->exists()) {
                PointRule::query()->create([
                    'activity_type_id' => $type->id,
                    'version' => 1,
                    'points' => $t['points'],
                    'per_quantity' => $t['per'],
                ]);
            }
        }
    }
}
