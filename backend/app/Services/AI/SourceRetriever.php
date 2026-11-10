<?php

namespace App\Services\AI;

use App\Models\IslamicSource;
use App\Models\IslamicSourceChunk;
use App\Models\QuranTranslationEdition;
use App\Services\AI\Understanding\QuestionAnalysis;
use App\Services\AI\Understanding\QuestionIntentClassifier;
use App\Services\AI\Understanding\QuestionUnderstandingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hybrid retrieval over approved sources only:
 *   1. exact surah:ayah references (typed, named like Ayatul Kursi, or carried from a follow-up)
 *   2. the opening of a surah asked about as a whole
 *   3. well-known verses for recognised topics (from the lexicon)
 *   4. word search: FULLTEXT for English, spelling-tolerant matching for Bangla, using words the
 *      Bangla/Banglish question was mapped to, so "namaz koy wakt" can find English verses on prayer
 *   5. Tafsir covering the verses found, then other approved sources
 *
 * Eligibility is checked on every call and nothing is cached, so a revoked, stale or unsynced
 * source drops out on the very next question. A pending or rejected source never reaches the model.
 */
class SourceRetriever
{
    /** The rest of the retrieval limit is filled with Quran passages first. */
    private const OTHER_SOURCE_SLOTS = 2;

    private const MAX_TOPIC_VERSES = 6;

    private const SURAH_OPENING_VERSES = 5;

    /** Spellings that differ across Bangla texts, such as নামাজ and নামায. */
    private const BANGLA_SPELLINGS = [['জ', 'য'], ['য', 'জ'], ['র্য্য', 'র্য'], ['র্য', 'র্য্য'], ['ী', 'ি'], ['ূ', 'ু']];

    private const BANGLA_SUFFIXES = ['গুলোর', 'গুলো', 'দের', 'ের', 'কে', 'তে'];

    public function __construct(private QuestionUnderstandingService $understanding) {}

