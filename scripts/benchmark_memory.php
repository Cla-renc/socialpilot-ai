<?php

// Enforce isolated local SQLite connection for benchmarking
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . __DIR__ . '/../database/database.sqlite');
putenv('CACHE_STORE=database');
putenv('QUEUE_CONNECTION=database');

$startTime = microtime(true);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Explicitly override config to ensure SQLite
config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => __DIR__ . '/../database/database.sqlite']);
config(['cache.default' => 'database']);
config(['queue.default' => 'database']);
config(['services.ops.cron_secret' => 'benchmark-secret-key-123']);

$bootMemory = memory_get_usage(true);
$bootPeakMemory = memory_get_peak_usage(true);
$bootTime = microtime(true) - $startTime;

echo "--- 1. LARAVEL BOOTSTRAP ---\n";
echo "Boot Memory: " . round($bootMemory / 1024 / 1024, 2) . " MB\n";
echo "Boot Peak Memory: " . round($bootPeakMemory / 1024 / 1024, 2) . " MB\n";
echo "Boot Duration: " . round($bootTime * 1000, 2) . " ms\n\n";

// 2. Simulate Laravel Request (/api/v1/health)
$requestStart = microtime(true);
$req = Illuminate\Http\Request::create('/api/v1/health', 'GET');
$response = $app->handle($req);
$requestMemory = memory_get_usage(true);
$requestPeakMemory = memory_get_peak_usage(true);
$requestTime = microtime(true) - $requestStart;

echo "--- 2. LARAVEL REQUEST (/api/v1/health) ---\n";
echo "Request Memory: " . round($requestMemory / 1024 / 1024, 2) . " MB\n";
echo "Request Peak Memory: " . round($requestPeakMemory / 1024 / 1024, 2) . " MB\n";
echo "Request Status: " . $response->getStatusCode() . "\n";
echo "Request Duration: " . round($requestTime * 1000, 2) . " ms\n\n";

// 3. Simulate Scheduler Execution
$schedStart = microtime(true);
$schedOutput = new Symfony\Component\Console\Output\BufferedOutput();
Illuminate\Support\Facades\Artisan::call('schedule:run', [], $schedOutput);
$schedMemory = memory_get_usage(true);
$schedPeakMemory = memory_get_peak_usage(true);
$schedTime = microtime(true) - $schedStart;

echo "--- 3. LARAVEL SCHEDULER EXECUTION ---\n";
echo "Scheduler Memory: " . round($schedMemory / 1024 / 1024, 2) . " MB\n";
echo "Scheduler Peak Memory: " . round($schedPeakMemory / 1024 / 1024, 2) . " MB\n";
echo "Scheduler Duration: " . round($schedTime * 1000, 2) . " ms\n\n";

// 4. Simulate Queue Worker Processing
$queueStart = microtime(true);
$queueOutput = new Symfony\Component\Console\Output\BufferedOutput();
Illuminate\Support\Facades\Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-jobs' => 5, '--max-time' => 5], $queueOutput);
$queueMemory = memory_get_usage(true);
$queuePeakMemory = memory_get_peak_usage(true);
$queueTime = microtime(true) - $queueStart;

echo "--- 4. LARAVEL QUEUE WORKER (Bounded) ---\n";
echo "Queue Worker Memory: " . round($queueMemory / 1024 / 1024, 2) . " MB\n";
echo "Queue Worker Peak Memory: " . round($queuePeakMemory / 1024 / 1024, 2) . " MB\n";
echo "Queue Duration: " . round($queueTime * 1000, 2) . " ms\n\n";

// 5. Simulate Internal Ops Cycle (/api/internal/ops/cycle)
$cycleStart = microtime(true);
$cycleReq = Illuminate\Http\Request::create('/api/internal/ops/cycle', 'POST', [], [], [], [
    'HTTP_AUTHORIZATION' => 'Bearer benchmark-secret-key-123',
]);
$cycleResponse = $app->handle($cycleReq);
$cycleMemory = memory_get_usage(true);
$cyclePeakMemory = memory_get_peak_usage(true);
$cycleTime = microtime(true) - $cycleStart;

echo "--- 5. INTERNAL OPS CYCLE (/api/internal/ops/cycle) ---\n";
echo "Cycle Memory: " . round($cycleMemory / 1024 / 1024, 2) . " MB\n";
echo "Cycle Peak Memory: " . round($cyclePeakMemory / 1024 / 1024, 2) . " MB\n";
echo "Cycle Status: " . $cycleResponse->getStatusCode() . "\n";
echo "Cycle Duration: " . round($cycleTime * 1000, 2) . " ms\n";
