<?php

namespace App\Services\AI;

/** First line of defence. The system prompt and output validator remain the real controls. */
class InputGuard
{
    private const INJECTION_PATTERNS = [
        '/\b(ignore|disregard|forget|override)\b.{0,40}\b(previous|prior|above|earlier|system|all)\b.{0,40}\b(instruction|prompt|rule|message)s?\b/isu',
        '/\b(reveal|show|print|repeat|output)\b.{0,40}\b(system prompt|your (instructions|prompt|rules))\b/isu',
        '/\byou are now\b.{0,60}\b(scholar|mufti|imam|unrestricted|dan|jailbroken)\b/isu',
        '/\b(pretend|act|roleplay)\b.{0,30}\b(you are|as) (a |an )?(mufti|scholar|imam)\b/isu',
        '/<\/?(system|source|sources|instructions?)\b[^>]*>/iu',
    ];

    public function isSuspicious(string $text): bool
    {
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /** Strips control characters. Never rewrites what the user meant to ask. */
    public function clean(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';

        return trim(mb_substr($text, 0, (int) config('deenmate.ai.max_question_chars')));
    }
}
