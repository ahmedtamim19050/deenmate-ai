<?php

namespace App\Services\AI\Understanding;

/**
 * Deterministic intent classification, so routing can't be steered by the model or spend tokens.
 * A question gets one primary intent and any number of secondary ones; a question that matches
 * nothing stays general_islamic_question with low confidence instead of being forced into a topic.
 */
class QuestionIntentClassifier
{
    public const GREETING = 'greeting';

    public const PERSONAL = 'personal_progress';

    public const VERSE_LOOKUP = 'quran_verse_lookup';

    public const SURAH_SUMMARY = 'surah_summary';

    public const QURAN_TOPIC = 'quran_topic_search';

    public const TAFSIR = 'tafsir_lookup';

    public const HADITH = 'hadith_lookup';

    public const FIQH = 'fiqh_question';

    public const HALAL_HARAM = 'halal_haram';

    public const GUIDANCE = 'islamic_guidance';

    public const GENERAL = 'general_islamic_question';

    /** Intents whose answers carry the "not a fatwa" notice. */
    public const RULING_INTENTS = [self::FIQH, self::HALAL_HARAM];

    private const RULING_TERMS = [
        'halal', 'haram', 'fatwa', 'ruling', 'permissible', 'allowed', 'forbidden', 'is it a sin', 'am i allowed', 'can i ',
        'divorce', 'talaq', 'talak', 'inheritance', 'makruh', 'mustahab', 'wajib', 'fard', 'farz', 'jayez', 'jaiz', 'najayez',
        'hobe ki', 'jabe ki', 'parbo ki', 'korle ki', 'niyom', 'masala', 'masail', 'vang', 'bhang', 'nullify', 'invalidate', 'ভাঙ', 'মাসআলা', 'হালাল', 'হারাম', 'ফতোয়া', 'ফরজ',
        'জায়েজ', 'জায়েজ', 'নিয়ম', 'جائز', 'حرام', 'حلال', 'فتوى',
    ];

    private const SUMMARY_TERMS = ['main message', 'summary', 'summarise', 'summarize', 'theme', 'themes', 'about', 'mul bani', 'mul bisoy', 'bisoybostu', 'ki niye', 'shikkha', 'sikkha', 'lesson', 'lessons', 'মূল', 'বিষয়', 'বিষয়বস্তু', 'শিক্ষা', 'সারাংশ'];

    private const PERSONAL_PATTERN = '/\b(my|mine|amar)\b.*\b(progress|history|record|records|activity|activities|prayers|salah|namaz|namaj|streak|hisab)\b/u';

    private const GREETING_PATTERN = '/^(as-?salaam?u?\s*alaikum|assalamu\s*alaikum|assalamualaikum|salam|salaam|hello|hi|hey|السلام عليكم|আসসালামু আলাইকুম|সালাম)[\s!.,]*$/u';

    /**
     * @param  array{references: array, surahs: array, concepts: array, prophets: array, hadith: array}  $entities
     * @return array{primary: string, secondary: array<int, string>, confidence: float, ruling_caution: bool}
     */
    public function classify(NormalizedQuestion $question, array $entities): array
    {
        $text = mb_strtolower(trim($question->original));

        if (preg_match(self::GREETING_PATTERN, $text)) {
            return ['primary' => self::GREETING, 'secondary' => [], 'confidence' => 1.0, 'ruling_caution' => false];
        }
        if (preg_match(self::PERSONAL_PATTERN, $text)) {
            return ['primary' => self::PERSONAL, 'secondary' => [], 'confidence' => 0.9, 'ruling_caution' => false];
        }

        $concepts = config('islamic_lexicon.concepts');
        $intents = [];
        $confidence = 0.35;

        $ruling = false;
        foreach (self::RULING_TERMS as $term) {
            if (str_contains($text, $term)) {
                $ruling = true;
                break;
            }
        }

        $mentions = fn (string $concept) => in_array($concept, $entities['concepts'], true);

        if ($entities['references'] !== []) {
            $intents[] = $mentions('tafsir') ? self::TAFSIR : self::VERSE_LOOKUP;
            $confidence = 0.95;
        } elseif ($entities['surahs'] !== []) {
            $summary = false;
            foreach (self::SUMMARY_TERMS as $term) {
                $summary = $summary || str_contains($text, $term);
            }
            $intents[] = $mentions('tafsir') ? self::TAFSIR : ($summary || count($entities['concepts']) === 0 ? self::SURAH_SUMMARY : self::QURAN_TOPIC);
            $confidence = 0.85;
        }
        if ($entities['hadith'] !== [] || $mentions('hadith')) {
            $intents[] = self::HADITH;
            $confidence = max($confidence, 0.8);
        }

        // Topic intents in the order the concepts appear. Vocabulary-only concepts (quran, hadith,
        // tafsir) say where to look, not what about.
        foreach ($entities['concepts'] as $concept) {
            if (in_array($concept, ['quran', 'hadith', 'tafsir'], true)) {
                continue;
            }
            $intents[] = $concepts[$concept]['intent'];
            $confidence = max($confidence, 0.75);
        }
        if ($entities['prophets'] !== []) {
            $intents[] = 'prophets';
            $confidence = max($confidence, 0.75);
        }
        if ($ruling) {
            $intents[] = in_array(self::HALAL_HARAM, $intents, true) ? self::HALAL_HARAM : self::FIQH;
        }
        if ($mentions('quran') && ! in_array(self::VERSE_LOOKUP, $intents, true)) {
            $intents[] = self::QURAN_TOPIC;
            $confidence = max($confidence, 0.6);
        }

        $intents = array_values(array_unique($intents));
        if ($intents === []) {
            return ['primary' => self::GENERAL, 'secondary' => [], 'confidence' => $confidence, 'ruling_caution' => false];
        }

        // A ruling question about prayer is a salah question first, with a fiqh secondary intent.
        $primary = $intents[0];
        if (in_array($primary, [self::QURAN_TOPIC], true) && count($intents) > 1) {
            $primary = $intents[1];
        }
        $secondary = array_values(array_diff($intents, [$primary]));
        $rulingCaution = $ruling || in_array($primary, self::RULING_INTENTS, true) || array_intersect($secondary, self::RULING_INTENTS) !== [];

        return ['primary' => $primary, 'secondary' => $secondary, 'confidence' => round($confidence, 2), 'ruling_caution' => $rulingCaution];
    }
}
