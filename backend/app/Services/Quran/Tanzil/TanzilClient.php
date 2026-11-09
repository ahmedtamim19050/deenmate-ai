<?php

namespace App\Services\Quran\Tanzil;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Downloads from Tanzil's own download service. Using it requires accepting Tanzil's terms
 * (CC BY 3.0, verbatim copies only), which is what agree=true records.
 */
class TanzilClient
{
    /**
     * Presentation options, all published by Tanzil: pause marks, sajdah and rub el-hizb signs,
     * superscript alefs and sequential tanween, as in the Madina mushaf.
     */
    public const UTHMANI_OPTIONS = ['marks' => 'true', 'sajdah' => 'true', 'rub' => 'true', 'alef' => 'true', 'stanween' => 'true'];

    public function text(string $quranType, array $options = []): string
    {
        $query = ['quranType' => $quranType, 'outType' => 'xml', 'agree' => 'true'] + $options;

        return $this->get(rtrim(config('deenmate.quran.tanzil.download_url'), '?'), $query);
    }

    public function metadata(): string
    {
        return $this->get(config('deenmate.quran.tanzil.metadata_url'));
    }

    private function get(string $url, array $query = []): string
    {
        try {
            $response = Http::timeout(120)->retry(3, 2000)->get($url, $query);
        } catch (\Throwable $e) {
            throw new RuntimeException("Could not download from Tanzil: {$e->getMessage()}", 0, $e);
        }
        $body = $response->body();
        if (! $response->successful() || ! str_contains($body, '<')) {
            throw new RuntimeException("Tanzil returned HTTP {$response->status()} for {$url}.");
        }

        return $body;
    }
}
