<?php

namespace App\Services\AI\Understanding;

use App\Services\AI\SourceRetriever;

/**
 * Deterministic, token-level normalization for Bangla, Banglish, English and mixed questions.
 *
 * Each word is recognised as a whole (with its grammatical ending), never by blind character
 * replacement, so names, numbers, surah references and negation words stay exactly as typed.
 * The normalized form is a readable gloss for the model and the logs; retrieval works from the
 * recognised tokens, and the original question is always kept.
 */
class BanglishQuestionNormalizer
{
    private const LATIN_ENDINGS = ['gulo', 'guli', 'der', 'era', 'er', 'ra', 'te', 'ke', 'r', 'e', 's', 'o'];

    private const BANGLA_ENDINGS = ['গুলোর', 'গুলো', 'দের', 'য়ের', 'য়ের', 'ের', 'কে', 'তে', 'র', 'ে', 'ও'];

    private const ENGLISH_MARKERS = ['what', 'why', 'how', 'is', 'are', 'the', 'does', 'do', 'say', 'about', 'which', 'when', 'who', 'can', 'should', 'of', 'in', 'my', 'and'];

    /** @var array<string, array{0: string, 1: string}>|null */
    private ?array $vocabulary = null;

    /** @var array<string, array{0: string, 1: string}>|null multi-word entries, keyed by their words joined with a space */
    private ?array $phrases = null;

    public function normalize(string $question): NormalizedQuestion
    {
        $lexicon = config('islamic_lexicon');
        $text = mb_strtolower(SourceRetriever::asciiDigits($question));
        // Apostrophes inside a word are spelling, not punctuation: qur'an, don't.
        $text = preg_replace("/(\p{L})['’`](\p{L})/u", '$1$2', $text) ?? $text;
        $words = preg_split('/[^\p{L}\p{M}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];
        $negations = [];
        $negationWords = array_merge($lexicon['negations']['latin'], $lexicon['negations']['bangla']);
        $negationKeys = array_map(fn ($w) => self::banglaKey(str_replace("'", '', $w)), $negationWords);
        $functionWords = $lexicon['function_words'];

        for ($i = 0, $n = count($words); $i < $n; $i++) {
            // Two-word entries first, such as "ma baba" or "mara gele".
            if ($i + 1 < $n && ($hit = $this->lookup($words[$i].' '.$words[$i + 1], true))) {
                $tokens[] = ['text' => $words[$i].' '.$words[$i + 1], 'kind' => $hit[0], 'value' => $hit[1]];
                $i++;

                continue;
            }
            $word = $words[$i];
            if (in_array(self::banglaKey($word), $negationKeys, true)) {
                $tokens[] = ['text' => $word, 'kind' => 'negation', 'value' => 'not'];
                $negations[] = $word;
            } elseif (preg_match('/^\d+$/', $word)) {
                $tokens[] = ['text' => $word, 'kind' => 'number', 'value' => $word];
            } elseif ($hit = $this->lookup($word)) {
                $tokens[] = ['text' => $word, 'kind' => $hit[0], 'value' => $hit[1]];
            } elseif (isset($functionWords[$word])) {
                $tokens[] = ['text' => $word, 'kind' => 'function', 'value' => $functionWords[$word]];
            } else {
                $tokens[] = ['text' => $word, 'kind' => 'word', 'value' => null];
            }
        }

        $language = $this->detectLanguage($question, $tokens);
        [$answerLanguage, $requested] = $this->answerLanguage($text, $language);

        return new NormalizedQuestion(
            original: $question,
            normalized: $this->render($tokens, $language, $question),
            language: $language,
            answerLanguage: $answerLanguage,
            answerLanguageRequested: $requested,
            tokens: $tokens,
            negations: $negations,
        );
    }

    /**
     * Folds Bangla spelling variants (য/জ, ী/ি, ূ/ু, doubled জ) so নামায and নামাজ compare equal.
     * য় is a different letter (the "y" sound) and is kept apart, so হয় ("is") never becomes হজ (Hajj).
     */
    public static function banglaKey(string $word): string
    {
        // য় is written precomposed (U+09DF) or as য + nukta; give both one form outside the fold.
        $word = str_replace(["\u{09DF}", "য\u{09BC}"], "\u{0001}", $word);
        $word = str_replace("\u{09BC}", '', $word);
        $word = str_replace(['য', 'ী', 'ূ', 'জ্জ'], ['জ', 'ি', 'ু', 'জ'], $word);

        return str_replace("\u{0001}", 'য়', $word);
    }

    /** @return array{0: string, 1: string}|null [kind, value] */
    private function lookup(string $word, bool $phrase = false): ?array
    {
        $this->buildVocabulary();
        $table = $phrase ? $this->phrases : $this->vocabulary;
        $isBangla = preg_match('/\p{Bengali}/u', $word) === 1;
        $key = $isBangla ? self::banglaKey($word) : $word;
        if (isset($table[$key])) {
            return $table[$key];
        }
        // A known stem with a grammatical ending: namajer, rojar, জান্নাতের, পিতামাতার.
        foreach ($isBangla ? self::BANGLA_ENDINGS : self::LATIN_ENDINGS as $ending) {
            $endingKey = $isBangla ? self::banglaKey($ending) : $ending;
            if (str_ends_with($key, $endingKey) && mb_strlen($key) - mb_strlen($endingKey) >= ($isBangla ? 2 : 3)) {
                $stem = mb_substr($key, 0, mb_strlen($key) - mb_strlen($endingKey));
                if (isset($table[$stem])) {
                    return $table[$stem];
                }
            }
        }

        return null;
    }

    private function buildVocabulary(): void
    {
        if ($this->vocabulary !== null) {
            return;
        }
        $this->vocabulary = [];
        $this->phrases = [];
        $add = function (string $variant, string $kind, string $value) {
            $isBangla = preg_match('/\p{Bengali}/u', $variant) === 1;
            $key = mb_strtolower($isBangla ? self::banglaKey($variant) : $variant);
            $key = preg_replace('/[\s\-]+/u', ' ', $key);
            if (str_contains($key, ' ')) {
                $this->phrases[$key] ??= [$kind, $value];
            } else {
                $this->vocabulary[$key] ??= [$kind, $value];
            }
        };
        foreach (config('islamic_lexicon.concepts') as $concept => $entry) {
            foreach ([...$entry['latin'], ...$entry['bangla']] as $variant) {
                $add($variant, 'concept', $concept);
            }
        }
        foreach (config('islamic_lexicon.prophets') as $prophet => $entry) {
            foreach ([...$entry['latin'], ...$entry['bangla']] as $variant) {
                $add($variant, 'prophet', $prophet);
            }
        }
    }

    private function detectLanguage(string $question, array $tokens): string
    {
        $bengali = preg_match_all('/\p{Bengali}/u', $question);
        $arabic = preg_match_all('/\p{Arabic}/u', $question);
        $latinWords = array_values(array_filter($tokens, fn ($t) => preg_match('/^[a-z]/', $t['text']) === 1));

        if ($bengali > 0) {
            // A Bangla sentence with a couple of English words in it is still Bangla.
            return count($latinWords) >= 2 ? NormalizedQuestion::LANG_MIXED : NormalizedQuestion::LANG_BANGLA;
        }
        if ($arabic > 0 && $latinWords === []) {
            return NormalizedQuestion::LANG_ARABIC;
        }

        $markers = config('islamic_lexicon.banglish_markers');
        $banglish = count(array_filter($latinWords, fn ($t) => in_array($t['text'], $markers, true) || in_array(explode(' ', $t['text'])[0], $markers, true)));
        $english = count(array_filter($latinWords, fn ($t) => in_array($t['text'], self::ENGLISH_MARKERS, true)));
        if ($banglish === 0 || $english > 2 * $banglish) {
            return NormalizedQuestion::LANG_ENGLISH;
        }

        return $english >= 2 ? NormalizedQuestion::LANG_MIXED : NormalizedQuestion::LANG_BANGLISH;
    }

    /** @return array{0: string, 1: bool} answer language and whether the user asked for it */
    private function answerLanguage(string $text, string $language): array
    {
        foreach (config('islamic_lexicon.language_requests') as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($text, mb_strtolower($phrase))) {
                    return [$lang, true];
                }
            }
        }

