<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\LlmResponse;

interface LlmProvider
{
    public function name(): string;

    public function isConfigured(): bool;

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $jsonSchema  the response must validate against this schema
     */
    public function complete(string $system, array $messages, array $jsonSchema): LlmResponse;
}
