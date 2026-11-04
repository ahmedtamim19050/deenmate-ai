<?php

namespace Database\Seeders;

use App\Models\AiPrompt;
use App\Services\AI\PromptRepository;
use Illuminate\Database\Seeder;

class AiPromptSeeder extends Seeder
{
    public function run(): void
    {
        AiPrompt::query()->firstOrCreate(
            ['name' => PromptRepository::SYSTEM_PROMPT_NAME, 'version' => 1],
            ['content' => PromptRepository::DEFAULT_SYSTEM_PROMPT, 'is_active' => true],
        );
    }
}
