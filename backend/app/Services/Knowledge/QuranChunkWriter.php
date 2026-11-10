<?php

namespace App\Services\Knowledge;

use App\Models\IslamicSource;
use Illuminate\Support\Facades\DB;

/**
 * Writes a Quran edition into the knowledge base: one document per surah and one chunk per verse,
 * each chunk carrying its surah, ayah and a checksum of its exact text. A verse is never split or
 * merged. Idempotent: rerunning with the same text rewrites the same rows.
 */
class QuranChunkWriter
{
    /**
     * @param  array<string, string>  $verses  "surah:ayah" => exact verse text, in Quran order
     * @return int number of chunks written
     */
    public function write(IslamicSource $source, array $verses): int
    {
        $surahNames = DB::table('quran_surahs')->pluck('name_transliterated', 'id');
        $now = now();

        $documents = [];
        foreach (array_keys($verses) as $key) {
            $surah = (int) strtok($key, ':');
            $documents[$surah] ??= [
                'islamic_source_id' => $source->id,
                'document_key' => "surah:{$surah}",
                'title' => $surahNames[$surah] ?? "Surah {$surah}",
                'position' => $surah,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('islamic_source_documents')->upsert(array_values($documents), ['islamic_source_id', 'document_key'], ['title', 'position', 'updated_at']);
        $documentIds = DB::table('islamic_source_documents')->where('islamic_source_id', $source->id)->pluck('id', 'document_key');

        $rows = [];
        $index = 0;
        foreach ($verses as $key => $text) {
            [$surah, $ayah] = array_map('intval', explode(':', $key));
            $rows[] = [
                'islamic_source_id' => $source->id,
                'islamic_source_document_id' => $documentIds["surah:{$surah}"] ?? null,
                'chunk_index' => $index++,
                'reference' => "Quran {$key}",
                'surah_number' => $surah,
                'ayah_from' => $ayah,
                'ayah_to' => $ayah,
                'content' => $text,
                'content_checksum' => hash('sha256', $text),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $update = ['islamic_source_document_id', 'reference', 'surah_number', 'ayah_from', 'ayah_to', 'content', 'content_checksum', 'updated_at'];
        foreach (array_chunk($rows, 500) as $batch) {
            DB::table('islamic_source_chunks')->upsert($batch, ['islamic_source_id', 'chunk_index'], $update);
        }
        DB::table('islamic_source_chunks')->where('islamic_source_id', $source->id)->where('chunk_index', '>=', $index)->delete();

        return $index;
    }
}
