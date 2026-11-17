<?php

namespace App\Jobs;

use App\Models\BrandContentDraft;
use App\Models\ContentGenerationJob;
use App\Services\ContentGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunContentGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $generationJobId) {}

    public function handle(ContentGenerationService $service): void
    {
        $job = ContentGenerationJob::query()->findOrFail($this->generationJobId);

        // Fast idempotency check: if job is already marked completed and has draft, no-op
        if ($job->status === 'completed' && $job->result_draft_id) {
            return;
        }

        // If a draft already exists for this job, ensure job links to it and complete
        $existingDraft = BrandContentDraft::where('generation_job_id', $job->id)->first();
        if ($existingDraft) {
            $job->update([
                'status' => 'completed',
                'progress' => 100,
                'result_draft_id' => $existingDraft->id,
                'completed_at' => $job->completed_at ?? now(),
            ]);

            return;
        }

        try {
            $service->generateForJob($job);
        } catch (Throwable $e) {
            // Only update job status to failed if no draft was created
            if (! BrandContentDraft::where('generation_job_id', $job->id)->exists()) {
                $job->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now(),
                ]);
            }

            throw $e;
        }
    }
}
