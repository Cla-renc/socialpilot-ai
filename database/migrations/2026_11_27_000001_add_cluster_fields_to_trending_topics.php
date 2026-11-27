<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trending_topics', function (Blueprint $table) {
            $table->string('cluster_id', 64)->nullable()->after('research_job_id')->index();
            $table->json('participating_platforms')->nullable()->after('geographic_market');
            $table->boolean('is_cross_platform')->default(false)->after('participating_platforms')->index();
            $table->unsignedTinyInteger('cluster_confidence')->nullable()->after('is_cross_platform');
        });
    }

    public function down(): void
    {
        Schema::table('trending_topics', function (Blueprint $table) {
            $table->dropIndex(['cluster_id']);
            $table->dropIndex(['is_cross_platform']);
            $table->dropColumn([
                'cluster_id',
                'participating_platforms',
                'is_cross_platform',
                'cluster_confidence',
            ]);
        });
    }
};
