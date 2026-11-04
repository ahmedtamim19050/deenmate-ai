<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 32)->index();
            $table->string('level', 16)->default('beginner');
            $table->string('language', 8)->default('en');
            $table->string('review_status', 16)->default('pending');
            $table->boolean('is_published')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->json('references')->nullable();
            $table->string('audio_url', 512)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(5);
            $table->string('review_status', 16)->default('pending');
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['course_id', 'sort_order']);
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedTinyInteger('pass_percent')->default(60);
            $table->timestamps();
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->json('options');
            $table->unsignedTinyInteger('correct_index');
            $table->text('explanation')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 64);
            $table->json('answers');
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('total');
            $table->boolean('passed')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
        });

        Schema::create('user_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lesson_id']);
        });

        Schema::create('quran_surahs', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name_arabic');
            $table->string('name_transliterated');
            $table->string('name_english');
            $table->string('revelation_place', 16);
            $table->unsignedSmallInteger('verse_count');
            $table->timestamps();
        });

        Schema::create('quran_verses', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('surah_id');
            $table->foreign('surah_id')->references('id')->on('quran_surahs')->cascadeOnDelete();
            $table->unsignedSmallInteger('verse_number');
            $table->unsignedSmallInteger('page')->nullable()->index();
            $table->unsignedTinyInteger('juz')->nullable();
            $table->text('text_arabic');
            $table->text('transliteration')->nullable();
            $table->string('text_source');
            $table->timestamps();
            $table->unique(['surah_id', 'verse_number']);
        });

        Schema::create('quran_translation_editions', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 64)->unique();
            $table->string('language', 8);
            $table->string('name');
            $table->string('translator')->nullable();
            $table->string('source_url', 512)->nullable();
            $table->string('license')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('quran_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quran_verse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quran_translation_edition_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->timestamps();
            $table->unique(['quran_verse_id', 'quran_translation_edition_id'], 'quran_translation_unique');
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE quran_translations ADD FULLTEXT ft_translation_text (text)');
        }

        Schema::create('hadith_collections', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('compiler')->nullable();
            $table->string('language', 8)->default('en');
            $table->string('source_url', 512)->nullable();
            $table->string('license')->nullable();
            $table->timestamps();
        });

        Schema::create('hadith_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hadith_collection_id')->constrained()->cascadeOnDelete();
            $table->string('book')->nullable();
            $table->string('chapter')->nullable();
            $table->string('hadith_number', 32);
            $table->text('text_arabic')->nullable();
            $table->text('translation')->nullable();
            $table->string('grade')->nullable();
            $table->string('grade_source')->nullable();
            $table->string('source_reference')->nullable();
            $table->timestamps();
            $table->unique(['hadith_collection_id', 'hadith_number']);
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 16);
            $table->unsignedBigInteger('target_id');
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        foreach (['bookmarks', 'hadith_records', 'hadith_collections', 'quran_translations', 'quran_translation_editions', 'quran_verses', 'quran_surahs', 'user_lesson_progress', 'quiz_attempts', 'quiz_questions', 'quizzes', 'lessons', 'courses'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
