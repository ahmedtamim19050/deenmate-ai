<?php

namespace App\Services\AI\Understanding;

use App\Services\Quran\QuranStructure;

/**
 * question -> normalization -> entities -> intent -> search terms.
 *
 * Works without any AI provider. Earlier turns are used only to resolve a follow-up such as
 * "er tafsir ki?" or "next ayah": they add places to look, and the answer still has to come from
 * evidence retrieved for this turn.
 */
class QuestionUnderstandingService
{
    private const FOLLOW_UP_PATTERN = '/\b(this|that|it|these|next|previous|more|again|explain|eta|ota|ei|oi|tar|er\s+tafsir|porer|ager|agher|aro|bujhiye|bujhao)\b|এটা|এটি|এই আয়াত|এই আয়াত|পরের|আগের|আরও|আরো|ব্যাখ্যা/u';

    private const ENGLISH_STOP_WORDS = [
        'a', 'an', 'and', 'are', 'about', 'as', 'at', 'be', 'by', 'can', 'do', 'does', 'for', 'from', 'how', 'i', 'in', 'is', 'it',
        'me', 'my', 'of', 'on', 'or', 'please', 'say', 'says', 'tell', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where',
        'which', 'who', 'why', 'with', 'you', 'your', 'islam', 'islamic', 'muslim', 'muslims', 'explain', 'mean', 'means', 'meaning',
        'quran', 'surah', 'verse', 'verses', 'ayah', 'hadith', 'give', 'know', 'want', 'should', 'would', 'could', 'there', 'their',
        'allah', 'god', 'many', 'much', 'did', 'does', 'have', 'has', 'will', 'our', 'we', 'us', 'they', 'them',
    ];

    private const BANGLA_STOP_WORDS = [
        'কুরআন', 'কোরআন', 'কুরআনে', 'কোরআনে', 'সূরা', 'সুরা', 'আয়াত', 'হাদিস', 'হাদীস', 'ইসলাম', 'ইসলামে', 'মুসলিম', 'মুসলমান',
        'সম্পর্কে', 'বিষয়ে', 'নিয়ে', 'কিভাবে', 'কীভাবে', 'কেন', 'কখন', 'কোথায়', 'কোন', 'কোনো', 'একটি', 'এবং', 'অথবা', 'আমি', 'আমার',
        'আমাকে', 'আপনি', 'আপনার', 'তুমি', 'তোমার', 'করতে', 'করব', 'করবো', 'করে', 'করি', 'হবে', 'হয়', 'বলে', 'বলা', 'বলেন', 'বলুন',
        'আছে', 'নেই', 'দিন', 'দাও', 'মানে', 'অর্থ', 'কী', 'কি', 'আল্লাহ',
    ];

    public function __construct(
        private BanglishQuestionNormalizer $normalizer,
        private IslamicEntityExtractor $extractor,
        private QuestionIntentClassifier $classifier,
    ) {}

    /** @param  array<string, mixed>|null  $previous  the analysis stored with the previous answer in this conversation */
    public function analyze(string $question, ?array $previous = null): QuestionAnalysis
    {
        $normalized = $this->normalizer->normalize($question);
        $entities = $this->extractor->extract($normalized);
        [$entities, $contextUsed] = $this->withContext($normalized, $entities, $previous);
        $intent = $this->classifier->classify($normalized, $entities);

        return new QuestionAnalysis(
            question: $normalized,
            primaryIntent: $intent['primary'],
            secondaryIntents: $intent['secondary'],
            entities: $entities,
            confidence: $contextUsed ? round($intent['confidence'] * 0.85, 2) : $intent['confidence'],
            rulingCaution: $intent['ruling_caution'],
            contextUsed: $contextUsed,
            searchEnglish: $this->englishTerms($normalized, $entities),
            searchBangla: $this->banglaTerms($normalized, $entities),
        );
    }

    /** @return array{0: array, 1: bool} */
    private function withContext(NormalizedQuestion $question, array $entities, ?array $previous): array
    {
        $previousEntities = $previous['entities'] ?? null;
        $isFollowUp = $previousEntities
            && $entities['references'] === [] && $entities['surahs'] === []
            && preg_match(self::FOLLOW_UP_PATTERN, mb_strtolower($question->original)) === 1;
        if (! $isFollowUp) {
            return [$entities, false];
        }

        $text = mb_strtolower($question->original);
        $next = preg_match('/\b(next|porer)\b|পরের/u', $text) === 1;
        $before = preg_match('/\b(previous|ager|agher)\b|আগের/u', $text) === 1;
        foreach ($previousEntities['references'] ?? [] as $r) {
            $from = (int) $r['ayah_from'];
            $to = (int) $r['ayah_to'];
            if ($next) {
                $from = $to = $to + 1;
            } elseif ($before) {
                $from = $to = $from - 1;
            }
            if (QuranStructure::exists((int) $r['surah'], $from)) {
                $entities['references'][] = ['surah' => (int) $r['surah'], 'ayah_from' => $from, 'ayah_to' => $to, 'via' => 'conversation'];
            }
        }
        $entities['surahs'] = array_values(array_unique([...$entities['surahs'], ...($previousEntities['surahs'] ?? [])]));
        // Topics carry over only when the follow-up names none of its own.
        if ($entities['concepts'] === [] || $entities['concepts'] === ['tafsir']) {
            $entities['concepts'] = array_values(array_unique([...$entities['concepts'], ...($previousEntities['concepts'] ?? [])]));
        }

        return [$entities, true];
    }

    private function englishTerms(NormalizedQuestion $question, array $entities): array
    {
        $concepts = config('islamic_lexicon.concepts');
        $prophets = config('islamic_lexicon.prophets');
        $terms = [];
        foreach ($entities['concepts'] as $c) {
            array_push($terms, ...($concepts[$c]['search_en'] ?? []));
        }
        foreach ($entities['prophets'] as $p) {
            $terms[] = $prophets[$p]['en'];
        }
        // Unrecognised Latin words are searched too: in a Banglish question they are usually English
        // words ("throne"). Banglish particles were recognised already and are never searched.
        $minimum = $question->language === NormalizedQuestion::LANG_BANGLISH ? 4 : 3;
        foreach ($question->tokens as $t) {
            if ($t['kind'] === 'word' && preg_match('/^[a-z]{'.$minimum.',}$/', $t['text']) && ! in_array($t['text'], self::ENGLISH_STOP_WORDS, true)
                && ! in_array($t['text'], config('islamic_lexicon.banglish_markers'), true)) {
                $terms[] = $t['text'];
            }
        }

        return array_values(array_unique($terms));
    }

    private function banglaTerms(NormalizedQuestion $question, array $entities): array
    {
        $concepts = config('islamic_lexicon.concepts');
        $terms = [];
        foreach ($entities['concepts'] as $c) {
            array_push($terms, ...($concepts[$c]['search_bn'] ?? []));
        }
        foreach ($question->tokens as $t) {
            if (preg_match('/\p{Bengali}/u', $t['text']) && in_array($t['kind'], ['word', 'concept', 'prophet'], true)
                && mb_strlen($t['text']) >= 3 && ! in_array($t['text'], self::BANGLA_STOP_WORDS, true)) {
                $terms[] = $t['text'];
            }
        }

        return array_values(array_unique($terms));
    }
}
