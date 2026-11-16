<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;

class InternalOpsController extends Controller
{
    /**
     * Execute a bounded operations cycle: run scheduler checks and process a bounded queue batch.
     */
    public function cycle(Request $request): JsonResponse
    {
        // 1. Timing-safe authentication
        $configuredSecret = (string) config('services.ops.cron_secret', env('INTERNAL_CRON_SECRET', ''));
        if ($configuredSecret === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal operations secret is not configured on the server.',
            ], 503);
        }

        $headerToken = (string) $request->bearerToken();
        if ($headerToken === '') {
            $headerToken = (string) $request->header('X-Ops-Secret', '');
        }

        if ($headerToken === '' || !hash_equals($configuredSecret, $headerToken)) {
            Log::warning('Unauthorized access attempt to internal ops cycle endpoint', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'status' => 'unauthorized',
                'message' => 'Invalid or missing internal operation credentials.',
            ], 401);
        }

        // 2. Prevent overlapping execution with an atomic cache lock (expires in 60s)
        $lock = Cache::lock('internal_ops_cycle_lock', 60);

        if (!$lock->get()) {
            return response()->json([
                'status' => 'locked',
                'message' => 'An operational cycle is already in progress.',
            ], 429);
        }

        $startTime = microtime(true);
        $scheduleOutput = new BufferedOutput();
        $queueOutput = new BufferedOutput();
        $scheduleExitCode = null;
        $queueExitCode = null;

        try {
            // 3. Phase 1: Execute Scheduler (bounded run)
            $scheduleExitCode = Artisan::call('schedule:run', [], $scheduleOutput);

            // 4. Phase 2: Execute Bounded Queue Worker
            // Strict boundaries: stop when empty, at most 10 jobs, at most 25 seconds runtime
            $queueExitCode = Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-jobs' => 10,
                '--max-time' => 25,
                '--tries' => 3,
            ], $queueOutput);

            $duration = round(microtime(true) - $startTime, 3);
            $memoryPeakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

            $responsePayload = [
                'status' => 'success',
                'timestamp' => now()->toIso8601String(),
                'duration_seconds' => $duration,
                'memory_peak_mb' => $memoryPeakMb,
                'scheduler' => [
                    'exit_code' => $scheduleExitCode,
                    'output' => trim($scheduleOutput->fetch()),
                ],
                'queue' => [
                    'exit_code' => $queueExitCode,
                    'output' => trim($queueOutput->fetch()),
                ],
            ];

            Log::info('Internal ops cycle completed successfully', [
                'duration' => $duration,
                'memory_peak_mb' => $memoryPeakMb,
                'scheduler_exit' => $scheduleExitCode,
                'queue_exit' => $queueExitCode,
            ]);

            return response()->json($responsePayload, 200);
        } catch (\Throwable $e) {
            Log::error('Internal ops cycle encountered an error', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Operational cycle failed: ' . $e->getMessage(),
            ], 500);
        } finally {
            $lock->release();
        }
    }
}
