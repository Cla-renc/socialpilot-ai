<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trend_research_jobs', function (Blueprint $table) {
            $table->string('result_state', 40)->nullable()->after('status');
            $table->json('result_meta')->nullable()->after('source_status');
        });
    }

    public function down(): void
    {
        Schema::table('trend_research_jobs', function (Blueprint $table) {
            $table->dropColumn(['result_state', 'result_meta']);
        });
    }
};
