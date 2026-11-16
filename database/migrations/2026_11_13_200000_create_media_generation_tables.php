<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Media Assets
        if (! Schema::hasTable('media_assets')) {
            Schema::create('media_assets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
                $table->foreignId('content_draft_id')->nullable()->constrained('brand_content_drafts')->nullOnDelete();
                $table->unsignedBigInteger('parent_asset_id')->nullable(); // no FK constraint (SQLite safe)
                $table->string('asset_type', 50); // 'image','video','poster','graphic','thumbnail','storyboard','uploaded'
                $table->string('title')->nullable();
                $table->string('file_name')->nullable();
                $table->string('file_format', 20)->nullable(); // 'jpg','png','webp','mp4','gif'
                $table->string('mime_type', 100)->nullable();
                $table->string('storage_disk', 50)->default('local');
                $table->text('storage_path')->nullable();
                $table->text('public_url')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->string('aspect_ratio', 20)->nullable(); // '1:1','16:9','9:16','4:5','1.91:1'
                $table->string('platform', 50)->nullable(); // 'instagram','facebook','tiktok','youtube','linkedin'
                $table->string('provider', 50)->nullable(); // 'openai','stability','gemini','internal','uploaded'
                $table->string('provider_model', 100)->nullable();
                $table->string('provider_job_id', 255)->nullable();
                $table->text('generation_prompt')->nullable();
                $table->text('generation_params')->nullable(); // JSON stored as text
                $table->string('generation_status', 50)->default('pending'); // 'pending','queued','processing','completed','failed'
                $table->text('generation_error')->nullable();
                $table->string('objective', 100)->nullable(); // 'social_post','promotional_poster','quote_card','thumbnail'
                $table->string('visual_style', 100)->nullable(); // 'cinematic','minimalist','corporate','editorial'
                $table->boolean('is_variation')->default(false);
                $table->text('metadata')->nullable(); // JSON stored as text
                $table->timestamps();
                $table->softDeletes();

                $table->index('brand_id');
                $table->index('user_id');
                $table->index('generation_status');
                $table->index('asset_type');
                $table->index('platform');
                $table->index('parent_asset_id');
            });
        }

        // 2. Media Generation Jobs
        if (! Schema::hasTable('media_generation_jobs')) {
            Schema::create('media_generation_jobs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('media_asset_id')->nullable(); // set after asset created
                $table->foreignId('content_draft_id')->nullable()->constrained('brand_content_drafts')->nullOnDelete();
                $table->string('job_type', 50); // 'image_generation','video_generation','storyboard_generation','image_edit','image_adapt'
                $table->string('provider', 50)->nullable();
                $table->string('provider_model', 100)->nullable();
                $table->string('provider_job_id', 255)->nullable();
                $table->string('status', 50)->default('queued'); // 'queued','processing','completed','failed','cancelled'
                $table->integer('progress')->default(0);
                $table->text('request_payload')->nullable();
                $table->text('result_payload')->nullable();
                $table->text('error_message')->nullable();
                $table->string('error_code', 100)->nullable();
                $table->integer('retry_count')->default(0);
                $table->integer('max_retries')->default(3);
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index('brand_id');
                $table->index('user_id');
                $table->index('status');
                $table->index('job_type');
                $table->index('media_asset_id');
            });
        }

        // 3. Media Storyboards
        if (! Schema::hasTable('media_storyboards')) {
            Schema::create('media_storyboards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('media_asset_id')->nullable();
                $table->foreignId('content_draft_id')->nullable()->constrained('brand_content_drafts')->nullOnDelete();
                $table->string('title');
                $table->text('concept')->nullable();
                $table->string('platform', 50)->nullable();
                $table->string('aspect_ratio', 20)->nullable();
                $table->integer('estimated_duration')->nullable(); // seconds
                $table->string('status', 50)->default('draft'); // 'draft','approved','exported'
                $table->text('metadata')->nullable();
                $table->timestamps();

                $table->index('brand_id');
                $table->index('user_id');
            });
        }

        // 4. Media Storyboard Scenes
        if (! Schema::hasTable('media_storyboard_scenes')) {
            Schema::create('media_storyboard_scenes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('storyboard_id')->constrained('media_storyboards')->cascadeOnDelete();
                $table->integer('scene_number');
                $table->text('description')->nullable();
                $table->text('visual_suggestion')->nullable();
                $table->text('narration_text')->nullable();
                $table->text('on_screen_text')->nullable();
                $table->integer('duration_seconds')->default(5);
                $table->string('transition', 100)->nullable();
                $table->text('background_music_note')->nullable();
                $table->unsignedBigInteger('image_asset_id')->nullable();
                $table->text('metadata')->nullable();
                $table->timestamps();

                $table->index('storyboard_id');
            });
        }

        // 5. Media Visual Settings
        if (! Schema::hasTable('media_visual_settings')) {
            Schema::create('media_visual_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('visual_identity', 50)->default('minimalist'); // 'dark_cinematic','minimalist','corporate','commercial','educational','inspirational','product_ad','editorial'
                $table->string('primary_color', 20)->nullable(); // hex e.g. '#1a1a2e'
                $table->string('secondary_color', 20)->nullable();
                $table->string('accent_color', 20)->nullable();
                $table->string('background_color', 20)->nullable();
                $table->string('text_color', 20)->nullable();
                $table->string('primary_font', 100)->nullable();
                $table->string('secondary_font', 100)->nullable();
                $table->string('font_size_base', 20)->nullable();
                $table->string('logo_placement', 50)->nullable(); // 'top_left','top_right','bottom_left','bottom_right','center'
                $table->text('preferred_platforms')->nullable(); // JSON
                $table->text('preferred_aspect_ratios')->nullable(); // JSON
                $table->text('image_generation_style_prompt')->nullable();
                $table->text('image_generation_negative_prompt')->nullable();
                $table->string('preferred_image_provider', 50)->nullable();
                $table->string('preferred_video_provider', 50)->nullable();
                $table->text('design_preferences')->nullable(); // JSON
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_visual_settings');
        Schema::dropIfExists('media_storyboard_scenes');
        Schema::dropIfExists('media_storyboards');
        Schema::dropIfExists('media_generation_jobs');
        Schema::dropIfExists('media_assets');
    }
};
