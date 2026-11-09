<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether an approved translation edition has been copied into islamic_sources for the AI,
 * and which content (by checksum) was copied. Retrieval checks these at query time, so a stale,
 * failed or half-written copy is never used. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_translation_editions', function (Blueprint $table) {
            $table->foreignId('islamic_source_id')->nullable()->constrained(indexName: 'qte_islamic_source_fk')->nullOnDelete();
            // not_synced, syncing, synced, failed or revoked
            $table->string('ai_sync_status', 16)->default('not_synced');
            $table->string('ai_synced_checksum', 64)->nullable();
            $table->timestamp('ai_synced_at')->nullable();
            $table->text('ai_sync_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quran_translation_editions', function (Blueprint $table) {
            $table->dropForeign('qte_islamic_source_fk');
            $table->dropColumn(['islamic_source_id', 'ai_sync_status', 'ai_synced_checksum', 'ai_synced_at', 'ai_sync_error']);
        });
    }
};
