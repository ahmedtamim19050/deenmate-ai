<?php

namespace App\Services\Quran\Translations;

/**
 * Finds damage in translation text without changing it. Errors block an import; warnings are
 * stored for a reviewer. Legitimate characters are never flagged: the edition's own script,
 * Arabic (Quran and hadith quotations), shared punctuation and digits, and joiners.
 */
class TranslationTextInspector
{
    /** The writing system each language's text is expected in. */
    private const LANGUAGE_SCRIPT = [
        'bn' => 'Bengali',
        'en' => 'Latin',
        'ar' => 'Arabic',
        'ur' => 'Arabic',
        'fa' => 'Arabic',
        'hi' => 'Devanagari',
    ];

    /** Formatting that published footnotes may carry. Anything else is flagged for review. */
    private const FOOTNOTE_TAGS = ['b', 'strong', 'i', 'em', 'br', 'p', 'sup', 'sub', 'span', 'u'];

    /** @return list<TextIssue> */
    public function inspect(string $language, string $text, ?string $footnotes = null): array
    {
        $issues = $this->inspectField($language, 'text', $text);
        if ($footnotes !== null && $footnotes !== '') {
            $issues = array_merge($issues, $this->inspectField($language, 'footnotes', $footnotes));
        }

        return $issues;
    }

    /** @return list<TextIssue> */
    private function inspectField(string $language, string $field, string $value): array
    {
        $issues = [];
        // Closures by reference: arrow functions would capture a copy of $issues.
        $error = function (string $code, string $detail) use (&$issues, $field) {
            $issues[] = TextIssue::error($code, "{$field}: {$detail}");
        };
        $warning = function (string $code, string $detail) use (&$issues, $field) {
            $issues[] = TextIssue::warning($code, "{$field}: {$detail}");
        };

        if (! mb_check_encoding($value, 'UTF-8')) {
            $error('invalid_utf8', 'not valid UTF-8.');

            return $issues;
        }
        if ($field === 'text' && trim($value) === '') {
            $error('empty', 'no text.');

            return $issues;
        }
        if (str_contains($value, "\u{FFFD}")) {
            $error('replacement_character', 'contains U+FFFD, so it was corrupted before it reached us.');
        }

        // Broken character references, from Tanzil's 2011 Bangla file: a lost character replaced by
        // "?", a modifier letter or a character from another script, fused to "#NNNN;" or "NNNN;".
        // ASCII digits only: Bengali digits followed by ";" are ordinary text.
        if (preg_match_all('/([^\s\p{L}\p{N}]|[^\s\p{Common}\p{Inherited}\p{Bengali}\p{Latin}\p{Arabic}])(#?[0-9]{3,5};)/u', $value, $m)) {
            $error('known_corruption_pattern', 'a lost character replaced by "'.implode('", "', array_unique($m[1])).'" next to a broken character reference ('.implode(', ', array_unique($m[2])).').');
        } elseif (preg_match_all('/(?<!&)#[0-9]{2,6};|(?<![&0-9])[0-9]{3,5};(?=\S)/u', $value, $m)) {
            $error('broken_character_reference', 'a character reference without its "&" ('.implode(', ', array_unique($m[0])).').');
        }
        if ($field === 'text' && preg_match_all('/&(#[0-9]+|#x[0-9a-f]+|[a-z]+);/iu', $value, $m)) {
            $error('html_entity_in_text', 'verse text contains HTML character references ('.implode(', ', array_unique($m[0])).').');
        }

        if (preg_match_all('/<\/?([a-z][a-z0-9]*)\b[^>]*>/i', $value, $m)) {
            $tags = array_unique(array_map('strtolower', $m[1]));
            if (array_intersect($tags, ['script', 'style', 'iframe', 'object', 'embed'])) {
                $error('unsafe_markup', 'contains unsafe markup ('.implode(', ', $tags).').');
            } elseif ($field === 'text') {
                // Some sources put a section heading in bold inside the verse. Kept, but a reviewer should see it.
                $warning('markup_in_text', 'verse text contains markup ('.implode(', ', $tags).').');
            } elseif ($other = array_diff($tags, self::FOOTNOTE_TAGS)) {
                $warning('unexpected_markup', 'contains unusual markup ('.implode(', ', $other).').');
            }
        }

        if (preg_match_all('/[\p{Co}\p{Cn}\p{Cs}]/u', $value, $m)) {
            $error('invalid_codepoint', 'private-use or unassigned code points ('.$this->codepoints($m[0]).').');
        }
        $controls = $field === 'text' ? '/[\p{Cc}]/u' : '/[^\P{Cc}\n\r\t]/u';
        if (preg_match_all($controls, $value, $m)) {
            $error('control_character', 'control characters ('.$this->codepoints($m[0]).').');
        }

        // Script checks look at the words only, not at markup such as <b>.
        $words = preg_replace('/<[^>]*>/', ' ', $value) ?? $value;

        // Letters from scripts that do not belong in this edition.
        $script = self::LANGUAGE_SCRIPT[$language] ?? null;
        $allowed = '\p{Common}\p{Inherited}\p{Arabic}'.($script ? '\p{'.$script.'}' : '\p{Latin}');
        if (preg_match_all('/[^'.$allowed.'\s]/u', $words, $m)) {
            $letters = array_values(array_unique($m[0]));
            // Latin in a Bangla or Arabic-script edition can be legitimate (a name, an abbreviation), so it is a warning.
            $latinOnly = $script !== 'Latin' && preg_match('/^\p{Latin}+$/u', implode('', $letters));
            $latinOnly
                ? $warning('latin_in_text', 'contains Latin letters ('.$this->codepoints($letters).').')
                : $error('unexpected_script', 'letters from an unexpected script ('.$this->codepoints($letters).').');
        }

        // Combining marks with no letter of their own script to attach to.
        if (preg_match_all('/(?<![\p{Latin}\x{0300}-\x{036F}])[\x{0300}-\x{036F}]/u', $words, $m)) {
            $error('stray_combining_mark', 'Latin combining marks not attached to a Latin letter ('.$this->codepoints($m[0]).').');
        }
        // A detached Arabic vowel mark is usually a typing slip in a quotation, not corruption.
        if (preg_match_all('/(?<![\p{Arabic}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}])[\x{064B}-\x{065F}\x{0670}]/u', $words, $m)) {
            $warning('detached_arabic_mark', 'Arabic vowel marks not attached to an Arabic letter ('.$this->codepoints($m[0]).').');
        }

        // Words written in more than one script. Two runs of Bengali and Arabic side by side are
        // usually a missing space before a quotation; anything else is a sign of mangled text.
        if (preg_match_all('/[\p{L}\p{M}]+/u', $words, $found)) {
            $mangled = [];
            $joined = [];
            foreach (array_unique($found[0]) as $word) {
                $runs = $this->scriptRuns($word);
                if (count($runs) < 2) {
                    continue;
                }
                $pairIsBengaliArabic = count($runs) === 2 && ! array_diff($runs, ['Bengali', 'Arabic']);
                $pairIsBengaliArabic ? $joined[] = $word : $mangled[] = $word;
            }
            if ($mangled) {
                $error('mixed_script_word', 'words mixing scripts ('.implode(', ', array_slice($mangled, 0, 5)).').');
            }
            if ($joined) {
                $warning('missing_space_between_scripts', 'Bengali and Arabic run together ('.implode(', ', array_slice($joined, 0, 5)).').');
            }
        }

        return $issues;
    }

