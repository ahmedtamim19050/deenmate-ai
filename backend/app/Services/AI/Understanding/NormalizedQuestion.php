<?php

namespace App\Services\AI\Understanding;

/** The output of BanglishQuestionNormalizer. The original text is always kept unchanged. */
final class NormalizedQuestion
{
    public const LANG_BANGLA = 'bn';

    public const LANG_BANGLISH = 'banglish';

    public const LANG_ENGLISH = 'en';

    public const LANG_MIXED = 'mixed';

    public const LANG_ARABIC = 'ar';

    /**
     * @param  array<int, array{text: string, kind: string, value: ?string}>  $tokens  kind: concept|prophet|function|negation|number|word
     * @param  array<int, string>  $negations  negation words exactly as typed
     */
    public function __construct(
        public readonly string $original,
        public readonly string $normalized,
        public readonly string $language,
        public readonly string $answerLanguage,
        public readonly bool $answerLanguageRequested,
        public readonly array $tokens,
        public readonly array $negations,
    ) {}

    public function negated(): bool
    {
        return $this->negations !== [];
    }

    /** @return array<int, string> concept keys, in the order they appear */
    public function concepts(): array
    {
        return $this->values('concept');
    }

    /** @return array<int, string> */
    public function prophets(): array
    {
        return $this->values('prophet');
    }

    /** @return array<int, string> words that are neither vocabulary nor particles, as typed */
    public function otherWords(): array
    {
        return array_values(array_unique(array_map(fn ($t) => $t['text'], array_filter($this->tokens, fn ($t) => $t['kind'] === 'word'))));
    }

    private function values(string $kind): array
    {
        return array_values(array_unique(array_map(fn ($t) => $t['value'], array_filter($this->tokens, fn ($t) => $t['kind'] === $kind))));
    }
}
