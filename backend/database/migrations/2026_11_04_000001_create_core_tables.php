<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('admin_role', 32)->nullable()->index()->after('password');
            $table->string('country', 2)->nullable()->after('admin_role');
            $table->timestamp('onboarded_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('locale', 8)->default('en');
            $table->string('theme', 8)->default('system');
            $table->boolean('haptics_enabled')->default(true);
            $table->boolean('show_points')->default(true);
            $table->boolean('ai_personalization_enabled')->default(false);
            $table->boolean('ads_personalized')->default(false);
            $table->timestamps();
        });

        Schema::create('activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('category', 32)->index();
            $table->string('unit', 32)->default('count');
            $table->boolean('allows_in_app_source')->default(false);
            $table->boolean('allows_manual_source')->default(true);
            $table->boolean('one_per_day')->default(false);
            $table->boolean('client_creatable')->default(true);
            $table->unsignedInteger('max_quantity')->default(10000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_type_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('source', 16);
            $table->timestamp('occurred_at');
            $table->date('local_date');
            $table->json('metadata')->nullable();
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'local_date']);
            $table->index(['user_id', 'activity_type_id', 'local_date']);
        });

        Schema::create('activity_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_log_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'started_at']);
        });

        Schema::create('user_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('activity_type_key', 64)->nullable();
            $table->unsignedInteger('target_value')->default(1);
            $table->string('period', 16)->default('daily');
            $table->string('status', 16)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status']);
        });

        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('activity_count')->default(0);
            $table->unsignedInteger('salah_count')->default(0);
            $table->unsignedInteger('quran_pages')->default(0);
            $table->unsignedInteger('dhikr_count')->default(0);
            $table->unsignedInteger('good_deeds')->default(0);
            $table->unsignedInteger('lessons_completed')->default(0);
            $table->boolean('fasted')->default(false);
            $table->integer('points_earned')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_summaries');
        Schema::dropIfExists('user_goals');
        Schema::dropIfExists('activity_sessions');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('activity_types');
        Schema::dropIfExists('user_preferences');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropIndex(['admin_role']);
            $table->dropSoftDeletes();
            $table->dropColumn(['uuid', 'admin_role', 'country', 'onboarded_at']);
        });
    }
};
