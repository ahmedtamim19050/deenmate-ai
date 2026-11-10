<?php

namespace App\Services\AI;

use App\Models\IslamicSourceChunk;

class RetrievedChunk
{
    /** Found by an explicit or follow-up surah:ayah reference. */
    public const MATCH_REFERENCE = 'reference';

    /** Opening verses of a surah asked about as a whole. */
    public const MATCH_SURAH = 'surah';

    /** A well-known verse on a recognised topic. */
    public const MATCH_TOPIC = 'topic';

    /** Word search. */
    public const MATCH_KEYWORD = 'keyword';

    /** Tafsir covering a retrieved verse. */
    public const MATCH_TAFSIR = 'tafsir_for_verse';

    public function __construct(
        public readonly string $id,
        public readonly IslamicSourceChunk $chunk,
        public readonly float $score,
        public readonly string $match = self::MATCH_KEYWORD,
    ) {}

    public function isQuran(): bool
    {
        return $this->chunk->source->source_type === 'quran';
    }

    public function type(): string
    {
        return (string) $this->chunk->source->source_type;
    }

    /** Matched by reference, surah or topic rather than by words alone. */
    public function isStrongMatch(): bool
    {
        return in_array($this->match, [self::MATCH_REFERENCE, self::MATCH_SURAH, self::MATCH_TOPIC], true);
    }

    public function verseKey(): ?string
    {
        return $this->chunk->surah_number !== null ? "{$this->chunk->surah_number}:{$this->chunk->ayah_from}" : null;
    }
}
