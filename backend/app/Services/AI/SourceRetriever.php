<?php

namespace App\Services\AI;

use App\Models\IslamicSourceChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Reads approved sources only. A pending or rejected source can never reach the model. */
class SourceRetriever
{
    private const STOP_WORDS = [
        'a', 'an', 'and', 'are', 'about', 'as', 'at', 'be', 'by', 'can', 'do', 'does', 'for', 'from', 'how', 'i', 'in', 'is', 'it',
        'me', 'my', 'of', 'on', 'or', 'please', 'say', 'says', 'tell', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where',
        'which', 'who', 'why', 'with', 'you', 'your', 'islam', 'islamic', 'muslim', 'muslims', 'explain', 'mean', 'means', 'meaning',
        'quran', 'surah', 'verse', 'verses', 'ayah', 'hadith', 'give', 'know', 'want', 'should', 'would', 'could', 'there', 'their',
    ];

    /** @return Collection<int, RetrievedChunk> */
    public function search(string $question, ?int $limit = null): Collection
    {
        $limit ??= (int) config('deenmate.ai.retrieval_limit');
        $byReference = $this->byReference($question);
        $keywords = $this->keywords($question);

        $remaining = $limit - $byReference->count();
        $byText = ($keywords === [] || $remaining <= 0)
            ? collect()
            : (DB::getDriverName() === 'mysql'
                ? $this->fullText($keywords, $remaining)
                : $this->likeSearch($keywords, $remaining));

        return $byReference->concat($byText)
            ->unique('id')
            ->take($limit)
            ->values()
            ->map(fn (IslamicSourceChunk $chunk, int $i) => new RetrievedChunk('S'.($i + 1), $chunk->loadMissing('source'), (float) ($chunk->score ?? 1)));
    }

    /** @return array<int, string> */
    public function keywords(string $question): array
    {
        $tokens = preg_split('/[^\p{L}\p{M}\p{N}]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            fn (string $t) => mb_strlen($t) >= 3 && ! in_array($t, self::STOP_WORDS, true),
        )));
    }

    private function approved()
    {
        return IslamicSourceChunk::query()
            ->whereHas('source', fn ($q) => $q->where('verification_status', 'approved'))
            ->with('source');
    }

    private function byReference(string $question): Collection
    {
        if (! preg_match_all('/\b(\d{1,3}):(\d{1,3})\b/', $question, $m, PREG_SET_ORDER)) {
            return collect();
        }
        $references = array_map(fn ($hit) => "Quran {$hit[1]}:{$hit[2]}", array_slice($m, 0, 4));

        return $this->approved()->whereIn('reference', $references)->limit(6)->get();
    }

    private function fullText(array $keywords, int $limit): Collection
    {
        // Boolean mode with quoted terms keeps user text from becoming query operators.
        $terms = implode(' ', array_map(fn (string $k) => '"'.str_replace('"', '', $k).'"', array_slice($keywords, 0, 12)));

        return $this->approved()
            ->select('islamic_source_chunks.*')
            ->selectRaw('MATCH(content) AGAINST(? IN BOOLEAN MODE) as score', [$terms])
            ->whereRaw('MATCH(content) AGAINST(? IN BOOLEAN MODE)', [$terms])
            ->orderByDesc('score')
            ->limit($limit)
            ->get();
    }

    /** Fallback for databases without FULLTEXT, such as the SQLite test database. */
    private function likeSearch(array $keywords, int $limit): Collection
    {
        $needed = min(2, count($keywords));

        return $this->approved()
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $k) {
                    $q->orWhere('content', 'like', '%'.addcslashes($k, '%_').'%');
                }
            })
            ->limit(200)
            ->get()
            ->map(function (IslamicSourceChunk $c) use ($keywords) {
                $content = mb_strtolower($c->content);
                $c->setAttribute('score', collect($keywords)->filter(fn ($k) => str_contains($content, $k))->count());

                return $c;
            })
            ->filter(fn ($c) => $c->score >= $needed)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }
}
