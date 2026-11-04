<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('category', 32);
            $table->string('status', 16);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_uuid', 64);
            $table->string('platform', 16);
            $table->string('push_token', 512)->nullable();
            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'device_uuid']);
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 96)->unique();
            $table->text('value')->nullable();
            $table->string('group', 32)->default('general')->index();
            $table->boolean('is_public')->default(false);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Actor is a nullable reference so the trail survives account deletion.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64)->index();
            $table->nullableMorphs('subject');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('data_export_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('pending')->index();
            $table->string('file_path', 512)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['data_export_requests', 'audit_logs', 'app_settings', 'user_devices', 'notification_logs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
