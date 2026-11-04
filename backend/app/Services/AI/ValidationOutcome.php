<?php

namespace App\Services\AI;

class ValidationOutcome
{
    private function __construct(
        public readonly bool $accepted,
        public readonly ?string $reason,
        public readonly array $citedIds,
    ) {}

    public static function accept(array $citedIds): self
    {
        return new self(true, null, $citedIds);
    }

    public static function reject(string $reason): self
    {
        return new self(false, $reason, []);
    }
}
