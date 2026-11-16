<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_content_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('platform')->default('linkedin');
            $table->string('content_type')->default('caption');
            $table->string('status')->default('draft');
            $table->text('prompt');
            $table->longText('body');
            $table->json('hashtags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_content_drafts');
    }
};
