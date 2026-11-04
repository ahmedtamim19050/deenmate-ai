<?php

namespace App\Services\AI;

use App\Models\IslamicSourceChunk;

class RetrievedChunk
{
    public function __construct(
        public readonly string $id,
        public readonly IslamicSourceChunk $chunk,
        public readonly float $score,
    ) {}

    /** Metadata comes from the database only, so the model cannot alter a citation. */
    public function toCitation(): array
    {
        $source = $this->chunk->source;

        return [
            'id' => $this->id,
            'source_id' => $source->id,
            'title' => $source->title,
            'type' => $source->source_type,
            'author' => $source->author,
            'publisher' => $source->publisher,
            'reference' => $this->chunk->reference ?? $source->reference_identifier,
            'language' => $source->language,
            'url' => $source->url,
            'excerpt' => mb_substr($this->chunk->content, 0, 600),
            'verification_status' => $source->verification_status,
            'reviewed_at' => $source->reviewed_at?->toIso8601String(),
        ];
    }
}
