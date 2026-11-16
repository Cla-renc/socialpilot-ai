<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('brand_voice')->nullable();
            $table->string('writing_style')->nullable();
            $table->json('content_formats')->nullable();
            $table->json('preferred_publishing_times')->nullable();
            $table->json('brand_colours')->nullable();
            $table->json('content_restrictions')->nullable();
            $table->json('hashtag_preferences')->nullable();
            $table->string('publishing_frequency')->default('weekly');
            $table->string('timezone')->default('UTC');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_settings');
    }
};
