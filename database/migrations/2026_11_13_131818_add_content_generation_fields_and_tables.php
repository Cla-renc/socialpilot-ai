<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Content Generation Jobs
        if (! Schema::hasTable('content_generation_jobs')) {
            Schema::create('content_generation_jobs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
                $table->string('status')->default('queued'); // queued, processing, completed, failed
                $table->unsignedTinyInteger('progress')->default(0);
                $table->string('objective')->nullable();
                $table->string('category')->nullable();
                $table->string('platform')->default('linkedin');
                $table->string('content_format')->default('post');
                $table->text('instructions')->nullable();
                $table->foreignId('trend_id')->nullable()->constrained('trending_topics')->nullOnDelete();
                $table->string('provider')->default('internal');
                $table->string('model')->nullable();
                $table->text('error_message')->nullable();
                $table->unsignedBigInteger('result_draft_id')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['brand_id', 'status']);
                $table->index(['user_id', 'status']);
            });
        }

        // 2. Extend brand_content_drafts
        Schema::table('brand_content_drafts', function (Blueprint $table) {
            if (! Schema::hasColumn('brand_content_drafts', 'hook')) {
                $table->text('hook')->nullable()->after('body');
            }
            if (! Schema::hasColumn('brand_content_drafts', 'caption')) {
                $table->text('caption')->nullable()->after('hook');
            }
            if (! Schema::hasColumn('brand_content_drafts', 'call_to_action')) {
                $table->text('call_to_action')->nullable()->after('caption');
            }
            if (! Schema::hasColumn('brand_content_drafts', 'content_format')) {
                $table->string('content_format')->nullable()->after('content_type');
            }
            if (! Schema::hasColumn('brand_content_drafts', 'objective')) {
                $table->string('objective')->nullable()->after('content_format');
            }
            if (! Schema::hasColumn('brand_content_drafts', 'category')) {
                $table->string('category')->nullable()->after('objective');
            }
            if (! Schema::hasColumn('brand_content_drafts', 'quality_score')) {
                $table->unsignedTinyInteger('quality_score')->nullable()->after('hashtags');
            }
            if (! Schema::hasColumn('brand_content_drafts', 'quality_evaluation')) {
                $table->json('quality_evaluation')->nullable()->after('quality_score');
            }
            if (! Schema::hasColumn('brand_content_drafts', 'generation_job_id')) {
                $table->foreignId('generation_job_id')->nullable()->after('quality_evaluation')->constrained('content_generation_jobs')->nullOnDelete();
            }
            if (! Schema::hasColumn('brand_content_drafts', 'selected_variation_id')) {
                $table->unsignedBigInteger('selected_variation_id')->nullable()->after('generation_job_id');
            }
        });

        // 3. Content Variations
        if (! Schema::hasTable('content_variations')) {
            Schema::create('content_variations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('draft_id')->constrained('brand_content_drafts')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
                $table->string('style'); // professional, conversational, bold, educational, direct
                $table->string('title')->nullable();
                $table->text('hook')->nullable();
                $table->longText('body');
                $table->text('caption')->nullable();
                $table->text('call_to_action')->nullable();
                $table->json('hashtags')->nullable();
                $table->unsignedTinyInteger('quality_score')->nullable();
                $table->json('quality_evaluation')->nullable();
                $table->boolean('is_selected')->default(false);
                $table->timestamps();

                $table->index(['draft_id', 'is_selected']);
                $table->index(['brand_id', 'style']);
            });
        }

        // 4. Content Revisions
        if (! Schema::hasTable('content_revisions')) {
            Schema::create('content_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('draft_id')->constrained('brand_content_drafts')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('revision_number')->default(1);
                $table->string('action'); // initial, rewrite, shorten, expand, improve_hook, tone_change, user_edit, etc.
                $table->text('instructions')->nullable();
                $table->string('title')->nullable();
                $table->text('hook')->nullable();
                $table->longText('body');
                $table->text('caption')->nullable();
                $table->text('call_to_action')->nullable();
                $table->json('hashtags')->nullable();
                $table->unsignedTinyInteger('quality_score')->nullable();
                $table->timestamps();

                $table->index(['draft_id', 'revision_number']);
            });
        }

        // 5. Content Trend Sources
        if (! Schema::hasTable('content_trend_sources')) {
            Schema::create('content_trend_sources', function (Blueprint $table) {
                $table->id();
                $table->foreignId('draft_id')->constrained('brand_content_drafts')->cascadeOnDelete();
                $table->foreignId('trending_topic_id')->constrained('trending_topics')->cascadeOnDelete();
                $table->foreignId('trend_source_id')->nullable()->constrained('trend_sources')->nullOnDelete();
                $table->string('relationship_type')->default('primary');
                $table->timestamps();

                $table->unique(['draft_id', 'trending_topic_id']);
            });
        }

        // 6. Content Generation Settings
        if (! Schema::hasTable('content_generation_settings')) {
            Schema::create('content_generation_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('default_objective')->nullable();
                $table->string('default_category')->nullable();
                $table->string('default_platform')->nullable();
                $table->string('default_format')->nullable();
                $table->string('default_tone')->nullable();
                $table->json('preferred_hashtags')->nullable();
                $table->json('avoid_hashtags')->nullable();
                $table->text('writing_guidelines')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_generation_settings');
        Schema::dropIfExists('content_trend_sources');
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('content_variations');

        Schema::table('brand_content_drafts', function (Blueprint $table) {
            $table->dropForeign(['generation_job_id']);
            $table->dropColumn([
                'hook',
                'caption',
                'call_to_action',
                'content_format',
                'objective',
                'category',
                'quality_score',
                'quality_evaluation',
                'generation_job_id',
                'selected_variation_id',
            ]);
        });

        Schema::dropIfExists('content_generation_jobs');
    }
};
