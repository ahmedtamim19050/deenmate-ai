<?php

namespace App\Services\AI;

use App\Models\IslamicSourceChunk;
use App\Models\QuranTranslationEdition;
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

    private const BANGLA_STOP_WORDS = [
        'কুরআন', 'কোরআন', 'কুরআনে', 'কোরআনে', 'সূরা', 'সুরা', 'আয়াত', 'হাদিস', 'হাদীস', 'ইসলাম', 'ইসলামে', 'মুসলিম', 'মুসলমান',
        'সম্পর্কে', 'বিষয়ে', 'নিয়ে', 'কিভাবে', 'কীভাবে', 'কেন', 'কখন', 'কোথায়', 'কোন', 'কোনো', 'একটি', 'এবং', 'অথবা', 'আমি', 'আমার',
        'আমাকে', 'আপনি', 'আপনার', 'তুমি', 'তোমার', 'করতে', 'করব', 'করবো', 'করে', 'করি', 'হবে', 'হয়', 'বলে', 'বলা', 'বলেন', 'বলুন',
        'আছে', 'নেই', 'দিন', 'দাও', 'মানে', 'অর্থ',
    ];

    private const BANGLA_SUFFIXES = ['গুলোর', 'গুলো', 'দের', 'ের', 'কে', 'তে'];

    /** Spellings that differ across Bangla texts, such as নামাজ and নামায. */
    private const BANGLA_SPELLINGS = [['জ', 'য'], ['য', 'জ'], ['র্য্য', 'র্য'], ['র্য', 'র্য্য'], ['ী', 'ি'], ['ূ', 'ু']];

    /** The rest of the retrieval limit is filled with Quran passages first. */
    private const OTHER_SOURCE_SLOTS = 2;

    public static function asciiDigits(string $text): string
    {
        return strtr($text, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    }

    /** @return Collection<int, RetrievedChunk> */
    public function search(string $question, ?int $limit = null): Collection
    {
        $limit ??= (int) config('deenmate.ai.retrieval_limit');
        $question = self::asciiDigits($question);
        $byReference = $this->byReference($question);
        $keywords = $this->keywords($question);

        $remaining = $limit - $byReference->count();
        $quran = collect();
        $others = collect();
        if ($keywords !== [] && $remaining > 0) {
            $quran = $this->textSearch($keywords, max(1, $remaining - self::OTHER_SOURCE_SLOTS), fn ($q) => $q->where('source_type', 'quran'));
            $left = min($remaining - $quran->count(), self::OTHER_SOURCE_SLOTS);
            if ($left > 0) {
                $others = $this->textSearch($keywords, $left, fn ($q) => $q->where('source_type', '!=', 'quran'));
            }
        }

        return $byReference->concat($quran)->concat($others)
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
            fn (string $t) => mb_strlen($t) >= 3 && ! in_array($t, self::STOP_WORDS, true) && ! in_array($t, self::BANGLA_STOP_WORDS, true),
        )));
    }

    private function approved(?callable $scope = null)
    {
        // Checked on every query: a translation is used only while its edition is approved and its
        // AI copy matches the reviewed content. A revoked, stale or failed copy is excluded.
        $eligible = QuranTranslationEdition::query()->aiEligible()->pluck('identifier')->all();
        $unapprovedEditions = QuranTranslationEdition::query()->whereNotIn('identifier', $eligible)
            ->pluck('identifier')->map(fn ($id) => "edition:{$id}")->all();

        return IslamicSourceChunk::query()
            ->whereHas('source', function ($q) use ($scope, $unapprovedEditions) {
                $q->where('verification_status', 'approved');
                if ($unapprovedEditions !== []) {
                    $q->whereNotIn('reference_identifier', $unapprovedEditions);
                }
                if ($scope) {
                    $scope($q);
                }
            })
            ->with('source');
    }

    private function textSearch(array $keywords, int $limit, callable $scope): Collection
    {
        $isBangla = fn (string $k) => preg_match('/\p{Bengali}/u', $k) === 1;
        $bangla = array_values(array_filter($keywords, $isBangla));
        $other = array_values(array_filter($keywords, fn (string $k) => ! $isBangla($k)));

        $found = $bangla === [] ? collect() : $this->banglaSearch($bangla, $limit, $scope);
        $left = $limit - $found->count();
        if ($other !== [] && $left > 0) {
            $found = $found->concat(DB::getDriverName() === 'mysql'
                ? $this->fullText($other, $left, $scope)
                : $this->likeSearch($other, $left, $scope));
        }

        return $found;
    }

    /**
     * FULLTEXT only matches whole Bangla tokens, so it misses inflected forms and spelling
     * variants. Bangla uses substring matching, ranked by how many keywords a passage contains.
     */
    private function banglaSearch(array $keywords, int $limit, callable $scope): Collection
    {
        $parts = [];
        $bindings = [];
        foreach (array_slice($keywords, 0, 8) as $keyword) {
            $variants = $this->banglaVariants($keyword);
            $parts[] = '('.implode(' OR ', array_fill(0, count($variants), 'content LIKE ?')).')';
            foreach ($variants as $variant) {
                $bindings[] = '%'.addcslashes($variant, '%_').'%';
            }
        }

        return $this->approved($scope)
            ->select('islamic_source_chunks.*')
            ->selectRaw('('.implode(' + ', $parts).') as score', $bindings)
            ->whereRaw('('.implode(' OR ', $parts).')', $bindings)
            ->orderByDesc('score')
            ->orderBy('id')
            ->limit($limit)
            ->get();
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

    private function byReference(string $question): Collection
    {
        if (! preg_match_all('/\b(\d{1,3}):(\d{1,3})\b/', $question, $m, PREG_SET_ORDER)) {
            return collect();
        }
        $references = array_map(fn ($hit) => "Quran {$hit[1]}:{$hit[2]}", array_slice($m, 0, 4));

        return $this->approved()->whereIn('reference', $references)->limit(6)->get();
    }

    private function fullText(array $keywords, int $limit, ?callable $scope = null): Collection
    {
        // Boolean mode with quoted terms keeps user text from becoming query operators.
        $terms = implode(' ', array_map(fn (string $k) => '"'.str_replace('"', '', $k).'"', array_slice($keywords, 0, 12)));

        return $this->approved($scope)
            ->select('islamic_source_chunks.*')
            ->selectRaw('MATCH(content) AGAINST(? IN BOOLEAN MODE) as score', [$terms])
            ->whereRaw('MATCH(content) AGAINST(? IN BOOLEAN MODE)', [$terms])
            ->orderByDesc('score')
            ->limit($limit)
            ->get();
    }

    /** Fallback for databases without FULLTEXT, such as the SQLite test database. */
    private function likeSearch(array $keywords, int $limit, ?callable $scope = null): Collection
    {
        $needed = min(2, count($keywords));

        return $this->approved($scope)
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
