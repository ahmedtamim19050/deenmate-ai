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

        if (! $chunks->contains(fn (RetrievedChunk $c) => $c->isQuran() && in_array($c->id, $cited, true))) {
            return ValidationOutcome::reject('no_quran_citation');
        }

        // Bangla answers may write references in Bangla digits, which must not skip the checks below.
        $body = SourceRetriever::asciiDigits($text.' '.($answer['scholarly_differences'] ?? ''));
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

        // A quotation must be the source's own words. A model-made rendering of a verse in quotation
        // marks would read as the published translation, so it is refused.
        $citedTexts = $chunks->filter(fn (RetrievedChunk $c) => in_array($c->id, $cited, true))
            ->map(fn (RetrievedChunk $c) => self::comparable((string) $c->chunk->content))->values();
        foreach (self::quotations($text.' '.($answer['scholarly_differences'] ?? '')) as $quote) {
            $needle = self::comparable($quote);
            if (! $citedTexts->contains(fn (string $source) => str_contains($source, $needle))) {
                return ValidationOutcome::reject('unverified_quotation');
            }
        }

        return ValidationOutcome::accept($cited);
    }

    /** @return array<int, string> quoted passages long enough to be presented as a source's words */
    public static function quotations(string $text): array
    {
        preg_match_all('/"([^"]{2,})"|“([^”]{2,})”|«([^»]{2,})»|„([^“”]{2,})[“”]/u', $text, $m, PREG_SET_ORDER);
        $quotes = [];
        foreach ($m as $hit) {
            $quote = trim((string) collect(array_slice($hit, 1))->first(fn ($g) => $g !== ''));
            // Short quoted terms such as "taqwa" or "be patient" are not passages.
            if (count(preg_split('/\s+/u', $quote, -1, PREG_SPLIT_NO_EMPTY)) >= 5) {
                $quotes[] = $quote;
            }
        }

        return $quotes;
    }

    /** Compares wording, not typography: case, curly quotes, dashes, spacing and edge punctuation are ignored. */
    private static function comparable(string $text): string
    {
        $text = mb_strtolower(strtr($text, ['’' => "'", '‘' => "'", '“' => '"', '”' => '"', '—' => '-', '–' => '-']));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text, " \t\n.,;:!?'\"-…");
    }
}
