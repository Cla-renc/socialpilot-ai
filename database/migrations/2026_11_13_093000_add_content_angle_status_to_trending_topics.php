<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trending_topics', function (Blueprint $table) {
            $table->string('content_angle_status', 32)->default('unavailable');
            $table->text('content_angle_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trending_topics', function (Blueprint $table) {
            $table->dropColumn(['content_angle_status', 'content_angle_error']);
        });
    }
};