        return [match ($language) {
            NormalizedQuestion::LANG_BANGLA, NormalizedQuestion::LANG_BANGLISH => 'bn',
            NormalizedQuestion::LANG_ARABIC => 'ar',
            // Mixed questions are answered in Bangla when they contain Bangla script or Banglish.
            NormalizedQuestion::LANG_MIXED => 'bn',
            default => 'en',
        }, false];
    }

    private function render(array $tokens, string $language, string $question): string
    {
        $glosses = config('islamic_lexicon.concepts');
        $prophets = config('islamic_lexicon.prophets');
        $words = array_map(function (array $t) use ($glosses, $prophets, $language) {
            return match ($t['kind']) {
                'concept' => '['.$glosses[$t['value']]['gloss'].']',
                'prophet' => '['.$prophets[$t['value']]['en'].']',
                'negation' => 'NOT',
                // Banglish particles are glossed; Bangla script and English words stay as typed.
                'function' => $language === NormalizedQuestion::LANG_ENGLISH ? $t['text'] : $t['value'],
                default => $t['text'],
            };
        }, $tokens);
        $normalized = implode(' ', $words);
        // Keep surah:ayah references, which tokenizing splits apart.
        if (preg_match_all('/\b\d{1,3}:\d{1,3}(?:\s*[-–]\s*\d{1,3})?\b/u', SourceRetriever::asciiDigits($question), $m)) {
            $normalized .= ' ('.implode(', ', $m[0]).')';
        }

        return trim($normalized.(str_contains($question, '?') || str_contains($question, '?') ? '?' : ''));
    }
}
