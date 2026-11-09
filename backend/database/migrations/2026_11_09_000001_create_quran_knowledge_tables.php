<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the existing Quran tables into a traceable knowledge base. Additive only: no existing
 * column, row or table is dropped or renamed, so the app and its API keep working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('website', 512);
            $table->string('api_endpoint', 512)->nullable();
            $table->string('license_name');
            $table->string('license_url', 512)->nullable();
            $table->text('attribution_text');
            $table->timestamps();
        });

        Schema::create('quran_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('import_type', 32)->index();
            $table->foreignId('quran_data_source_id')->nullable()->constrained()->nullOnDelete();
            // running, completed, failed or dry_run
            $table->string('status', 16)->index();
            $table->json('options')->nullable();
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('imported_records')->default(0);
            $table->unsignedInteger('updated_records')->default(0);
            $table->unsignedInteger('skipped_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);
            $table->json('error_details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('quran_verses', function (Blueprint $table) {
            $table->string('verse_key', 8)->nullable()->unique();
            // Tanzil "Simple Clean": the same verse without diacritics or marks, for searching only.
            $table->text('text_simple_clean')->nullable();
            $table->foreignId('quran_data_source_id')->nullable()->constrained()->nullOnDelete();
        });
        $key = DB::getDriverName() === 'mysql' ? "CONCAT(surah_id, ':', verse_number)" : "surah_id || ':' || verse_number";
        DB::statement("UPDATE quran_verses SET verse_key = {$key} WHERE verse_key IS NULL");
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE quran_verses ADD FULLTEXT ft_verse_simple_clean (text_simple_clean)');
        }

        Schema::table('quran_translation_editions', function (Blueprint $table) {
            $table->foreignId('quran_data_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('license_url', 512)->nullable();
        });

        Schema::create('quran_tafsirs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('author')->nullable();
            $table->string('language', 8)->index();
            $table->foreignId('quran_data_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('license_name')->nullable();
            $table->string('license_url', 512)->nullable();
            // Off until its licence has been confirmed and a reviewer has approved it.
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // One entry can explain a run of verses, as many tafsir works comment on groups of verses.
        Schema::create('quran_tafsir_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quran_tafsir_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('surah_id');
            $table->foreign('surah_id')->references('id')->on('quran_surahs')->cascadeOnDelete();
            $table->unsignedSmallInteger('verse_from');
            $table->unsignedSmallInteger('verse_to');
            $table->longText('text');
            $table->timestamps();
            $table->unique(['quran_tafsir_id', 'surah_id', 'verse_from'], 'quran_tafsir_entry_unique');
            $table->index(['surah_id', 'verse_from', 'verse_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_tafsir_entries');
        Schema::dropIfExists('quran_tafsirs');
        Schema::table('quran_translation_editions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quran_data_source_id');
            $table->dropColumn('license_url');
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE quran_verses DROP INDEX ft_verse_simple_clean');
        }
        Schema::table('quran_verses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quran_data_source_id');
            $table->dropUnique(['verse_key']);
            $table->dropColumn(['verse_key', 'text_simple_clean']);
        });
        Schema::dropIfExists('quran_import_logs');
        Schema::dropIfExists('quran_data_sources');
    }
};
