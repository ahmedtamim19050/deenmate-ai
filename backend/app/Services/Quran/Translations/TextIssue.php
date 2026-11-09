<?php

namespace App\Services\Quran\Translations;

final class TextIssue
{
    private function __construct(
        public readonly string $severity,
        public readonly string $code,
        public readonly string $detail,
    ) {}

    public static function error(string $code, string $detail): self
    {
        return new self('error', $code, $detail);
    }

    public static function warning(string $code, string $detail): self
    {
        return new self('warning', $code, $detail);
    }

    public function isError(): bool
    {
        return $this->severity === 'error';
    }
}