    public static function asciiDigits(string $text): string
    {
        return strtr($text, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    }

    /** @return Collection<int, RetrievedChunk> */
    public function search(string $question, ?int $limit = null): Collection
    {
        return $this->retrieve($this->understanding->analyze($question), $limit);
    }

    /** @return Collection<int, RetrievedChunk> ordered by evidence strength, ids S1, S2, ... */
    public function retrieve(QuestionAnalysis $analysis, ?int $limit = null): Collection
    {
        $limit ??= (int) config('deenmate.ai.retrieval_limit');
        $sources = $this->eligibleSources();
        if ($sources->isEmpty()) {
            return collect();
        }
        $quran = $sources->where('source_type', 'quran')
            ->sortBy(fn (IslamicSource $s) => $s->language === $analysis->answerLanguage() ? 0 : ($s->language === 'en' ? 1 : 2))
            ->values();
        $quranIds = $quran->pluck('id')->all();
        $rank = array_flip($quranIds);

        /** @var array<int, array{0: IslamicSourceChunk, 1: float, 2: string}> $found */
        $found = [];
        $verses = [];
        $add = function (IslamicSourceChunk $chunk, float $score, string $match) use (&$found, &$verses) {
            // One passage per verse: the edition in the answer's language wins, as it was found first.
            $key = $chunk->source->source_type === 'quran' && $chunk->surah_number ? "{$chunk->surah_number}:{$chunk->ayah_from}" : 'c'.$chunk->id;
            if (! isset($verses[$key])) {
                $verses[$key] = true;
                $found[] = [$chunk, $score, $match];
            }
        };

        if ($quranIds !== []) {
            foreach ($this->byReference($analysis, $quranIds, $rank) as $chunk) {
                $add($chunk, 1.0, RetrievedChunk::MATCH_REFERENCE);
            }
            if ($analysis->primaryIntent === QuestionIntentClassifier::SURAH_SUMMARY) {
                foreach ($this->surahOpenings($analysis, $quranIds, $rank) as $chunk) {
                    $add($chunk, 0.85, RetrievedChunk::MATCH_SURAH);
                }
            }
            foreach ($this->byTopic($analysis, $quranIds, $rank) as $i => $chunk) {
                $add($chunk, round(0.9 - $i * 0.02, 3), RetrievedChunk::MATCH_TOPIC);
            }
            $room = $limit - self::OTHER_SOURCE_SLOTS - count($found);
            if ($room > 0) {
                $surah = $analysis->primaryIntent === QuestionIntentClassifier::SURAH_SUMMARY ? ($analysis->entities['surahs'][0] ?? null) : null;
                foreach ($this->textSearch($analysis, $room + 4, $quranIds, $surah) as [$chunk, $score]) {
                    $add($chunk, $score, RetrievedChunk::MATCH_KEYWORD);
                }
            }
        }

        // Keep the strongest Quran evidence within the limit, leaving room for other sources.
        $found = array_slice($found, 0, max(1, $limit - self::OTHER_SOURCE_SLOTS));

        $tafsirIds = $sources->where('source_type', 'tafsir')->pluck('id')->all();
        $others = $sources->whereNotIn('source_type', ['quran', 'tafsir'])->pluck('id')->all();
        $left = $limit - count($found);
        if ($tafsirIds !== [] && $left > 0) {
            foreach ($this->tafsirFor($found, $tafsirIds, min($left, 2)) as $chunk) {
                $add($chunk, 0.8, RetrievedChunk::MATCH_TAFSIR);
            }
        }
        $left = $limit - count($found);
        if ($others !== [] && $left > 0) {
            foreach ($this->textSearch($analysis, min($left, self::OTHER_SOURCE_SLOTS), $others) as [$chunk, $score]) {
                $add($chunk, $score, RetrievedChunk::MATCH_KEYWORD);
            }
        }

        return collect(array_slice($found, 0, $limit))
            ->values()
            ->map(fn (array $hit, int $i) => new RetrievedChunk('S'.($i + 1), $hit[0], $hit[1], $hit[2]));
    }

    /**
     * Sources the AI may use right now. A source that is the AI copy of a translation edition is
     * used only while that edition is approved, synced and its synced checksum matches its content.
     *
     * @return Collection<int, IslamicSource>
     */
    public function eligibleSources(): Collection
    {
        $eligible = QuranTranslationEdition::query()->aiEligible()->pluck('identifier')->all();
        $blocked = QuranTranslationEdition::query()->whereNotIn('identifier', $eligible)
            ->pluck('identifier')->map(fn ($id) => "edition:{$id}")->all();

        return IslamicSource::query()
            ->where('verification_status', 'approved')
            ->when($blocked !== [], fn ($q) => $q->where(fn ($q) => $q->whereNull('reference_identifier')->orWhereNotIn('reference_identifier', $blocked)))
            ->with('translationEdition')
            ->get();
    }

    /** @return array<int, string> */
    public function keywords(string $question): array
    {
        $analysis = $this->understanding->analyze($question);

        return array_values(array_unique([...$analysis->searchEnglish, ...$analysis->searchBangla]));
    }

    private function chunks(array $sourceIds): Builder
    {
        return IslamicSourceChunk::query()->whereIn('islamic_source_id', $sourceIds)->with('source.translationEdition');
    }

    /** Orders chunks of the same verse by edition preference, then by Quran order. */
    private function inEditionOrder(Collection $chunks, array $rank): Collection
    {
        return $chunks->sortBy(fn (IslamicSourceChunk $c) => [$rank[$c->islamic_source_id] ?? 99, $c->surah_number, $c->ayah_from])->values();
    }

    private function byReference(QuestionAnalysis $analysis, array $quranIds, array $rank): Collection
    {
        $references = array_slice($analysis->entities['references'], 0, 4);
        if ($references === []) {
            return collect();
        }
        $chunks = $this->chunks($quranIds)->where(function ($q) use ($references) {
            foreach ($references as $r) {
                $q->orWhere(fn ($q) => $q->where('surah_number', $r['surah'])->whereBetween('ayah_from', [$r['ayah_from'], $r['ayah_to']]));
            }
        })->get();

        // Keep the order the user asked in, then edition preference.
        $order = [];
        foreach ($references as $i => $r) {
            for ($a = $r['ayah_from']; $a <= $r['ayah_to']; $a++) {
                $order["{$r['surah']}:{$a}"] ??= count($order);
            }
        }

        return $chunks->sortBy(fn (IslamicSourceChunk $c) => [$order["{$c->surah_number}:{$c->ayah_from}"] ?? 999, $rank[$c->islamic_source_id] ?? 99])->values();
    }

    private function surahOpenings(QuestionAnalysis $analysis, array $quranIds, array $rank): Collection
    {
        $surah = $analysis->entities['surahs'][0] ?? null;
        if ($surah === null) {
            return collect();
        }

        return $this->inEditionOrder(
            $this->chunks($quranIds)->where('surah_number', $surah)->where('ayah_from', '<=', self::SURAH_OPENING_VERSES)->get(),
            $rank,
        );
    }

    private function byTopic(QuestionAnalysis $analysis, array $quranIds, array $rank): Collection
    {
        $concepts = config('islamic_lexicon.concepts');
        $anchors = [];
        // Interleave the anchors of each topic so a two-topic question gets verses on both.
        $lists = array_values(array_filter(array_map(fn ($c) => $concepts[$c]['anchors'] ?? [], array_slice($analysis->entities['concepts'], 0, 3))));
        for ($i = 0; count($anchors) < self::MAX_TOPIC_VERSES && $lists !== [] && $i < 8; $i++) {
            foreach ($lists as $list) {
                if (isset($list[$i]) && count($anchors) < self::MAX_TOPIC_VERSES) {
                    $anchors[$list[$i]] = count($anchors);
                }
            }
        }
        if ($anchors === []) {
            return collect();
        }
        $pairs = array_map(fn ($k) => array_map('intval', explode(':', $k)), array_keys($anchors));
        $chunks = $this->chunks($quranIds)->where(function ($q) use ($pairs) {
            foreach ($pairs as [$s, $a]) {
                $q->orWhere(fn ($q) => $q->where('surah_number', $s)->where('ayah_from', $a));
            }
        })->get();

        return $chunks->sortBy(fn (IslamicSourceChunk $c) => [$anchors["{$c->surah_number}:{$c->ayah_from}"] ?? 99, $rank[$c->islamic_source_id] ?? 99])->values();
    }

    /** @return array<int, array{0: IslamicSourceChunk, 1: float}> */
    private function textSearch(QuestionAnalysis $analysis, int $limit, array $sourceIds, ?int $surah = null): array
    {
        $scope = fn ($q) => $surah === null ? $q : $q->where('surah_number', $surah);
        $hits = [];
        if ($analysis->searchBangla !== []) {
            $hits = $this->banglaSearch($analysis->searchBangla, $limit, $sourceIds, $scope);
        }
        $left = $limit - count($hits);
        if ($analysis->searchEnglish !== [] && $left > 0) {
            $english = DB::getDriverName() === 'mysql'
                ? $this->fullText($analysis->searchEnglish, $left, $sourceIds, $scope)
                : $this->likeSearch($analysis->searchEnglish, $left, $sourceIds, $scope);
            $hits = [...$hits, ...$english];
        }

        return $hits;
    }

    /**
     * FULLTEXT only matches whole Bangla tokens, so it misses inflected forms and spelling
     * variants. Bangla uses substring matching, ranked by how many terms a passage contains.
     */
    private function banglaSearch(array $terms, int $limit, array $sourceIds, callable $scope): array
    {
        $parts = [];
        $bindings = [];
        foreach (array_slice($terms, 0, 8) as $term) {
            $variants = $this->banglaVariants($term);
            $parts[] = '('.implode(' OR ', array_fill(0, count($variants), 'content LIKE ?')).')';
            foreach ($variants as $variant) {
                $bindings[] = '%'.addcslashes($variant, '%_').'%';
            }
        }

        $rows = $scope($this->chunks($sourceIds))
            ->select('islamic_source_chunks.*')
            ->selectRaw('('.implode(' + ', $parts).') as score', $bindings)
            ->whereRaw('('.implode(' OR ', $parts).')', $bindings)
            ->orderByDesc('score')
            ->orderBy('id')
            ->limit($limit)
            ->get();
        $max = max(1, (float) $rows->max('score'));

        return $rows->map(fn ($c) => [$c, round(0.7 * (float) $c->score / $max, 3)])->all();
    }

    /** @return array<int, string> */
    private function banglaVariants(string $keyword): array
    {
        $variants = [$keyword];
        foreach (self::BANGLA_SUFFIXES as $suffix) {
            $stem = mb_substr($keyword, 0, mb_strlen($keyword) - mb_strlen($suffix));
            if (str_ends_with($keyword, $suffix) && mb_strlen($stem) >= 3) {
                $variants[] = $stem;
                break;
            }
        }
        foreach (self::BANGLA_SPELLINGS as [$from, $to]) {
            foreach ($variants as $variant) {
                $variants[] = str_replace($from, $to, $variant);
            }
            $variants = array_values(array_unique($variants));
        }

        return array_slice($variants, 0, 8);
    }

    private function fullText(array $terms, int $limit, array $sourceIds, callable $scope): array
    {
        // Boolean mode with quoted terms keeps user text from becoming query operators.
        $query = implode(' ', array_map(fn (string $k) => '"'.str_replace('"', '', $k).'"', array_slice($terms, 0, 16)));

        $rows = $scope($this->chunks($sourceIds))
            ->select('islamic_source_chunks.*')
            ->selectRaw('MATCH(content) AGAINST(? IN BOOLEAN MODE) as score', [$query])
            ->whereRaw('MATCH(content) AGAINST(? IN BOOLEAN MODE)', [$query])
            ->orderByDesc('score')
            ->limit($limit)
            ->get();
        $max = max(0.0001, (float) $rows->max('score'));

        return $rows->map(fn ($c) => [$c, round(0.7 * (float) $c->score / $max, 3)])->all();
    }

    /** Fallback for databases without FULLTEXT, such as the SQLite test database. */
    private function likeSearch(array $terms, int $limit, array $sourceIds, callable $scope): array
    {
        $terms = array_map('mb_strtolower', $terms);
        $needed = min(2, count($terms));

        return $scope($this->chunks($sourceIds))
            ->where(function ($q) use ($terms) {
                foreach ($terms as $k) {
                    $q->orWhere('content', 'like', '%'.addcslashes($k, '%_').'%');
                }
            })
            ->limit(200)
            ->get()
            ->map(fn (IslamicSourceChunk $c) => [$c, collect($terms)->filter(fn ($k) => str_contains(mb_strtolower($c->content), $k))->count()])
            ->filter(fn ($hit) => $hit[1] >= $needed)
            ->sortByDesc(fn ($hit) => $hit[1])
            ->take($limit)
            ->map(fn ($hit) => [$hit[0], round(0.7 * $hit[1] / max(1, count($terms)), 3)])
            ->values()
            ->all();
    }

    /** Approved Tafsir entries whose verse range covers a verse already found. */
    private function tafsirFor(array $found, array $tafsirIds, int $limit): Collection
    {
        $verses = collect($found)->filter(fn ($hit) => $hit[0]->source->source_type === 'quran' && $hit[2] !== RetrievedChunk::MATCH_KEYWORD)
            ->take(3)->map(fn ($hit) => [$hit[0]->surah_number, $hit[0]->ayah_from])->values()->all();
        if ($verses === []) {
            return collect();
        }

        return $this->chunks($tafsirIds)->where(function ($q) use ($verses) {
            foreach ($verses as [$s, $a]) {
                $q->orWhere(fn ($q) => $q->where('surah_number', $s)->where('ayah_from', '<=', $a)->where('ayah_to', '>=', $a));
            }
        })->orderBy('surah_number')->orderBy('ayah_from')->limit($limit)->get();
    }
}
