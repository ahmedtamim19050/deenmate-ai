<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Reference data only. No user, admin or sample worship data is ever seeded. */
    public function run(): void
    {
        $this->call([
            ActivitySeeder::class,
            AchievementSeeder::class,
            AppSettingSeeder::class,
            AiPromptSeeder::class,
            StarterCurriculumSeeder::class,
        ]);
    }
}
