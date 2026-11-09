<?php

namespace App\Services\Quran\QuranEnc;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP access to QuranEnc.com's public API, polite by design: requests are spaced out, slow or
 * failed requests are retried with backoff, and Retry-After is respected. Certificate checks
 * always stay on; QURANENC_CA_BUNDLE can point at a CA file where PHP has none configured.
 */
class QuranEncClient
{
    private float $lastRequestAt = 0.0;

    /** @var callable(int): void */
    private $sleeper;

    public function __construct(?callable $sleeper = null)
    {
        $this->sleeper = $sleeper ?? fn (int $ms) => usleep($ms * 1000);
    }

    /** @return array<string, mixed> */
    public function get(string $path): array
    {
        $attempts = max(1, (int) config('deenmate.quran.quranenc.max_attempts', 4));
        $lastError = '';
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $this->throttle();
            try {
                $response = $this->request()->get(rtrim(config('deenmate.quran.quranenc.api_base'), '/').$path);
            } catch (ConnectionException $e) {
                $lastError = 'connection failed: '.$e->getMessage();
                $this->backoff($attempt, null);

                continue;
            }
            if ($response->successful()) {
                $json = $response->json();
                if (! is_array($json)) {
                    throw new RuntimeException("QuranEnc returned a response that is not JSON for {$path}.");
                }

                return $json;
            }
            $lastError = "HTTP {$response->status()}";
            // Only rate limiting and server errors are worth retrying.
            if ($response->status() !== 429 && $response->serverError() === false) {
                break;
            }
            $this->backoff($attempt, $response);
        }

        throw new RuntimeException("QuranEnc request failed for {$path} after retries: {$lastError}.");
    }

    private function request()
    {
        $request = Http::timeout((int) config('deenmate.quran.quranenc.timeout', 60))
            ->connectTimeout(15)
            ->acceptJson()
            ->withUserAgent('DeenQ-AI translation sync');
        $bundle = config('deenmate.quran.quranenc.ca_bundle');

        return $bundle ? $request->withOptions(['verify' => $bundle]) : $request;
    }

    /** Keeps at least the configured gap between requests. */
    private function throttle(): void
    {
        $gap = (int) config('deenmate.quran.quranenc.delay_ms', 500);
        $elapsed = (microtime(true) - $this->lastRequestAt) * 1000;
        if ($this->lastRequestAt > 0 && $elapsed < $gap) {
            ($this->sleeper)((int) ($gap - $elapsed));
        }
        $this->lastRequestAt = microtime(true);
    }

    private function backoff(int $attempt, ?Response $response): void
    {
        $retryAfter = $response?->header('Retry-After');
        $ms = is_numeric($retryAfter) ? (int) $retryAfter * 1000 : 1000 * (2 ** ($attempt - 1));
        ($this->sleeper)(min($ms, 60000));
    }
}
