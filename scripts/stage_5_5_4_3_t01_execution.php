<?php
/**
 * STAGE 5.5.4.3 — T-01 Controlled Production Operational Cycle Verification Script
 * 
 * STRICT SECURITY & AUDIT PROTOCOL:
 * 1. Loads DB credentials and INTERNAL_CRON_SECRET securely.
 * 2. NEVER prints, echoes, logs, or exports secrets.
 * 3. Pre-execution safety check on all 10 tables + Brand 30001 draft status.
 * 4. Halts if ANY safety precondition is violated.
 * 5. Exactly ONE POST invocation to /api/internal/ops/cycle via curl with secret in temp config.
 * 6. Immediate post-execution verification of table counts, lock release, and health endpoints.
 * 7. Saves sanitized results to stage_5_5_4_3_t01_results.json.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

$results = [
    'timestamp' => date('c'),
    'stage' => '5.5.4.3 — T-01 Controlled Production Operational Cycle Verification',
    'mode' => 'CONTROLLED_SINGLE_INVOCATION',
    'cron_secret_validation' => [],
    'pre_execution_safety' => [],
    'invocation' => [],
    'post_execution_safety' => [],
    'endpoint_health' => [],
    'final_decision' => null,
    'decision_rationale' => '',
];

echo "======================================================================\n";
echo "STAGE 5.5.4.3: T-01 CONTROLLED PRODUCTION OPERATIONAL CYCLE VERIFICATION\n";
echo "======================================================================\n\n";

// --- 1. Load Credentials & Validate Secret ---
echo "--- 1. Loading Credentials & Validating Secret ---\n";
$stagingLocal = __DIR__ . '/../.env.staging.local';
$prodLocal = __DIR__ . '/../.env.production.local';

$prodAppUser = 'AZDARrqgy4MBFGE.prod_app';
$prodAppPass = '';
$cronSecret = '';

if (file_exists($stagingLocal)) {
    $lines = file($stagingLocal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_starts_with(trim($line), 'TIDB_PROD_APP_PASSWORD=')) {
            $prodAppPass = trim(substr(trim($line), strlen('TIDB_PROD_APP_PASSWORD=')));
        }
        if (str_starts_with(trim($line), 'TIDB_PROD_APP_USERNAME=')) {
            $prodAppUser = trim(substr(trim($line), strlen('TIDB_PROD_APP_USERNAME=')));
        }
    }
}

if (file_exists($prodLocal)) {
    $lines = file($prodLocal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_starts_with(trim($line), 'INTERNAL_CRON_SECRET=')) {
            $cronSecret = trim(substr(trim($line), strlen('INTERNAL_CRON_SECRET=')));
        }
    }
}

$secretLength = strlen($cronSecret);
$secretIsHex = ctype_xdigit($cronSecret);
$results['cron_secret_validation'] = [
    'present' => ($secretLength > 0),
    'length' => $secretLength,
    'is_hex' => $secretIsHex,
    'valid_format' => ($secretLength === 64 && $secretIsHex),
];

if (!$results['cron_secret_validation']['valid_format']) {
    $results['final_decision'] = 'C — BLOCKED';
    $results['decision_rationale'] = 'INTERNAL_CRON_SECRET is missing or invalid format in .env.production.local.';
    echo "  FATAL: INTERNAL_CRON_SECRET format check failed. Aborting.\n";
    file_put_contents(__DIR__ . '/stage_5_5_4_3_t01_results.json', json_encode($results, JSON_PRETTY_PRINT));
    exit(1);
}
echo "  INTERNAL_CRON_SECRET verified: present, {$secretLength} hex characters. (Secret redacted)\n";

// --- 2. Database Setup & Pre-Execution Safety Checks ---
echo "\n--- 2. Database Connection & Pre-Execution Safety Verification ---\n";
Config::set('database.connections.tidb_prod_app.username', $prodAppUser);
Config::set('database.connections.tidb_prod_app.password', $prodAppPass);

$conn = DB::connection('tidb_prod_app');
$ident = $conn->selectOne("SELECT CURRENT_USER() AS cu, DATABASE() AS db");
$ssl = $conn->selectOne("SHOW STATUS LIKE 'Ssl_cipher'");

$results['pre_execution_safety']['db_connection'] = [
    'user' => $ident->cu,
    'database' => $ident->db,
    'ssl_cipher' => $ssl ? $ssl->Value : 'None',
    'is_prod_app' => str_contains($ident->cu, 'prod_app'),
    'is_socialpilot' => ($ident->db === 'socialpilot'),
];

echo "  Connected as: {$ident->cu} on DB: {$ident->db} (TLS: " . ($ssl ? $ssl->Value : 'None') . ")\n";

$tablesToAudit = [
    'jobs' => 'Queue jobs',
    'failed_jobs' => 'Failed queue jobs',
    'cache_locks' => 'Distributed cache locks',
    'scheduled_posts' => 'Scheduled posts',
    'brand_posts' => 'Brand posts',
    'publishing_attempts' => 'Publishing attempts',
    'social_accounts' => 'Connected social accounts',
    'publishing_queues' => 'Publishing queues',
    'brand_trend_preferences' => 'Brand trend preferences',
    'trend_research_jobs' => 'Trend research jobs',
];

$preCounts = [];
foreach ($tablesToAudit as $table => $desc) {
    $cnt = $conn->table($table)->count();
    $preCounts[$table] = $cnt;
    echo sprintf("  [PRE] %-25s : %d rows\n", $table, $cnt);
}

$pendingJobs = $conn->table('jobs')->where('available_at', '<=', time())->count();
$delayedJobs = $conn->table('jobs')->where('available_at', '>', time())->count();
$reservedJobs = $conn->table('jobs')->whereNotNull('reserved_at')->count();
$preCounts['pending_jobs'] = $pendingJobs;
$preCounts['delayed_jobs'] = $delayedJobs;
$preCounts['reserved_jobs'] = $reservedJobs;

$existingBrand = $conn->table('brands')->where('id', 30001)->first();
$brand30001Status = $existingBrand ? $existingBrand->status : 'NOT_FOUND';
$preCounts['brand_30001_status'] = $brand30001Status;
echo "  [PRE] Brand 30001 Status: {$brand30001Status}\n";

$results['pre_execution_safety']['table_counts'] = $preCounts;

// Evaluate pre-execution safety gate
$preSafetyPassed = (
    $preCounts['jobs'] === 0 &&
    $preCounts['failed_jobs'] === 0 &&
    $preCounts['cache_locks'] === 0 &&
    $preCounts['scheduled_posts'] === 0 &&
    $preCounts['brand_posts'] === 0 &&
    $preCounts['publishing_attempts'] === 0 &&
    $preCounts['social_accounts'] === 0 &&
    $preCounts['publishing_queues'] === 0 &&
    $preCounts['brand_trend_preferences'] === 0 &&
    $preCounts['trend_research_jobs'] === 0 &&
    $brand30001Status === 'draft'
);

$results['pre_execution_safety']['all_checks_passed'] = $preSafetyPassed;

if (!$preSafetyPassed) {
    $results['final_decision'] = 'C — BLOCKED';
    $results['decision_rationale'] = 'Pre-execution safety gate failed. Unsafe state detected in database tables.';
    echo "  FATAL: Pre-execution safety conditions NOT satisfied. Halting without invocation.\n";
    file_put_contents(__DIR__ . '/stage_5_5_4_3_t01_results.json', json_encode($results, JSON_PRETTY_PRINT));
    exit(1);
}
echo "  PRE-EXECUTION SAFETY GATE: ALL 10 TABLE CHECKS + BRAND DRAFT STATUS PASSED.\n";

// --- 3. Execute Exactly One Invocation of POST /api/internal/ops/cycle ---
echo "\n--- 3. Invoking POST /api/internal/ops/cycle (Exactly Once) ---\n";

$endpointUrl = 'https://socialpilot-ai-y4lm.onrender.com/api/internal/ops/cycle';
$invocationTimestamp = date('c');
$startTime = microtime(true);

// Create temporary curl config file to avoid exposing secret on command line
$tempConfig = tempnam(sys_get_temp_dir(), 'curl_cfg_');
$tempHeader = tempnam(sys_get_temp_dir(), 'curl_hdr_');
$tempBody = tempnam(sys_get_temp_dir(), 'curl_bdy_');

$configContent = "header = \"Authorization: Bearer {$cronSecret}\"\nheader = \"Accept: application/json\"\nheader = \"User-Agent: SocialPilot-Preflight/1.0\"\n";
file_put_contents($tempConfig, $configContent);

// Command: exactly 1 invocation, 45s timeout, response headers and body separated
$cmd = "curl.exe -s -S --max-time 45 -K " . escapeshellarg($tempConfig) . " -D " . escapeshellarg($tempHeader) . " -o " . escapeshellarg($tempBody) . " -X POST " . escapeshellarg($endpointUrl);

exec($cmd, $cmdOutput, $cmdExitCode);
$duration = round(microtime(true) - $startTime, 3);

// Securely delete temporary config file containing secret immediately
@unlink($tempConfig);

$rawHeaders = file_exists($tempHeader) ? file_get_contents($tempHeader) : '';
$rawBody = file_exists($tempBody) ? file_get_contents($tempBody) : '';
@unlink($tempHeader);
@unlink($tempBody);

preg_match('/HTTP\/\S+\s+(\d+)/i', $rawHeaders, $httpMatches);
$httpStatus = isset($httpMatches[1]) ? (int)$httpMatches[1] : 0;

$decodedBody = json_decode($rawBody, true);

echo "  Invocation Timestamp: {$invocationTimestamp}\n";
echo "  HTTP Status: {$httpStatus}\n";
echo "  Execution Duration (Client-observed): {$duration}s\n";
echo "  Raw Response: " . substr($rawBody, 0, 500) . "\n";

$results['invocation'] = [
    'timestamp' => $invocationTimestamp,
    'endpoint' => $endpointUrl,
    'http_status' => $httpStatus,
    'client_duration_seconds' => $duration,
    'curl_exit_code' => $cmdExitCode,
    'raw_response' => $rawBody,
    'decoded_response' => $decodedBody,
];

// --- 4. Post-Execution Database Verification ---
echo "\n--- 4. Post-Execution Database Verification ---\n";
$postCounts = [];
foreach ($tablesToAudit as $table => $desc) {
    $cnt = $conn->table($table)->count();
    $postCounts[$table] = $cnt;
    echo sprintf("  [POST] %-25s : %d rows (delta: %+d)\n", $table, $cnt, $cnt - $preCounts[$table]);
}

$postPending = $conn->table('jobs')->where('available_at', '<=', time())->count();
$postDelayed = $conn->table('jobs')->where('available_at', '>', time())->count();
$postReserved = $conn->table('jobs')->whereNotNull('reserved_at')->count();
$postCounts['pending_jobs'] = $postPending;
$postCounts['delayed_jobs'] = $postDelayed;
$postCounts['reserved_jobs'] = $postReserved;

$activeLocks = $conn->table('cache_locks')->where('key', 'like', '%internal_ops_cycle_lock%')->count();
$results['post_execution_safety']['table_counts'] = $postCounts;
$results['post_execution_safety']['active_ops_cycle_locks'] = $activeLocks;
$results['post_execution_safety']['lock_cleanly_released'] = ($activeLocks === 0 && $postCounts['cache_locks'] === 0);

echo "  [POST] Active ops cycle locks: {$activeLocks}\n";
echo "  [POST] Lock cleanly released: " . ($results['post_execution_safety']['lock_cleanly_released'] ? 'YES' : 'NO') . "\n";

// --- 5. Post-Execution Health Endpoints Recheck ---
echo "\n--- 5. Post-Execution Health Endpoints Check ---\n";
$healthEndpoints = [
    'up' => '/up',
    'health' => '/api/v1/health',
    'ready' => '/api/v1/health/ready',
];

$allHealthOk = true;
foreach ($healthEndpoints as $name => $path) {
    $url = 'https://socialpilot-ai-y4lm.onrender.com' . $path;
    $tmpH = tempnam(sys_get_temp_dir(), 'hdr_');
    $tmpB = tempnam(sys_get_temp_dir(), 'bdy_');
    $c = "curl.exe -s -S --max-time 15 -D " . escapeshellarg($tmpH) . " -o " . escapeshellarg($tmpB) . " " . escapeshellarg($url);
    exec($c, $o, $r);
    $h = file_exists($tmpH) ? file_get_contents($tmpH) : '';
    $b = file_exists($tmpB) ? file_get_contents($tmpB) : '';
    @unlink($tmpH);
    @unlink($tmpB);
    
    preg_match('/HTTP\/\S+\s+(\d+)/i', $h, $m);
    $code = isset($m[1]) ? (int)$m[1] : 0;
    
    $results['endpoint_health'][$name] = [
        'path' => $path,
        'http_status' => $code,
        'body' => substr($b, 0, 200),
        'healthy' => ($code === 200),
    ];
    if ($code !== 200) {
        $allHealthOk = false;
    }
    echo "  [{$name}] {$path} => HTTP {$code}\n";
}

// --- 6. Decision Formulation ---
echo "\n--- 6. Decision Formulation ---\n";

$invocationSuccess = ($httpStatus === 200 && isset($decodedBody['status']) && $decodedBody['status'] === 'success');
$tablesUnchanged = true;
foreach ($tablesToAudit as $t => $d) {
    if ($postCounts[$t] !== $preCounts[$t]) {
        $tablesUnchanged = false;
    }
}
$lockReleased = $results['post_execution_safety']['lock_cleanly_released'];

if ($invocationSuccess && $tablesUnchanged && $lockReleased && $allHealthOk) {
    $results['final_decision'] = 'A — PASS';
    $results['decision_rationale'] = 'Exactly one invocation completed successfully (HTTP 200, status=success); lock was acquired and cleanly released; all table counts remained invariant (zero unwanted jobs, posts, or attempts); all health endpoints returned HTTP 200.';
} elseif ($httpStatus === 200 && (!$tablesUnchanged || !$lockReleased || !$allHealthOk)) {
    $results['final_decision'] = 'B — PARTIAL';
    $results['decision_rationale'] = 'Invocation completed with HTTP 200, but post-execution checks showed state inconsistencies or health degradation.';
} elseif ($httpStatus !== 200 && $cmdExitCode === 0) {
    $results['final_decision'] = 'D — FAILURE';
    $results['decision_rationale'] = "Invocation completed but returned HTTP {$httpStatus} with message: " . ($decodedBody['message'] ?? 'Unknown error');
} else {
    $results['final_decision'] = 'D — FAILURE';
    $results['decision_rationale'] = "Invocation failed with curl exit code {$cmdExitCode} or network timeout.";
}

echo "======================================================================\n";
echo "FINAL DECISION: {$results['final_decision']}\n";
echo "Rationale: {$results['decision_rationale']}\n";
echo "======================================================================\n";

file_put_contents(__DIR__ . '/stage_5_5_4_3_t01_results.json', json_encode($results, JSON_PRETTY_PRINT));
echo "\nResults saved to scripts/stage_5_5_4_3_t01_results.json\n";
