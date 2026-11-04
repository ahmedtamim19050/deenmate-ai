<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/** Tap for log channels: masks credentials and personal text in log context. */
class RedactSensitiveData
{
    private const KEYS = ['password', 'password_confirmation', 'token', 'authorization', 'api_key', 'push_token', 'message', 'comment', 'items', 'metadata'];

    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(function (LogRecord $record) {
                return $record->with(context: $this->scrub($record->context));
            });
        }
    }

    private function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::KEYS, true)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = $this->scrub($value);
            }
        }

        return $data;
    }
}
