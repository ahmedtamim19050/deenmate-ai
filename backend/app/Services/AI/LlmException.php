<?php

namespace App\Services\AI;

use RuntimeException;

class LlmException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
