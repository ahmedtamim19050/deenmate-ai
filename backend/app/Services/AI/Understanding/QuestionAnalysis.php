<?php

namespace App\Services\AI\Understanding;

/** Everything the assistant knows about a question before retrieval. Built only from the question and conversation, never from model output. */
final class QuestionAnalysis
{
    /**
     * @param  array{references: array, surahs: array, concepts: array, prophets: array, hadith: array}  $entities
     * @param  array<int, string>  $secondaryIntents
     * @param  array<int, string>  $searchEnglish  words to search for in English sources
     * @param  array<int, string>  $searchBangla  words to search for in Bangla sources
     */
    public function __construct(
        public readonly NormalizedQuestion $question,
        public readonly string $primaryIntent,
        public readonly array $secondaryIntents,
        public readonly array $entities,
        public readonly float $confidence,
        public readonly bool $rulingCaution,
        public readonly bool $contextUsed,
        public readonly array $searchEnglish,
        public readonly array $searchBangla,
    ) {}

    public function is(string $intent): bool
    {
        return $this->primaryIntent === $intent || in_array($intent, $this->secondaryIntents, true);
    }

    public function answerLanguage(): string
    {
        return $this->question->answerLanguage;
    }

    /** Stored with the reply and returned to the app. Contains no source or reviewer data. */
    public function toArray(): array
    {
        return [
            'original' => $this->question->original,
            'normalized' => $this->question->normalized,
            'language' => $this->question->language,
            'answer_language' => $this->question->answerLanguage,
            'intent' => $this->primaryIntent,
            'secondary_intents' => $this->secondaryIntents,
            'entities' => [
                'references' => array_map(fn ($r) => ['surah' => $r['surah'], 'ayah_from' => $r['ayah_from'], 'ayah_to' => $r['ayah_to']], $this->entities['references']),
                'surahs' => $this->entities['surahs'],
                'concepts' => $this->entities['concepts'],
                'prophets' => $this->entities['prophets'],
                'hadith' => $this->entities['hadith'],
            ],
            'negated' => $this->question->negated(),
            'confidence' => $this->confidence,
            'context_used' => $this->contextUsed,
        ];
    }
}
