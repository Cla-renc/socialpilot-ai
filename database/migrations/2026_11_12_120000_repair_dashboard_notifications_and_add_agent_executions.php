<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
            });
        }

        if (Schema::hasTable('brand_content_drafts') && ! Schema::hasColumn('brand_content_drafts', 'is_ai_generated')) {
            Schema::table('brand_content_drafts', function (Blueprint $table) {
                $table->boolean('is_ai_generated')->nullable()->index();
            });

            DB::table('brand_content_drafts')
                ->where('ai_provider', 'internal')
                ->update(['is_ai_generated' => false]);
        }

        if (! Schema::hasTable('agent_executions')) {
            Schema::create('agent_executions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
                $table->string('agent_key');
                $table->string('status');
                $table->text('message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'agent_key', 'created_at']);
                $table->index(['brand_id', 'agent_key', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_executions');

        if (Schema::hasTable('brand_content_drafts') && Schema::hasColumn('brand_content_drafts', 'is_ai_generated')) {
            Schema::table('brand_content_drafts', function (Blueprint $table) {
                $table->dropColumn('is_ai_generated');
            });
        }
    }
};
