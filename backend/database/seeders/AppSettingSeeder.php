<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    private const SETTINGS = [
        ['support_email', '', 'general', true, 'Shown on the Help and Support screen. Left blank until you set a real address.'],
        ['privacy_policy_url', '', 'general', true, 'Public privacy policy link, required for store listing.'],
        ['terms_url', '', 'general', true, 'Public terms of use link.'],
        ['ai_suggested_questions', "What are the five pillars of Islam?\nWhat does the Quran say about patience?\nHow do I begin learning to pray?\nWhat is the meaning of Surah Al-Fatihah?", 'general', true, 'One suggested AI question per line.'],
        ['notifications_broadcast_enabled', '0', 'notifications', false, 'Allow admins to send content announcements by push. Never used for worship records.'],
        ['notifications_default_reminder_minutes', '10', 'notifications', true, 'Default Remind Later duration in minutes.'],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as [$key, $value, $group, $public, $description]) {
            AppSetting::query()->firstOrCreate(['key' => $key], [
                'value' => $value,
                'group' => $group,
                'is_public' => $public,
                'description' => $description,
            ]);
        }
    }
}
