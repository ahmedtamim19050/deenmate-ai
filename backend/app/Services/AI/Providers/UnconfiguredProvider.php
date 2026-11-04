<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\LlmProvider;
use App\Services\AI\LlmException;
use App\Services\AI\LlmResponse;

class UnconfiguredProvider implements LlmProvider
{
    public function name(): string
    {
        return 'none';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function complete(string $system, array $messages, array $jsonSchema): LlmResponse
    {
        throw new LlmException('No AI provider is configured.');
    }
}
