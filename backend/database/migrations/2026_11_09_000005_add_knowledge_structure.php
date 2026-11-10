<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives the AI knowledge base a structured citation model: source -> document -> chunk, with
 * each chunk keeping its own reference fields and checksum. Additive only: no text is changed
 * and nothing is deleted. Existing chunks are backfilled from their stored "Quran s:a" reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('islamic_sources', function (Blueprint $table) {
            $table->string('version', 64)->nullable()->after('publisher');
            $table->text('attribution')->nullable()->after('license');
            $table->string('license_url', 512)->nullable()->after('attribution');
            $table->char('content_checksum', 64)->nullable()->after('license_url');
        });

        // A surah of a Quran edition, a book of a Hadith collection, a volume of a Tafsir, a chapter of a book.
        Schema::create('islamic_source_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('islamic_source_id')->constrained()->cascadeOnDelete();
            $table->string('document_key', 64);
            $table->string('title')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['islamic_source_id', 'document_key'], 'isd_source_key_unique');
        });

        Schema::table('islamic_source_chunks', function (Blueprint $table) {
            $table->foreignId('islamic_source_document_id')->nullable()->after('islamic_source_id')
                ->constrained('islamic_source_documents', indexName: 'isc_document_fk')->nullOnDelete();
            // Quran and Tafsir location. A Tafsir entry can cover a range of verses.
            $table->unsignedSmallInteger('surah_number')->nullable()->after('reference');
            $table->unsignedSmallInteger('ayah_from')->nullable()->after('surah_number');
            $table->unsignedSmallInteger('ayah_to')->nullable()->after('ayah_from');
            // Citation fields that differ by source type: Hadith collection, book and number and grade, or a book page.
            $table->json('citation')->nullable()->after('ayah_to');
            $table->char('content_checksum', 64)->nullable()->after('content');
            $table->index(['surah_number', 'ayah_from', 'ayah_to'], 'isc_quran_location');
        });

        Schema::table('ai_messages', function (Blueprint $table) {
            // Question understanding, answer language, limitations and confidence for one reply.
            $table->json('analysis')->nullable()->after('citations');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('ai_messages', fn (Blueprint $table) => $table->dropColumn('analysis'));
        Schema::table('islamic_source_chunks', function (Blueprint $table) {
            $table->dropForeign('isc_document_fk');
            $table->dropIndex('isc_quran_location');
            $table->dropColumn(['islamic_source_document_id', 'surah_number', 'ayah_from', 'ayah_to', 'citation', 'content_checksum']);
        });
        Schema::dropIfExists('islamic_source_documents');
        Schema::table('islamic_sources', fn (Blueprint $table) => $table->dropColumn(['version', 'attribution', 'license_url', 'content_checksum']));
    }

    private function backfill(): void
    {
        // Source metadata from the translation edition each AI copy was made from.
        foreach (DB::table('quran_translation_editions')->whereNotNull('islamic_source_id')->get() as $edition) {
            DB::table('islamic_sources')->where('id', $edition->islamic_source_id)->update([
                'version' => $edition->version,
                'attribution' => $edition->attribution_text,
                'license_url' => $edition->license_url,
                'content_checksum' => $edition->ai_synced_checksum,
            ]);
        }

        $surahs = DB::table('quran_surahs')->pluck('name_transliterated', 'id');
        // Hadith sources are named "collection:{slug}", matching hadith_collections.slug.
        $hadithSources = DB::table('islamic_sources')->where('source_type', 'hadith')->where('reference_identifier', 'like', 'collection:%')
            ->pluck('reference_identifier', 'id')
            ->map(fn (string $ref) => DB::table('hadith_collections')->where('slug', substr($ref, 11))->first())
            ->filter();

        DB::table('islamic_source_chunks')->orderBy('id')->chunkById(1000, function ($chunks) use ($surahs, $hadithSources) {
            $documents = [];
            foreach ($chunks as $chunk) {
                $update = ['content_checksum' => hash('sha256', $chunk->content)];
                $collection = $hadithSources[$chunk->islamic_source_id] ?? null;
                if ($collection && preg_match('/\s(\S+)$/', (string) $chunk->reference, $m)) {
                    $record = DB::table('hadith_records')->where('hadith_collection_id', $collection->id)->where('hadith_number', $m[1])->first();
                    if ($record) {
                        $update['citation'] = json_encode(array_filter([
                            'collection' => $collection->name,
                            'book' => $record->book,
                            'hadith_number' => $record->hadith_number,
                            'grade' => $record->grade,
                            'grade_source' => $record->grade_source,
                        ], fn ($v) => $v !== null));
                    }
                } elseif (preg_match('/^Quran (\d{1,3}):(\d{1,3})$/', (string) $chunk->reference, $m)) {
                    $surah = (int) $m[1];
                    $update += ['surah_number' => $surah, 'ayah_from' => (int) $m[2], 'ayah_to' => (int) $m[2]];
                    $key = "{$chunk->islamic_source_id}|{$surah}";
                    $documents[$key] ??= DB::table('islamic_source_documents')
                        ->where('islamic_source_id', $chunk->islamic_source_id)->where('document_key', "surah:{$surah}")->value('id')
                        ?? DB::table('islamic_source_documents')->insertGetId([
                            'islamic_source_id' => $chunk->islamic_source_id,
                            'document_key' => "surah:{$surah}",
                            'title' => $surahs[$surah] ?? "Surah {$surah}",
                            'position' => $surah,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    $update['islamic_source_document_id'] = $documents[$key];
                }
                DB::table('islamic_source_chunks')->where('id', $chunk->id)->update($update);
            }
        });
    }
};
