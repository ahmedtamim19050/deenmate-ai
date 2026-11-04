<?php

namespace App\Services\AI;

use Illuminate\Support\Collection;

/**
 * Rejects any answer that is not fully anchored to what was retrieved. Citations
 * shown to the user are rebuilt from the database, never taken from model text.
 */
class AnswerValidator
{
    private const HADITH_REFERENCE = '/\b(bukhari|muslim|tirmidhi|abu\s*dawud|abu\s*dawood|nasa\'?i|ibn\s*majah|malik|ahmad|nawawi)\b[^.\n]{0,24}?\b(\d{1,5})\b/iu';

    /** @param Collection<int, RetrievedChunk> $chunks */
    public function validate(array $answer, Collection $chunks): ValidationOutcome
    {
        $text = trim((string) ($answer['answer'] ?? ''));
        if ($text === '') {
            return ValidationOutcome::reject('empty_answer');
        }
        if (($answer['insufficient_sources'] ?? false) === true) {
            return ValidationOutcome::reject('model_reported_insufficient');
        }

        $byId = $chunks->keyBy('id');
        $cited = array_values(array_unique(array_map('strval', $answer['cited_source_ids'] ?? [])));
        if ($cited === []) {
            return ValidationOutcome::reject('no_citations');
        }
        foreach ($cited as $id) {
            if (! $byId->has($id)) {
                return ValidationOutcome::reject('unknown_citation:'.$id);
            }
        }

        $body = $text.' '.($answer['scholarly_differences'] ?? '');
        preg_match_all('/\[(S\d+)\]/', $body, $inline);
        foreach ($inline[1] as $id) {
            if (! in_array($id, $cited, true)) {
                return ValidationOutcome::reject('inline_marker_not_cited:'.$id);
            }
        }

        $references = $chunks->filter(fn (RetrievedChunk $c) => in_array($c->id, $cited, true))
            ->map(fn (RetrievedChunk $c) => mb_strtolower((string) $c->chunk->reference))
            ->values();

        preg_match_all('/\b(\d{1,3}):(\d{1,3})\b/', $body, $verses, PREG_SET_ORDER);
        foreach ($verses as $v) {
            $wanted = "quran {$v[1]}:{$v[2]}";
            if (! $references->contains($wanted)) {
                return ValidationOutcome::reject('uncited_verse:'.$v[0]);
            }
        }

        preg_match_all(self::HADITH_REFERENCE, $body, $hadith, PREG_SET_ORDER);
        foreach ($hadith as $h) {
            $number = $h[2];
            $collection = mb_strtolower(preg_replace('/\s+/', ' ', $h[1]));
            $matched = $references->contains(fn (string $r) => str_contains($r, $collection) && preg_match('/\b'.$number.'\b/', $r));
            if (! $matched) {
                return ValidationOutcome::reject('uncited_hadith:'.$h[0]);
            }
        }

        return ValidationOutcome::accept($cited);
    }
}
