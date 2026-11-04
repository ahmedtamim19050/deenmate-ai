<?php

namespace App\Services\AI;

class LlmResponse
{
    public function __construct(
        public readonly string $text,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly string $model,
        public readonly bool $refused = false,
    ) {}
}
