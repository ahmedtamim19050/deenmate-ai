<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_prayer_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('location_mode', 16)->default('manual');
            $table->string('city')->nullable();
            $table->string('country', 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('calculation_method', 32)->default('MWL');
            $table->string('asr_method', 16)->default('standard');
            $table->string('high_latitude_rule', 24)->default('angle_based');
            foreach (['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'] as $p) {
                $table->smallInteger($p.'_offset')->default(0);
            }
            $table->timestamps();
        });

        Schema::create('prayer_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('timezone', 64);
            $table->string('settings_hash', 40);
            foreach (['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'] as $p) {
                $table->dateTime($p.'_at');
            }
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });

        Schema::create('prayer_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('prayer', 16);
            $table->boolean('enabled')->default(true);
            $table->smallInteger('offset_minutes')->default(0);
            $table->string('sound', 32)->default('default');
            $table->boolean('vibration')->default(true);
            $table->boolean('private_preview')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'prayer']);
        });

        Schema::create('user_prayer_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('prayer', 16);
            $table->string('template_key', 48);
            $table->string('label');
            $table->string('category', 16);
            $table->unsignedTinyInteger('rakats');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'prayer', 'template_key']);
        });

        Schema::create('salah_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_log_id')->constrained()->cascadeOnDelete();
            $table->string('prayer', 16);
            $table->date('prayer_date');
            $table->timestamp('confirmed_at');
            $table->unsignedSmallInteger('total_rakats');
            $table->string('idempotency_key', 64);
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'prayer_date', 'prayer']);
        });

        Schema::create('salah_record_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salah_record_id')->constrained()->cascadeOnDelete();
            $table->string('template_key', 48)->nullable();
            $table->string('label');
            $table->string('category', 16);
            $table->unsignedTinyInteger('rakats');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['salah_record_items', 'salah_records', 'user_prayer_templates', 'prayer_notification_preferences', 'prayer_schedules', 'user_prayer_settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
