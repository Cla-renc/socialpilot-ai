<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Scheduled Posts
        Schema::create('scheduled_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_draft_id')->nullable()->constrained('brand_content_drafts')->nullOnDelete();
            $table->string('title');
            $table->text('caption')->nullable();
            $table->longText('body')->nullable();
            $table->json('hashtags')->nullable();
            $table->dateTime('scheduled_at');
            $table->string('timezone', 64)->default('UTC');
            $table->string('status', 32)->default('scheduled'); // scheduled, awaiting_approval, ready_to_schedule, in_queue, paused, cancelled, failed
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->boolean('autopilot_managed')->default(false);
            $table->unsignedBigInteger('autopilot_rule_id')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['brand_id', 'scheduled_at']);
            $table->index(['brand_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        // 2. Scheduled Post Platforms (Platform-specific versions)
        Schema::create('scheduled_post_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_post_id')->constrained('scheduled_posts')->cascadeOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained('social_accounts')->nullOnDelete();
            $table->string('platform', 32); // facebook, instagram, tiktok, youtube, linkedin
            $table->string('custom_title')->nullable();
            $table->text('custom_caption')->nullable();
            $table->longText('custom_body')->nullable();
            $table->json('custom_hashtags')->nullable();
            $table->dateTime('scheduled_at');
            $table->string('status', 32)->default('scheduled');
            $table->json('platform_specific_options')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['scheduled_post_id', 'platform']);
        });

        // 3. Scheduled Post Media (Associating media assets with scheduled posts)
        Schema::create('scheduled_post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_post_id')->constrained('scheduled_posts')->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->string('platform_variant', 32)->nullable();
            $table->timestamps();

            $table->index(['scheduled_post_id', 'order']);
        });

        // 4. Publishing Queues
        Schema::create('publishing_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100)->default('Default Queue');
            $table->string('status', 32)->default('active'); // active, paused
            $table->unsignedInteger('posting_interval_minutes')->default(1440); // 24 hours
            $table->json('preferred_days')->nullable();
            $table->json('preferred_hours')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->dateTime('next_slot_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'status']);
        });

        // 5. Publishing Queue Items
        Schema::create('publishing_queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publishing_queue_id')->constrained('publishing_queues')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduled_post_id')->nullable()->constrained('scheduled_posts')->nullOnDelete();
            $table->foreignId('content_draft_id')->nullable()->constrained('brand_content_drafts')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('priority', 32)->default('normal'); // low, normal, high, urgent
            $table->string('status', 32)->default('ready_to_schedule'); // ready_to_schedule, awaiting_approval, scheduled, paused, cancelled, failed
            $table->string('target_platform', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['publishing_queue_id', 'position']);
            $table->index(['brand_id', 'status']);
        });

        // 6. Autopilot Settings
        Schema::create('autopilot_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('mode', 32)->default('manual'); // manual, ai_assisted, automatic
            $table->unsignedSmallInteger('posts_per_week')->default(3);
            $table->unsignedSmallInteger('min_gap_hours')->default(12);
            $table->json('preferred_days')->nullable();
            $table->json('preferred_hours')->nullable();
            $table->json('preferred_platforms')->nullable();
            $table->json('preferred_categories')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->json('blackout_periods')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->timestamps();

            $table->index(['is_enabled', 'mode']);
        });

        // 7. Autopilot Rules
        Schema::create('autopilot_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('rule_type', 64); // category_schedule, frequency_limit, platform_distribution, gap_constraint, blackout
            $table->json('conditions');
            $table->json('actions');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->index(['brand_id', 'is_active']);
        });

        // 8. Autopilot Activity Logs
        Schema::create('autopilot_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 64);
            $table->json('details')->nullable();
            $table->string('status', 32)->default('success'); // success, warning, failed, info
            $table->timestamps();

            $table->index(['brand_id', 'created_at']);
        });

        // 9. Scheduling Recommendations
        Schema::create('scheduling_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('day_of_week', 20); // monday, tuesday, etc.
            $table->string('suggested_time', 10); // 09:00, 14:30
            $table->string('timezone', 64)->default('UTC');
            $table->text('reason');
            $table->float('confidence')->default(0.8);
            $table->string('evidence_type', 64)->default('heuristic_preliminary'); // heuristic_preliminary, historical_performance, brand_profile_aligned
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['brand_id', 'platform', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduling_recommendations');
        Schema::dropIfExists('autopilot_activity_logs');
        Schema::dropIfExists('autopilot_rules');
        Schema::dropIfExists('autopilot_settings');
        Schema::dropIfExists('publishing_queue_items');
        Schema::dropIfExists('publishing_queues');
        Schema::dropIfExists('scheduled_post_media');
        Schema::dropIfExists('scheduled_post_platforms');
        Schema::dropIfExists('scheduled_posts');
    }
};
