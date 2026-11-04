<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    /** Each describes something done in the app and makes no claim about spiritual status. */
    private const ACHIEVEMENTS = [
        ['quran_starter', 'Quran Starter', 'Recorded your first Quran reading session.', 'menu_book', 'activity_count', 'quran_page', 1],
        ['kindness', 'Kindness', 'Recorded your first good deed.', 'volunteer_activism', 'activity_count', 'good_deed', 1],
        ['knowledge_seeker', 'Knowledge Seeker', 'Completed your first lesson.', 'school', 'activity_count', 'lesson_complete', 1],
        ['personal_goal', 'Personal Goal', 'Completed a personal goal.', 'flag', 'goal_completed', null, 1],
        ['dhikr_first', 'First Dhikr Session', 'Recorded your first dhikr session.', 'radio_button_checked', 'activity_count', 'dhikr_count', 1],
        ['fasting_first', 'First Fast Recorded', 'Recorded your first fast in the app.', 'nights_stay', 'activity_count', 'fasting', 1],
        ['salah_first', 'Salah Tracker Started', 'Made your first Salah record in the app.', 'mosque', 'activity_count', 'salah_rakat', 1],
        ['learner_five', 'Steady Learner', 'Completed five lessons.', 'auto_stories', 'activity_count', 'lesson_complete', 5],
    ];

    public function run(): void
    {
        foreach (self::ACHIEVEMENTS as [$key, $name, $description, $icon, $ruleType, $activity, $threshold]) {
            $achievement = Achievement::query()->updateOrCreate(['key' => $key], compact('name', 'description', 'icon'));
            if ($achievement->rules()->doesntExist()) {
                $achievement->rules()->create(['rule_type' => $ruleType, 'activity_type_key' => $activity, 'threshold' => $threshold]);
            }
        }
    }
}
