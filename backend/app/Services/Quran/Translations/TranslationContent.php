<?php

namespace App\Services\Quran\Translations;

use App\Services\Quran\QuranStructure;
use Illuminate\Support\Facades\DB;

/**
 * An edition's complete content, keyed "surah:verse", with a checksum over the exact stored
 * strings in Quran order. Equal checksums mean byte-identical text and footnotes.
 */
final class TranslationContent
{
    /** @param array<string, array{text: string, footnotes: ?string}> $verses */
    public function __construct(public readonly array $verses) {}

    public function checksum(): string
    {
        $hash = hash_init('sha256');
        foreach (QuranStructure::VERSE_COUNTS as $i => $count) {
            for ($v = 1; $v <= $count; $v++) {
                $row = $this->verses[($i + 1).":{$v}"] ?? null;
                hash_update($hash, ($i + 1).":{$v}\x1F".($row['text'] ?? "\x00")."\x1F".($row['footnotes'] ?? '')."\x1E");
            }
        }

        return hash_final($hash);
    }

    public function footnoteCount(): int
    {
        return count(array_filter($this->verses, fn ($r) => ($r['footnotes'] ?? '') !== ''));
    }

    public static function stored(int $editionId): self
    {
        $rows = DB::table('quran_translations as t')
            ->join('quran_verses as v', 'v.id', '=', 't.quran_verse_id')
            ->where('t.quran_translation_edition_id', $editionId)
            ->get(['v.verse_key', 't.text', 't.footnotes_raw']);

        return new self($rows->mapWithKeys(fn ($r) => [$r->verse_key => ['text' => $r->text, 'footnotes' => $r->footnotes_raw]])->all());
    }

    public static function ofRevision(int $revisionId): self
    {
        $rows = DB::table('quran_translation_revision_texts as r')
            ->join('quran_verses as v', 'v.id', '=', 'r.quran_verse_id')
            ->where('r.quran_translation_revision_id', $revisionId)
            ->get(['v.verse_key', 'r.text', 'r.footnotes_raw']);

        return new self($rows->mapWithKeys(fn ($r) => [$r->verse_key => ['text' => $r->text, 'footnotes' => $r->footnotes_raw]])->all());
    }
}
