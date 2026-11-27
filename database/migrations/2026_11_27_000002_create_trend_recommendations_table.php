<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trend_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trend_topic_id')->constrained('trending_topics')->cascadeOnDelete();
            $table->string('cluster_id', 64)->nullable()->index();
            $table->unsignedTinyInteger('score');
            $table->string('relevance_classification', 24); // 'direct', 'adjacent', 'weak'
            $table->json('explanation'); // why_it_matters, audience_interest, timeliness_and_evidence
            $table->json('content_package'); // recommended_angle, alternative_hooks, draft_caption, hashtags, cta, platform_concepts
            $table->string('target_platform', 32)->default('cross_platform');
            $table->string('suggested_format', 40)->default('short_video');
            $table->string('status', 24)->default('generated'); // 'generated', 'reviewed', 'saved', 'dismissed', 'used'
            $table->string('feedback_status', 24)->nullable(); // 'relevant', 'not_relevant'
            $table->timestamps();

            $table->unique(['brand_id', 'trend_topic_id']);
            $table->index(['brand_id', 'status']);
            $table->index(['brand_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trend_recommendations');
    }
};
