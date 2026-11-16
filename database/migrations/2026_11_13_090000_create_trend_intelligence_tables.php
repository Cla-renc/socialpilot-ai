<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trend_research_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('queued');
            $table->char('criteria_hash', 64);
            $table->json('filters');
            $table->json('source_status')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'status', 'created_at']);
            $table->index(['brand_id', 'criteria_hash', 'status']);
        });

        Schema::create('trending_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_job_id')->constrained('trend_research_jobs')->cascadeOnDelete();
            $table->string('title', 500);
            $table->string('normalized_title');
            $table->text('description')->nullable();
            $table->text('summary')->nullable();
            $table->json('content_angles')->nullable();
            $table->string('category', 80)->default('general');
            $table->unsignedTinyInteger('relevance_score');
            $table->text('relevance_reason');
            $table->string('momentum', 32)->nullable();
            $table->text('momentum_evidence')->nullable();
            $table->string('geographic_market', 128)->nullable();
            $table->unsignedTinyInteger('opportunity_score');
            $table->timestamp('researched_at');
            $table->timestamps();

            $table->unique(['research_job_id', 'normalized_title']);
            $table->index(['brand_id', 'researched_at']);
            $table->index(['brand_id', 'relevance_score']);
            $table->index(['brand_id', 'momentum']);
        });

        Schema::create('trend_sources', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 80)->unique();
            $table->string('name');
            $table->string('type', 40);
            $table->string('connection_status', 32)->default('configuration_required');
            $table->timestamp('last_successful_research_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('rate_limit_resets_at')->nullable();
            $table->timestamps();
        });

        Schema::create('trend_topic_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trend_topic_id')->constrained('trending_topics')->cascadeOnDelete();
            $table->foreignId('trend_source_id')->nullable()->constrained('trend_sources')->nullOnDelete();
            $table->string('source_key', 80);
            $table->string('source_name');
            $table->string('title');
            $table->string('url', 2048);
            $table->char('url_hash', 64);
            $table->timestamp('published_at')->nullable();
            $table->text('summary')->nullable();
            $table->text('evidence')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();

            $table->unique(['trend_topic_id', 'url_hash']);
            $table->index(['source_key', 'published_at']);
        });

        Schema::create('brand_trend_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('research_frequency', 16)->default('manual');
            $table->time('research_time')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->json('preferred_sources')->nullable();
            $table->json('categories')->nullable();
            $table->string('geographic_market', 128)->nullable();
            $table->timestamps();
        });

        Schema::create('trend_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trend_topic_id')->constrained('trending_topics')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feedback_type', 16);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['trend_topic_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trend_feedback');
        Schema::dropIfExists('brand_trend_preferences');
        Schema::dropIfExists('trend_topic_sources');
        Schema::dropIfExists('trend_sources');
        Schema::dropIfExists('trending_topics');
        Schema::dropIfExists('trend_research_jobs');
    }
};
