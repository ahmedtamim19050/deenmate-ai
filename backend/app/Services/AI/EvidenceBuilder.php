<?php

namespace App\Services\AI;

use App\Models\QuranSurah;
use App\Models\QuranVerse;
use Illuminate\Support\Collection;

/**
 * Turns retrieved chunks into evidence records for the app. Every field comes from the database:
 * the Arabic from the verified Quran text, the translation and its credit from the approved source.
 * Nothing here is model output, and no reviewer identity or internal id is included.
 */
class EvidenceBuilder
{
    /**
     * @param  Collection<int, RetrievedChunk>  $chunks
     * @return array<int, array<string, mixed>>
     */
    public function build(Collection $chunks): array
    {
        $verses = $chunks->filter(fn (RetrievedChunk $c) => $c->isQuran() && $c->chunk->surah_number !== null);
        $surahs = QuranSurah::query()->whereIn('id', $verses->map(fn ($c) => $c->chunk->surah_number)->unique()->values())->get()->keyBy('id');
        $arabic = $verses->isEmpty() ? collect() : QuranVerse::query()
            ->where(function ($q) use ($verses) {
                foreach ($verses as $c) {
                    $q->orWhere(fn ($q) => $q->where('surah_id', $c->chunk->surah_number)->where('verse_number', $c->chunk->ayah_from));
                }
            })
            ->get(['surah_id', 'verse_number', 'text_arabic'])
            ->keyBy(fn ($v) => "{$v->surah_id}:{$v->verse_number}");

        return $chunks->map(fn (RetrievedChunk $c) => $this->record($c, $surahs, $arabic))->values()->all();
    }

    private function record(RetrievedChunk $c, Collection $surahs, Collection $arabic): array
    {
        $chunk = $c->chunk;
        $source = $chunk->source;
        $edition = $source->translationEdition;
        $citation = $chunk->citation ?? [];

        $record = [
            'id' => $c->id,
            'type' => $source->source_type,
            'reference' => $chunk->reference ?? $source->reference_identifier,
            'source_key' => $source->reference_identifier,
            'title' => $source->title,
            'edition' => $edition?->name ?? $source->title,
            'version' => $source->version ?? $edition?->version,
            'author' => $source->author,
            'publisher' => $source->publisher,
            'language' => $source->language,
            'attribution' => $source->attribution ?? $edition?->attribution_text,
            'license' => $edition?->license ?? $source->license,
            'license_url' => $source->license_url ?? $edition?->license_url,
            'source_url' => $source->url,
            'url' => $source->url,
            'verification_status' => $source->verification_status,
            'checksum' => $chunk->content_checksum ?? hash('sha256', (string) $chunk->content),
            'relevance' => $c->score,
            'match' => $c->match,
            // Kept for app versions that read the older citation shape.
            'excerpt' => mb_substr((string) $chunk->content, 0, 600),
        ];

        return $record + match ($source->source_type) {
            'quran' => $this->quran($c, $surahs, $arabic),
            'tafsir' => ['surah_number' => $chunk->surah_number, 'ayah_from' => $chunk->ayah_from, 'ayah_to' => $chunk->ayah_to, 'text' => $chunk->content],
            'hadith' => [
                'collection' => $citation['collection'] ?? $source->title,
                'book' => $citation['book'] ?? null,
                'hadith_number' => $citation['hadith_number'] ?? null,
                // Only a grade stated by the source, with who graded it. Never inferred.
                'grade' => $citation['grade'] ?? null,
                'grade_source' => $citation['grade_source'] ?? null,
                'text' => $chunk->content,
            ],
            default => ['text' => $chunk->content, 'page' => $citation['page'] ?? null, 'chapter' => $citation['chapter'] ?? null],
        };
    }

    private function quran(RetrievedChunk $c, Collection $surahs, Collection $arabic): array
    {
        $chunk = $c->chunk;
        $surah = $surahs[$chunk->surah_number] ?? null;
        $edition = $chunk->source->translationEdition;

        return [
            'surah_number' => $chunk->surah_number,
            'surah_name' => $surah?->name_transliterated,
            'surah_name_english' => $surah?->name_english,
            'surah_name_arabic' => $surah?->name_arabic,
            'ayah_number' => $chunk->ayah_from,
            'arabic' => $arabic["{$chunk->surah_number}:{$chunk->ayah_from}"]->text_arabic ?? null,
            // The approved translation, word for word.
            'translation' => $chunk->content,
            'translator' => $edition?->translator ?? $chunk->source->author,
        ];
    }
}
