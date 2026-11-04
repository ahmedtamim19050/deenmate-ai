<?php

namespace App\Services\Prayer;

use App\Models\User;
use App\Models\UserPrayerTemplate;

/** Editable starting points only. They are not presented as universal religious requirements. */
class PrayerTemplateDefaults
{
    public const PRAYERS = ['fajr', 'dhuhr', 'asr', 'maghrib', 'isha'];

    public const CATEGORIES = ['fard', 'sunnah', 'nafl', 'witr'];

    public const DEFAULTS = [
        'fajr' => [
            ['template_key' => 'sunnah_before', 'label' => 'Sunnah', 'category' => 'sunnah', 'rakats' => 2, 'is_enabled' => true],
            ['template_key' => 'fard', 'label' => 'Fard', 'category' => 'fard', 'rakats' => 2, 'is_enabled' => true],
        ],
        'dhuhr' => [
            ['template_key' => 'sunnah_before', 'label' => 'Sunnah (before)', 'category' => 'sunnah', 'rakats' => 4, 'is_enabled' => true],
            ['template_key' => 'fard', 'label' => 'Fard', 'category' => 'fard', 'rakats' => 4, 'is_enabled' => true],
            ['template_key' => 'sunnah_after', 'label' => 'Sunnah (after)', 'category' => 'sunnah', 'rakats' => 2, 'is_enabled' => true],
        ],
        'asr' => [
            ['template_key' => 'sunnah_before', 'label' => 'Optional Sunnah', 'category' => 'sunnah', 'rakats' => 4, 'is_enabled' => true],
            ['template_key' => 'fard', 'label' => 'Fard', 'category' => 'fard', 'rakats' => 4, 'is_enabled' => true],
        ],
        'maghrib' => [
            ['template_key' => 'fard', 'label' => 'Fard', 'category' => 'fard', 'rakats' => 3, 'is_enabled' => true],
            ['template_key' => 'sunnah_after', 'label' => 'Sunnah', 'category' => 'sunnah', 'rakats' => 2, 'is_enabled' => true],
        ],
        'isha' => [
            ['template_key' => 'fard', 'label' => 'Fard', 'category' => 'fard', 'rakats' => 4, 'is_enabled' => true],
            ['template_key' => 'sunnah_after', 'label' => 'Sunnah', 'category' => 'sunnah', 'rakats' => 2, 'is_enabled' => true],
            ['template_key' => 'witr', 'label' => 'Witr', 'category' => 'witr', 'rakats' => 3, 'is_enabled' => true],
        ],
    ];

    public function ensureFor(User $user): void
    {
        if (UserPrayerTemplate::query()->where('user_id', $user->id)->exists()) {
            return;
        }
        $this->restore($user);
    }

    public function restore(User $user): void
    {
        UserPrayerTemplate::query()->where('user_id', $user->id)->delete();
        foreach (self::DEFAULTS as $prayer => $rows) {
            foreach ($rows as $i => $row) {
                UserPrayerTemplate::query()->create($row + ['user_id' => $user->id, 'prayer' => $prayer, 'sort_order' => $i]);
            }
        }
    }
}
