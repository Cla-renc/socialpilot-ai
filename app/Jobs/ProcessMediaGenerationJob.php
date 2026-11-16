<?php

namespace App\Jobs;

use App\Models\MediaGenerationJob;
use App\Services\MediaGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessMediaGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public MediaGenerationJob $mediaJob)
    {
        if ($mediaJob->job_type === 'video_generation') {
            $this->timeout = 600;
        }
    }

    public function handle(MediaGenerationService $service): void
    {
        try {
            if ($this->mediaJob->job_type === 'video_generation') {
                $service->processVideoGeneration($this->mediaJob);
            } else {
                $service->processImageGeneration($this->mediaJob);
            }
        } catch (Throwable $e) {
            $this->mediaJob->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $this->mediaJob->update([
            'status'        => 'failed',
            'error_message' => $e->getMessage(),
            'completed_at'  => now(),
        ]);
    }
}
