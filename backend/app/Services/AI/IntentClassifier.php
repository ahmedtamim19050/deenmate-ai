<?php

namespace App\Services\AI;

/**
 * Deterministic, so routing cannot be steered by the model or spend tokens.
 * Order matters: a personal ruling that mentions the Quran is still a ruling.
 */
class IntentClassifier
{
    public const GREETING = 'greeting';

    public const RULING = 'ruling';

    public const QURAN = 'quran';

    public const HADITH = 'hadith';

    public const PERSONAL = 'personal_progress';

    public const GENERAL = 'general_knowledge';

    private const RULING_TERMS = [
        'halal', 'haram', 'fatwa', 'ruling', 'permissible', 'allowed', 'forbidden', 'sinful', 'is it a sin', 'am i allowed',
        'can i', 'should i divorce', 'divorce', 'talaq', 'inheritance', 'zakat on', 'makruh', 'mustahab', 'wajib', 'fard or',
        'হালাল', 'হারাম', 'ফতোয়া', 'جائز', 'حرام', 'حلال', 'فتوى',
    ];

    public function classify(string $question): string
    {
        $q = mb_strtolower(trim($question));

        if (preg_match('/^(as-?salaam?u?\s*alaikum|assalamu alaikum|salam|salaam|hello|hi|hey|السلام عليكم|আসসালামু আলাইকুম)[\s!.,]*$/u', $q)) {
            return self::GREETING;
        }
        if (preg_match('/\b(my|mine)\b.*\b(progress|history|record|activity|activities|prayers|salah)\b/u', $q)) {
            return self::PERSONAL;
        }
        foreach (self::RULING_TERMS as $term) {
            if (str_contains($q, $term)) {
                return self::RULING;
            }
        }
        if (preg_match('/\b(quran|qur\'an|koran|surah|sura|ayah|ayat|verse)\b|কুরআন|সূরা|قرآن|سورة|آية|\b\d{1,3}:\d{1,3}\b/u', $q)) {
            return self::QURAN;
        }
        if (preg_match('/\b(hadith|hadeeth|sunnah|bukhari|muslim said|tirmidhi|abu dawud|nasai|ibn majah|narrated)\b|হাদিস|حديث/u', $q)) {
            return self::HADITH;
        }

        return self::GENERAL;
    }
}
