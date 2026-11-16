<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Phase 10: Social Media Analytics and AI Performance Optimization.
     */
    public function up(): void
    {
        // 1. analytics_snapshots: stores historical account and platform metrics
        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained('social_accounts')->cascadeOnDelete();
            $table->string('platform', 32)->index();
            $table->string('metric_type', 32)->default('account')->index();
            $table->string('metric_name', 64)->index();
            $table->decimal('metric_value', 16, 4)->default(0);
            $table->string('metric_unit', 32)->default('count');
            $table->string('reporting_period', 32)->default('day');
            $table->timestamp('period_start')->nullable()->index();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('collected_at')->index();
            $table->string('source', 32)->default('platform_api');
            $table->json('metadata')->nullable();
            $table->string('confidence', 32)->default('medium');
            $table->string('priority', 32)->default('normal');
            $table->timestamps();

            // Prevent duplicate metric snapshots for the same account, metric, reporting period and period_start
            $table->unique(
                ['social_account_id', 'metric_name', 'reporting_period', 'period_start'],
                'uniq_snap_acc_metric_per'
            );
            $table->index(['brand_id', 'platform', 'metric_name', 'collected_at']);
        });

        // 2. post_analytics: stores performance information for individual published posts
        Schema::create('post_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained('social_accounts')->cascadeOnDelete();
            $table->foreignId('publishing_attempt_id')->nullable()->constrained('publishing_attempts')->nullOnDelete();
            $table->foreignId('scheduled_post_id')->nullable()->constrained('scheduled_posts')->nullOnDelete();
            $table->string('platform', 32)->index();
            $table->string('external_post_id', 255)->index();
            $table->string('metric_name', 64)->index();
            $table->decimal('metric_value', 16, 4)->default(0);
            $table->string('metric_unit', 32)->default('count');
            $table->string('reporting_period', 32)->default('lifetime');
            $table->timestamp('collected_at')->index();
            $table->json('metadata')->nullable();
            $table->string('confidence', 32)->default('medium');
            $table->string('priority', 32)->default('normal');
            $table->timestamps();

            $table->unique(
                ['social_account_id', 'external_post_id', 'metric_name', 'reporting_period'],
                'uniq_post_analytics_metric'
            );
            $table->index(['brand_id', 'platform', 'collected_at']);
            $table->index(['scheduled_post_id', 'metric_name']);
        });

        // 3. audience_insights: stores audience demographic and geographic metrics
        Schema::create('audience_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained('social_accounts')->cascadeOnDelete();
            $table->string('platform', 32)->index();
            $table->string('insight_type', 64)->index(); // demographics, country, age_gender, devices, active_hours
            $table->string('dimension', 64)->index(); // country, age_range, gender, device_type
            $table->string('dimension_value', 128)->index(); // US, 25-34, F, mobile
            $table->decimal('metric_value', 16, 4)->default(0);
            $table->string('reporting_period', 32)->default('lifetime');
            $table->timestamp('collected_at')->index();
            $table->json('metadata')->nullable();
            $table->string('confidence', 32)->default('medium');
            $table->string('priority', 32)->default('normal');
            $table->timestamps();

            $table->unique(
                ['social_account_id', 'insight_type', 'dimension', 'dimension_value', 'reporting_period'],
                'uniq_aud_ins_dim'
            );
            $table->index(['brand_id', 'platform', 'insight_type']);
        });

        // 4. analytics_collection_logs: tracks analytics collection runs and health
        Schema::create('analytics_collection_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->cascadeOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained('social_accounts')->cascadeOnDelete();
            $table->string('platform', 32)->index();
            $table->string('collection_type', 32)->default('account'); // account, post, audience, full
            $table->string('status', 32)->default('started')->index(); // started, success, failed, partial, rate_limited
            $table->unsignedInteger('records_collected')->default(0);
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->string('confidence', 32)->default('medium');
            $table->string('priority', 32)->default('normal');
            $table->timestamps();

            $table->index(['brand_id', 'platform', 'status']);
        });

        // 5. analytics_reports: stores generated analytics reports (PDF, CSV)
        Schema::create('analytics_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->string('report_type', 64)->index(); // brand_summary, platform_performance, content_performance, ai_optimization, custom
            $table->timestamp('date_range_start')->nullable();
            $table->timestamp('date_range_end')->nullable();
            $table->string('format', 16)->default('pdf'); // pdf, csv
            $table->string('status', 32)->default('pending')->index(); // pending, processing, completed, failed
            $table->string('file_path', 512)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->string('confidence', 32)->default('medium');
            $table->string('priority', 32)->default('normal');
            $table->timestamps();

            $table->index(['brand_id', 'report_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // 6. ai_optimization_recommendations: stores AI-generated performance suggestions
        Schema::create('ai_optimization_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->string('platform', 32)->nullable()->index();
            $table->string('recommendation_type', 64)->index(); // content_category, format_adjustment, posting_time, caption_style, topic_testing, general
            $table->string('title', 255);
            $table->text('description');
            $table->json('supporting_metrics')->nullable();
            $table->string('confidence_level', 32)->default('medium')->index(); // high, medium, low, insufficient_data
            $table->string('recommendation_status', 32)->default('active')->index(); // active, accepted, dismissed, expired
            $table->timestamp('generated_at')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->string('confidence', 32)->default('medium');
            $table->string('priority', 32)->default('normal');
            $table->timestamps();

            $table->index(['brand_id', 'recommendation_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_optimization_recommendations');
        Schema::dropIfExists('analytics_reports');
        Schema::dropIfExists('analytics_collection_logs');
        Schema::dropIfExists('audience_insights');
        Schema::dropIfExists('post_analytics');
        Schema::dropIfExists('analytics_snapshots');
    }
};
