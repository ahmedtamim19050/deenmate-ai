<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foreign keys have short explicit names: MySQL limits identifiers to 64 characters.
 *
 * Translation imports with full attribution, footnotes, revision history, review states and
 * flagged-record tracking. Additive only: existing editions and translation rows are untouched,
 * and existing editions start in the "imported" review state (never reviewed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_translation_editions', function (Blueprint $table) {
            $table->string('publisher')->nullable();
            $table->string('copyright_holder')->nullable();
            $table->text('attribution_text')->nullable();
            $table->string('version', 32)->nullable();
            $table->timestamp('source_updated_at')->nullable();
            // Where the import came from, e.g. "quranenc", and the edition's key there.
            $table->string('import_source', 32)->nullable();
            $table->string('source_key', 64)->nullable();
            // pending, imported, under_review, approved, rejected or inactive
            $table->string('review_status', 16)->default('imported')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('content_checksum', 64)->nullable();
            $table->unsignedInteger('current_revision')->nullable();
        });

        // The footnote field exactly as the source published it. It is what the checksum covers.
        Schema::table('quran_translations', function (Blueprint $table) {
            $table->longText('footnotes_raw')->nullable();
        });

        // A parsed index of footnotes, keyed by the marker used in the verse text, e.g. "[১]".
        Schema::create('quran_translation_footnotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quran_translation_id')->constrained(indexName: 'qtf_translation_fk')->cascadeOnDelete();
            // Null for text before the first marker, such as a surah introduction.
            $table->string('marker', 16)->nullable();
            $table->unsignedSmallInteger('position');
            $table->longText('text');
            $table->timestamps();
            $table->unique(['quran_translation_id', 'position'], 'quran_translation_footnote_unique');
        });

        Schema::create('quran_translation_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quran_translation_edition_id')->constrained(indexName: 'qtr_edition_fk')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            // baseline (content found before history existed), import or rollback
            $table->string('kind', 16);
            $table->unsignedInteger('restored_revision')->nullable();
            $table->foreignId('quran_import_log_id')->nullable()->constrained(indexName: 'qtr_import_log_fk')->nullOnDelete();
            $table->string('source_version', 32)->nullable();
            $table->string('checksum', 64);
            $table->unsignedInteger('verse_count');
            $table->unsignedInteger('footnote_count')->default(0);
            $table->timestamps();
            $table->unique(['quran_translation_edition_id', 'revision'], 'quran_translation_revision_unique');
        });

        // The complete content of each revision, so any revision can be restored exactly.
        Schema::create('quran_translation_revision_texts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quran_translation_revision_id')->constrained(indexName: 'qtrt_revision_fk')->cascadeOnDelete();
            $table->foreignId('quran_verse_id')->constrained(indexName: 'qtrt_verse_fk')->cascadeOnDelete();
            $table->text('text');
            $table->longText('footnotes_raw')->nullable();
            $table->unique(['quran_translation_revision_id', 'quran_verse_id'], 'quran_translation_revision_text_unique');
        });

        // Records a reviewer should look at. Errors block an import; warnings are stored here.
        Schema::create('quran_translation_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quran_translation_edition_id')->constrained(indexName: 'qtfl_edition_fk')->cascadeOnDelete();
            $table->foreignId('quran_verse_id')->constrained(indexName: 'qtfl_verse_fk')->cascadeOnDelete();
            $table->unsignedInteger('revision')->nullable();
            $table->string('severity', 8);
            $table->string('code', 48);
            $table->text('detail');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users', indexName: 'qtfl_resolver_fk')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->index(['quran_translation_edition_id', 'severity', 'resolved_at'], 'quran_translation_flag_lookup');
        });

        Schema::create('quran_translation_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quran_translation_edition_id')->constrained(indexName: 'qtrv_edition_fk')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users', indexName: 'qtrv_reviewer_fk')->nullOnDelete();
            $table->string('from_status', 16);
            $table->string('to_status', 16);
            $table->unsignedInteger('revision')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_translation_reviews');
        Schema::dropIfExists('quran_translation_flags');
        Schema::dropIfExists('quran_translation_revision_texts');
        Schema::dropIfExists('quran_translation_revisions');
        Schema::dropIfExists('quran_translation_footnotes');
        Schema::table('quran_translations', function (Blueprint $table) {
            $table->dropColumn('footnotes_raw');
        });
        Schema::table('quran_translation_editions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'publisher', 'copyright_holder', 'attribution_text', 'version', 'source_updated_at', 'import_source',
                'source_key', 'review_status', 'reviewed_at', 'review_notes', 'content_checksum', 'current_revision',
            ]);
        });
    }
};
