<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('content');
            $table->string('status', 32)->default('answered');
            $table->string('intent', 32)->nullable();
            $table->json('citations')->nullable();
            $table->string('provider', 32)->nullable();
            $table->string('model', 64)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamps();
            $table->index(['ai_conversation_id', 'id']);
            $table->index('created_at');
        });

        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->unsignedInteger('version')->default(1);
            $table->text('content');
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['name', 'version']);
        });

        Schema::create('islamic_sources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('source_type', 32)->index();
            $table->string('author')->nullable();
            $table->string('publisher')->nullable();
            $table->string('reference_identifier', 128)->nullable();
            $table->string('language', 8)->default('en');
            $table->string('url', 512)->nullable();
            $table->string('license')->nullable();
            $table->string('verification_status', 16)->default('pending')->index();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'reference_identifier', 'language']);
        });

        Schema::create('islamic_source_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('islamic_source_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->string('reference')->nullable();
            $table->text('content');
            $table->timestamps();
            $table->unique(['islamic_source_id', 'chunk_index']);
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE islamic_source_chunks ADD FULLTEXT ft_chunk_content (content)');
        }

        Schema::create('ai_response_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rating', 16);
            $table->text('comment')->nullable();
            $table->string('status', 16)->default('open')->index();
            $table->timestamps();
            $table->unique(['ai_message_id', 'user_id']);
        });

        Schema::create('scholar_reviews', function (Blueprint $table) {
            $table->id();
            $table->morphs('reviewable');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['scholar_reviews', 'ai_response_feedback', 'islamic_source_chunks', 'islamic_sources', 'ai_prompts', 'ai_messages', 'ai_conversations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
