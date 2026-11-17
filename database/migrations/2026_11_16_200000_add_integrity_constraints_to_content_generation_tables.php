<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Pre-flight verification: Check for duplicate generation_job_id in brand_content_drafts
        $duplicateJobs = DB::table('brand_content_drafts')
            ->select('generation_job_id', DB::raw('count(*) as aggregate_count'))
            ->whereNotNull('generation_job_id')
            ->groupBy('generation_job_id')
            ->having('aggregate_count', '>', 1)
            ->get();

        if ($duplicateJobs->isNotEmpty()) {
            $duplicateIds = $duplicateJobs->pluck('generation_job_id')->implode(', ');
            throw new \RuntimeException(
                "Integrity Migration Aborted: Duplicate generation_job_id found in brand_content_drafts for job IDs: [{$duplicateIds}]. Safe resolution required; no records were altered."
            );
        }

        // 2. Pre-flight verification: Check for duplicate (draft_id, revision_number) in content_revisions
        $duplicateRevisions = DB::table('content_revisions')
            ->select('draft_id', 'revision_number', DB::raw('count(*) as aggregate_count'))
            ->groupBy('draft_id', 'revision_number')
            ->having('aggregate_count', '>', 1)
            ->get();

        if ($duplicateRevisions->isNotEmpty()) {
            $details = $duplicateRevisions->map(fn ($r) => "Draft {$r->draft_id} / Rev {$r->revision_number}")->implode(', ');
            throw new \RuntimeException(
                "Integrity Migration Aborted: Duplicate (draft_id, revision_number) combinations found in content_revisions: [{$details}]. Safe resolution required; no records were altered."
            );
        }

        // 3. Add unique index to brand_content_drafts.generation_job_id
        Schema::table('brand_content_drafts', function (Blueprint $table) {
            $table->unique('generation_job_id', 'bcd_generation_job_id_unique');
        });

        // 4. Add unique composite index to content_revisions(draft_id, revision_number)
        Schema::table('content_revisions', function (Blueprint $table) {
            $table->unique(['draft_id', 'revision_number'], 'cr_draft_id_revision_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_revisions', function (Blueprint $table) {
            $table->dropUnique('cr_draft_id_revision_number_unique');
        });

        Schema::table('brand_content_drafts', function (Blueprint $table) {
            $table->dropUnique('bcd_generation_job_id_unique');
        });
    }
};
