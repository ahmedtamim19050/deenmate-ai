<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedInteger('points');
            $table->unsignedInteger('per_quantity')->default(1);
            $table->unsignedInteger('daily_cap')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['activity_type_id', 'version']);
            $table->index(['activity_type_id', 'is_active']);
        });

        // Append-only. The PointTransaction model rejects update and delete.
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->integer('points');
            $table->unsignedBigInteger('balance_after');
            $table->string('idempotency_key', 96);
            $table->foreignId('activity_log_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('point_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('rule_version')->nullable();
            $table->foreignId('reverses_transaction_id')->nullable()->unique()->constrained('point_transactions')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('user_point_balances', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->unsignedBigInteger('lifetime_earned')->default(0);
            $table->timestamps();
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('description');
            $table->string('icon', 64)->default('star');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('achievement_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->string('rule_type', 32);
            $table->string('activity_type_key', 64)->nullable();
            $table->unsignedInteger('threshold')->default(1);
            $table->timestamps();
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at');
            $table->unique(['user_id', 'achievement_id']);
        });
    }

    public function down(): void
    {
        foreach (['user_achievements', 'achievement_rules', 'achievements', 'user_point_balances', 'point_transactions', 'point_rules'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
