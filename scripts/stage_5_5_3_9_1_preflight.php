<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$results = [];
$results['timestamp'] = date('c');
$results['stage'] = '5.5.3.9.1 — Pre-Flight & Staging Isolation Check';

echo "=== STAGE 5.5.3.9.1 — PRE-FLIGHT VERIFICATION ===\n\n";

// 1. Git Repository & Commit
$commitHash = trim(shell_exec('git rev-parse HEAD 2>nul') ?? '');
$branch = trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>nul') ?? '');
$results['git_branch'] = $branch;
$results['git_commit'] = $commitHash;
echo "Git Branch: {$branch}\n";
echo "Git Commit: {$commitHash}\n";

// 2. Dockerfile.unified check
$unifiedExists = file_exists(__DIR__ . '/../Dockerfile.unified');
$results['dockerfile_unified_exists'] = $unifiedExists;
echo "Dockerfile.unified exists: " . ($unifiedExists ? 'YES' : 'NO') . "\n";

// 3. Staging live health check
$ctx = stream_context_create([
    'http' => [
        'timeout' => 15,
        'ignore_errors' => true,
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
]);

$upHeaders = [];
$upResp = @file_get_contents('https://socialpilot-staging.onrender.com/up', false, $ctx);
$upStatus = $http_response_header[0] ?? 'NO_RESPONSE';
$results['staging_up_status'] = $upStatus;
echo "Staging /up check: {$upStatus}\n";

$healthResp = @file_get_contents('https://socialpilot-staging.onrender.com/api/v1/health', false, $ctx);
$healthStatus = $http_response_header[0] ?? 'NO_RESPONSE';
$results['staging_health_status'] = $healthStatus;
echo "Staging /api/v1/health check: {$healthStatus}\n";

// 4. Staging DB isolation check
try {
    $stagingMigs = DB::connection('tidb_staging_migrator')->table('migrations')->count();
    $results['staging_db_migrations'] = $stagingMigs;
    echo "Staging DB migrations count: {$stagingMigs} (Expected: 29)\n";
} catch (\Exception $e) {
    $results['staging_db_error'] = $e->getMessage();
    echo "Staging DB check error: " . $e->getMessage() . "\n";
}

file_put_contents(__DIR__ . '/stage_5_5_3_9_1_results.json', json_encode($results, JSON_PRETTY_PRINT));
echo "\nPre-flight check completed.\n";