    /** Footnote markers in the verse text and in the footnotes should match. Warnings only. */
    public function footnoteIssues(string $text, ?string $footnotes): array
    {
        $inText = FootnoteParser::markers($text);
        if ($inText === [] && ($footnotes === null || trim($footnotes) === '')) {
            return [];
        }
        $inNotes = FootnoteParser::markers((string) $footnotes);
        $issues = [];
        if ($missing = array_diff($inText, $inNotes)) {
            $issues[] = TextIssue::warning('footnote_missing', 'markers with no footnote: '.implode(' ', $missing).'.');
        }
        if ($unused = array_diff($inNotes, $inText)) {
            $issues[] = TextIssue::warning('footnote_unreferenced', 'footnotes with no marker in the text: '.implode(' ', $unused).'.');
        }

        return $issues;
    }

    /** @return list<string> the scripts of a word's letters, one entry per run, e.g. [Bengali, Latin] */
    private function scriptRuns(string $word): array
    {
        $runs = [];
        foreach (mb_str_split($word) as $char) {
            // Marks and shared letters, such as the ʿ in Saʿīd, belong to no script of their own.
            if (preg_match('/[\p{M}\p{Common}\p{Inherited}]/u', $char)) {
                continue;
            }
            $script = 'Other';
            foreach (['Bengali', 'Latin', 'Arabic', 'Devanagari', 'Cyrillic', 'Han'] as $s) {
                if (preg_match('/\p{'.$s.'}/u', $char)) {
                    $script = $s;
                    break;
                }
            }
            if (end($runs) !== $script) {
                $runs[] = $script;
            }
        }

        return $runs;
    }

    private function codepoints(array $chars): string
    {
        return implode(', ', array_map(
            fn (string $c) => sprintf('U+%04X%s', mb_ord($c), class_exists(\IntlChar::class) ? ' '.\IntlChar::charName($c) : ''),
            array_slice(array_values(array_unique($chars)), 0, 6),
        ));
    }
}
