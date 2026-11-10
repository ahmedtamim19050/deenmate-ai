<?php

namespace App\Services\Quran\Translations;

use App\Models\IslamicSource;
use App\Models\QuranTranslationEdition as Edition;
use App\Models\QuranTranslationFlag;
use App\Services\Knowledge\QuranChunkWriter;
use App\Services\Quran\QuranStructure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Copies an approved translation edition into islamic_sources, one chunk per verse, so the AI can
 * retrieve it, and withdraws it again when approval ends. Works for any edition.
 *
 * The copy is written while its source is not approved, read back and checked against the
 * edition's checksum, and only then switched on, all in one transaction. A failure leaves the
 * source switched off. Retrieval separately checks, at query time, that the edition is approved
 * and that the synced checksum still matches its content.
 */
class TranslationAiSync
{
    public const STATUS_NOT_SYNCED = 'not_synced';

    public const STATUS_SYNCING = 'syncing';

    public const STATUS_SYNCED = 'synced';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVOKED = 'revoked';

    public function sync(Edition $edition): bool
    {
        $edition->refresh();
        if ($edition->review_status !== Edition::STATUS_APPROVED) {
            // Approval was withdrawn before the queued sync ran: nothing to expose.
            return false;
        }

        try {
            $content = $this->checkPreconditions($edition);
            $this->mark($edition, self::STATUS_SYNCING);

            DB::transaction(function () use ($edition, $content) {
                $source = $this->writeSource($edition, $content);
                $source->forceFill([
                    'verification_status' => 'approved',
                    'reviewer_id' => $edition->reviewed_by,
                    'reviewed_at' => $edition->reviewed_at ?? now(),
                    'review_notes' => "Approved through the translation review of {$edition->identifier}, revision {$edition->current_revision}.",
                ])->save();
                $edition->forceFill([
                    'islamic_source_id' => $source->id,
                    'ai_sync_status' => self::STATUS_SYNCED,
                    'ai_synced_checksum' => $edition->content_checksum,
                    'ai_synced_at' => now(),
                    'ai_sync_error' => null,
                ])->saveQuietly();
            });

            return true;
        } catch (Throwable $e) {
            // The transaction rolled back, so the source stays switched off.
            $this->mark($edition, self::STATUS_FAILED, $e->getMessage());

            return false;
        }
    }

    /** Switches the edition's AI source off at once. Nothing is deleted, so provenance is kept. */
    public function revoke(Edition $edition): void
    {
        $sourceStatus = $edition->review_status === Edition::STATUS_REJECTED ? 'rejected' : 'pending';
        IslamicSource::query()
            ->whereKey($edition->islamic_source_id)
            ->orWhere(fn ($q) => $q->where('source_type', 'quran')->where('reference_identifier', self::reference($edition)))
            ->update(['verification_status' => $sourceStatus]);
        $this->mark($edition, self::STATUS_REVOKED);
    }

    public static function reference(Edition $edition): string
    {
        return "edition:{$edition->identifier}";
    }

    /** @return array<string, array{text: string, footnotes: ?string}> verses in Quran order */
    private function checkPreconditions(Edition $edition): array
    {
        if (blank($edition->review_notes) || $edition->reviewed_by === null) {
            throw new RuntimeException('The approval has no reviewer or no reviewer notes.');
        }
        $open = QuranTranslationFlag::query()->where('quran_translation_edition_id', $edition->id)->whereNull('resolved_at')->count();
        if ($open > 0) {
            throw new RuntimeException("{$open} validation flags are still open.");
        }
        foreach (['attribution_text', 'license'] as $field) {
            if (blank($edition->{$field})) {
                throw new RuntimeException("The edition has no {$field}.");
            }
        }
        if (blank($edition->translator) && blank($edition->publisher)) {
            throw new RuntimeException('The edition names no translator or publisher.');
        }
        $stored = TranslationContent::stored($edition->id);
        $ordered = [];
        foreach (QuranStructure::VERSE_COUNTS as $i => $count) {
            for ($v = 1; $v <= $count; $v++) {
                $key = ($i + 1).":{$v}";
                if (! isset($stored->verses[$key])) {
                    throw new RuntimeException("Verse {$key} is missing from the edition.");
                }
                $ordered[$key] = $stored->verses[$key];
            }
        }
        if (count($stored->verses) !== QuranStructure::VERSES) {
            throw new RuntimeException('The edition has '.count($stored->verses).' verses, expected '.QuranStructure::VERSES.'.');
        }
        if ($edition->content_checksum === null || $stored->checksum() !== $edition->content_checksum) {
            throw new RuntimeException('The stored content does not match the reviewed checksum.');
        }

        return $ordered;
    }

    private function writeSource(Edition $edition, array $content): IslamicSource
    {
        $title = $edition->version ? "{$edition->name}, version {$edition->version}" : $edition->name;
        $source = IslamicSource::query()->updateOrCreate(
            ['source_type' => 'quran', 'reference_identifier' => self::reference($edition), 'language' => $edition->language],
            [
                'title' => Str::limit($title, 255, ''),
                'author' => $edition->translator,
                'publisher' => $edition->publisher,
                'version' => $edition->version,
                'url' => $edition->source_url,
                'license' => Str::limit($edition->license_url ?? $edition->license, 255, ''),
                'license_url' => $edition->license_url,
                'attribution' => $edition->attribution_text,
                'content_checksum' => $edition->content_checksum,
                // Off until the copy below is complete and verified.
                'verification_status' => 'pending',
            ],
        );

        // The verse text exactly as stored; footnotes stay with the translation, not the AI copy.
        app(QuranChunkWriter::class)->write($source, array_map(fn (array $row) => $row['text'], $content));

        // Read back every chunk and compare it, and its location and checksum, with the reviewed text.
        $stored = DB::table('islamic_source_chunks')->where('islamic_source_id', $source->id)->orderBy('chunk_index')
            ->get(['reference', 'surah_number', 'ayah_from', 'content', 'content_checksum']);
        if ($stored->count() !== count($content)) {
            throw new RuntimeException('Read-back found '.$stored->count().' chunks, expected '.count($content).'.');
        }
        $keys = array_keys($content);
        foreach ($stored as $i => $chunk) {
            $text = $content[$keys[$i]]['text'];
            if ($chunk->reference !== "Quran {$keys[$i]}" || "{$chunk->surah_number}:{$chunk->ayah_from}" !== $keys[$i]
                || $chunk->content !== $text || $chunk->content_checksum !== hash('sha256', $text)) {
                throw new RuntimeException("Read-back mismatch at {$keys[$i]}.");
            }
        }

        return $source;
    }

    private function mark(Edition $edition, string $status, ?string $error = null): void
    {
        $edition->forceFill(['ai_sync_status' => $status, 'ai_sync_error' => $error])->saveQuietly();
    }
}
