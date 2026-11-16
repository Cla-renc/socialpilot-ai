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
        // 1. Publishing Attempts table
        Schema::create('publishing_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_post_id')->nullable()->constrained('scheduled_posts')->cascadeOnDelete();
            $table->foreignId('scheduled_post_platform_id')->nullable()->constrained('scheduled_post_platforms')->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained('social_accounts')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('platform', 32)->index();
            $table->string('status', 32)->default('queued')->index();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('idempotency_key', 128)->nullable()->index();
            $table->string('external_post_id', 255)->nullable()->index();
            $table->text('external_post_url')->nullable();
            $table->string('provider_status', 64)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->json('request_metadata')->nullable();
            $table->json('response_metadata')->nullable();
            $table->boolean('is_retryable')->default(false);
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->index(['brand_id', 'platform', 'status']);
            $table->index(['scheduled_post_id', 'platform']);
        });

        // 2. OAuth States table (persistent state and PKCE tracking)
        Schema::create('oauth_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained('social_accounts')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('state_hash', 128)->unique();
            $table->string('mode', 16)->default('web');
            $table->text('code_verifier')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'expires_at']);
        });

        // 3. Publishing Notifications table
        Schema::create('publishing_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignId('publishing_attempt_id')->nullable()->constrained('publishing_attempts')->cascadeOnDelete();
            $table->foreignId('scheduled_post_id')->nullable()->constrained('scheduled_posts')->cascadeOnDelete();
            $table->string('notification_type', 32)->index(); // success, failure, partial, reauthorization_required, processing
            $table->string('platform', 32)->nullable();
            $table->string('title');
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'brand_id', 'read_at']);
        });

        // 4. Publishing Webhook Events table
        Schema::create('publishing_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32)->index();
            $table->string('event_type', 64)->index();
            $table->string('external_event_id', 255)->nullable()->index();
            $table->json('payload');
            $table->boolean('signature_valid')->default(true);
            $table->timestamp('processed_at')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publishing_webhook_events');
        Schema::dropIfExists('publishing_notifications');
        Schema::dropIfExists('oauth_states');
        Schema::dropIfExists('publishing_attempts');
    }
};
