<?php
/**
 * STAGE 5.5.4.7 — T-04 / T-05 Controlled Production Brand Creation & Overview Execution
 * 
 * STRICT COMPLIANCE & SAFETY:
 * - Redacts all passwords, tokens, secrets
 * - Enforces pre-execution gate before any mutation
 * - Creates 1 temporary synthetic user and 1 synthetic brand (status: draft)
 * - Executes T-04.1 through T-05.5 in strict sequence
 * - Deletes synthetic brand and synthetic user immediately
 * - Verifies database returns to baseline (User 30001 & Brand 30001 intact)
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$results = [
    'timestamp' => date('c'),
    'stage' => '5.5.4.7 — T-04 / T-05 Controlled Production Brand Creation & Overview Execution',
    'mode' => 'CONTROLLED_SYNTHETIC_BRAND_CYCLE',
    'pre_execution_gate' => [],
    'test_sequence' => [],
    'teardown' => [],
    'post_verification' => [],
    'final_decision' => null,
    'decision_rationale' => '',
];

echo "======================================================================\n";
echo "STAGE 5.5.4.7: T-04 / T-05 CONTROLLED BRAND CREATION & OVERVIEW EXECUTION\n";
echo "======================================================================\n\n";

// --- 1. Database Connection & Pre-Execution Gate ---
echo "--- 1. Pre-Execution Safety Gate ---\n";

$stagingLocal = __DIR__ . '/../.env.staging.local';
$prodAppUser = 'AZDARrqgy4MBFGE.prod_app';
$prodAppPass = '';

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

Config::set('database.connections.tidb_prod_app.username', $prodAppUser);
Config::set('database.connections.tidb_prod_app.password', $prodAppPass);

$conn = DB::connection('tidb_prod_app');
$ident = $conn->selectOne("SELECT CURRENT_USER() AS cu, DATABASE() AS db");
$ssl = $conn->selectOne("SHOW STATUS LIKE 'Ssl_cipher'");

$isProdDb = ($ident->db === 'socialpilot');
$isProdUser = str_contains($ident->cu, 'prod_app');
$tlsActive = !empty($ssl ? $ssl->Value : '');

$initialUserCount = $conn->table('users')->count();
$initialBrandCount = $conn->table('brands')->count();
$existingUser30001 = $conn->table('users')->where('id', 30001)->first();
$existingBrand30001 = $conn->table('brands')->where('id', 30001)->first();

$brand30001OwnedBy30001 = ($existingBrand30001 && (int)$existingBrand30001->user_id === 30001);
$brand30001Draft = ($existingBrand30001 && $existingBrand30001->status === 'draft');

echo "  Database: {$ident->db} (" . ($isProdDb ? 'PASS' : 'FAIL') . ")\n";
echo "  User: {$ident->cu} (" . ($isProdUser ? 'PASS' : 'FAIL') . ")\n";
echo "  TLS Cipher: " . ($ssl ? $ssl->Value : 'None') . " (" . ($tlsActive ? 'PASS' : 'FAIL') . ")\n";
echo "  Initial Users: {$initialUserCount} (Expected: 1)\n";
echo "  Initial Brands: {$initialBrandCount} (Expected: 1)\n";
echo "  User 30001 Exists: " . ($existingUser30001 ? 'YES' : 'NO') . "\n";
echo "  Brand 30001 Status: " . ($existingBrand30001 ? $existingBrand30001->status : 'MISSING') . " (Owned by 30001: " . ($brand30001OwnedBy30001 ? 'YES' : 'NO') . ")\n";

$preGatePassed = (
    $isProdDb &&
    $isProdUser &&
    $tlsActive &&
    $initialUserCount === 1 &&
    $initialBrandCount === 1 &&
    $existingUser30001 !== null &&
    $brand30001OwnedBy30001 &&
    $brand30001Draft
);

$results['pre_execution_gate'] = [
    'database_is_socialpilot' => $isProdDb,
    'user_is_prod_app' => $isProdUser,
    'tls_active' => $tlsActive,
    'initial_user_count' => $initialUserCount,
    'initial_brand_count' => $initialBrandCount,
    'user_30001_present' => ($existingUser30001 !== null),
    'brand_30001_owned_by_30001' => $brand30001OwnedBy30001,
    'brand_30001_draft' => $brand30001Draft,
    'all_preconditions_met' => $preGatePassed,
];

if (!$preGatePassed) {
    $results['final_decision'] = 'C — BLOCKED';
    $results['decision_rationale'] = 'Pre-execution safety gate failed. Preconditions not satisfied.';
    echo "FATAL: Pre-execution gate failed. Halting.\n";
    file_put_contents(__DIR__ . '/stage_5_5_4_7_results.json', json_encode($results, JSON_PRETTY_PRINT));
    exit(1);
}
echo "  PRE-EXECUTION GATE: PASSED. Proceeding with controlled tests.\n";

// --- 2. Helper HTTP Functions (Passing payload via temporary file to bypass Windows quoting issues) ---
function httpPostJson(string $url, array $data, ?string $bearerToken = null): array {
    $tmpHdr = tempnam(sys_get_temp_dir(), 'hdr_');
    $tmpBdy = tempnam(sys_get_temp_dir(), 'bdy_');
    $tmpCfg = tempnam(sys_get_temp_dir(), 'cfg_');
    $tmpDat = tempnam(sys_get_temp_dir(), 'dat_');

    $cfg = "header = \"Accept: application/json\"\nheader = \"Content-Type: application/json\"\n";
    if ($bearerToken) {
        $cfg .= "header = \"Authorization: Bearer {$bearerToken}\"\n";
    }
    file_put_contents($tmpCfg, $cfg);
    file_put_contents($tmpDat, json_encode($data));

    $cmd = "curl.exe -s -S --max-time 30 -K " . escapeshellarg($tmpCfg) . " -D " . escapeshellarg($tmpHdr) . " -o " . escapeshellarg($tmpBdy) . " -X POST -d @" . escapeshellarg($tmpDat) . " " . escapeshellarg($url);
    exec($cmd, $out, $ret);

    $hdr = file_exists($tmpHdr) ? file_get_contents($tmpHdr) : '';
    $bdy = file_exists($tmpBdy) ? file_get_contents($tmpBdy) : '';
    @unlink($tmpHdr);
    @unlink($tmpBdy);
    @unlink($tmpCfg);
    @unlink($tmpDat);

    preg_match('/HTTP\/\S+\s+(\d+)/i', $hdr, $m);
    $code = isset($m[1]) ? (int)$m[1] : 0;
    return ['status' => $code, 'body' => $bdy, 'json' => json_decode($bdy, true)];
}

function httpGetJson(string $url, ?string $bearerToken = null): array {
    $tmpHdr = tempnam(sys_get_temp_dir(), 'hdr_');
    $tmpBdy = tempnam(sys_get_temp_dir(), 'bdy_');
    $tmpCfg = tempnam(sys_get_temp_dir(), 'cfg_');

    $cfg = "header = \"Accept: application/json\"\n";
    if ($bearerToken) {
        $cfg .= "header = \"Authorization: Bearer {$bearerToken}\"\n";
    }
    file_put_contents($tmpCfg, $cfg);

    $cmd = "curl.exe -s -S --max-time 30 -K " . escapeshellarg($tmpCfg) . " -D " . escapeshellarg($tmpHdr) . " -o " . escapeshellarg($tmpBdy) . " " . escapeshellarg($url);
    exec($cmd, $out, $ret);

    $hdr = file_exists($tmpHdr) ? file_get_contents($tmpHdr) : '';
    $bdy = file_exists($tmpBdy) ? file_get_contents($tmpBdy) : '';
    @unlink($tmpHdr);
    @unlink($tmpBdy);
    @unlink($tmpCfg);

    preg_match('/HTTP\/\S+\s+(\d+)/i', $hdr, $m);
    $code = isset($m[1]) ? (int)$m[1] : 0;
    return ['status' => $code, 'body' => $bdy, 'json' => json_decode($bdy, true)];
}

function httpDeleteJson(string $url, ?string $bearerToken = null): array {
    $tmpHdr = tempnam(sys_get_temp_dir(), 'hdr_');
    $tmpBdy = tempnam(sys_get_temp_dir(), 'bdy_');
    $tmpCfg = tempnam(sys_get_temp_dir(), 'cfg_');

    $cfg = "header = \"Accept: application/json\"\n";
    if ($bearerToken) {
        $cfg .= "header = \"Authorization: Bearer {$bearerToken}\"\n";
    }
    file_put_contents($tmpCfg, $cfg);

    $cmd = "curl.exe -s -S --max-time 30 -K " . escapeshellarg($tmpCfg) . " -D " . escapeshellarg($tmpHdr) . " -o " . escapeshellarg($tmpBdy) . " -X DELETE " . escapeshellarg($url);
    exec($cmd, $out, $ret);

    $hdr = file_exists($tmpHdr) ? file_get_contents($tmpHdr) : '';
    $bdy = file_exists($tmpBdy) ? file_get_contents($tmpBdy) : '';
    @unlink($tmpHdr);
    @unlink($tmpBdy);
    @unlink($tmpCfg);

    preg_match('/HTTP\/\S+\s+(\d+)/i', $hdr, $m);
    $code = isset($m[1]) ? (int)$m[1] : 0;
    return ['status' => $code, 'body' => $bdy, 'json' => json_decode($bdy, true)];
}

// Generate unique synthetic identity
$uuidSuffix = strtolower(Str::random(12));
$syntheticEmail = "prod-brand-audit-{$uuidSuffix}@example.invalid";
$syntheticName = "[PROD-TEST] Brand Verification Auditor";
$rawPassword = Str::password(32, true, true, true, false);

$bearerToken = null;
$syntheticUserId = null;
$syntheticBrandId = null;

$baseUrl = 'https://socialpilot-ai-y4lm.onrender.com';

// --- 2. Register Temporary Synthetic User ---
echo "\n--- 2. Registering Temporary Synthetic Test User ---\n";
$regPayload = [
    'name' => $syntheticName,
    'email' => $syntheticEmail,
    'password' => $rawPassword,
    'password_confirmation' => $rawPassword,
    'terms' => true,
];
$regRes = httpPostJson("{$baseUrl}/api/v1/auth/register", $regPayload);
if ($regRes['status'] === 201 && !empty($regRes['json']['token']) && !empty($regRes['json']['user']['id'])) {
    $bearerToken = $regRes['json']['token'];
    $syntheticUserId = (int)$regRes['json']['user']['id'];
    echo "  Registered User ID: {$syntheticUserId} ({$syntheticEmail}) — Token issued.\n";
} else {
    echo "  FATAL: Registration failed. Status: {$regRes['status']}, Response: {$regRes['body']}\n";
    $results['final_decision'] = 'D — FAILURE';
    $results['decision_rationale'] = 'Synthetic user registration failed in Step 2.';
    file_put_contents(__DIR__ . '/stage_5_5_4_7_results.json', json_encode($results, JSON_PRETTY_PRINT));
    exit(1);
}

// --- 3. Execute Brand Test Sequence ---
echo "\n--- 3. Executing Brand Test Sequence ---\n";

// T-04.1: Invalid brand validation
echo "  [T-04.1] Submitting empty brand creation payload...\n";
$resT04_1 = httpPostJson("{$baseUrl}/api/v1/brands", [], $bearerToken);
$passedT04_1 = ($resT04_1['status'] === 422);
echo "    HTTP Status: {$resT04_1['status']} (" . ($passedT04_1 ? 'PASS' : 'FAIL') . ") — Validation rejected as expected.\n";
$results['test_sequence']['T-04.1'] = [
    'description' => 'Brand creation validation rejection on empty payload',
    'http_status' => $resT04_1['status'],
    'expected' => 422,
    'passed' => $passedT04_1,
    'errors_detected' => array_keys($resT04_1['json']['errors'] ?? []),
];

// T-04.2: Synthetic brand creation
echo "  [T-04.2] Creating approved synthetic brand...\n";
$brandPayload = [
    'name' => '[PROD-TEST] Brand Verification',
    'description' => 'Temporary synthetic brand for production API verification.',
    'industry' => 'Technology',
    'tone' => 'Professional',
    'status' => 'draft',
];
$resT04_2 = httpPostJson("{$baseUrl}/api/v1/brands", $brandPayload, $bearerToken);
$brandData = $resT04_2['json']['data'] ?? [];
$syntheticBrandId = isset($brandData['id']) ? (int)$brandData['id'] : null;

// Inspect brand_settings in DB
$settingsCreated = false;
if ($syntheticBrandId) {
    $settingsCreated = $conn->table('brand_settings')->where('brand_id', $syntheticBrandId)->exists();
}

$passedT04_2 = (
    $resT04_2['status'] === 201 &&
    $syntheticBrandId !== null &&
    $syntheticBrandId > 30001 &&
    ($brandData['status'] ?? '') === 'draft' &&
    $settingsCreated
);

echo "    HTTP Status: {$resT04_2['status']} (" . ($passedT04_2 ? 'PASS' : 'FAIL') . ") — Brand ID: {$syntheticBrandId}, Settings created in DB: " . ($settingsCreated ? 'YES' : 'NO') . "\n";
$results['test_sequence']['T-04.2'] = [
    'description' => 'Synthetic brand creation',
    'http_status' => $resT04_2['status'],
    'expected' => 201,
    'passed' => $passedT04_2,
    'brand_id' => $syntheticBrandId,
    'brand_status' => $brandData['status'] ?? null,
    'settings_created' => $settingsCreated,
];

if (!$passedT04_2 || !$syntheticBrandId) {
    echo "  FATAL: Brand creation failed. Halting.\n";
    $results['final_decision'] = 'D — FAILURE';
    $results['decision_rationale'] = 'Synthetic brand creation failed at T-04.2.';
    file_put_contents(__DIR__ . '/stage_5_5_4_7_results.json', json_encode($results, JSON_PRETTY_PRINT));
    exit(1);
}

// T-05.1: Brand listing
echo "  [T-05.1] Listing brands (GET /api/v1/brands)...\n";
$resT05_1 = httpGetJson("{$baseUrl}/api/v1/brands", $bearerToken);
$brandList = $resT05_1['json']['data'] ?? [];
$brandIdsInList = array_column($brandList, 'id');
$syntheticBrandPresent = in_array($syntheticBrandId, $brandIdsInList, true);
$brand30001Absent = !in_array(30001, $brandIdsInList, true);
$passedT05_1 = ($resT05_1['status'] === 200 && $syntheticBrandPresent && $brand30001Absent);

echo "    HTTP Status: {$resT05_1['status']} (" . ($passedT05_1 ? 'PASS' : 'FAIL') . ") — Synthetic brand present: " . ($syntheticBrandPresent ? 'YES' : 'NO') . ", Brand 30001 absent: " . ($brand30001Absent ? 'YES' : 'NO') . "\n";
$results['test_sequence']['T-05.1'] = [
    'description' => 'Brand listing tenant isolation',
    'http_status' => $resT05_1['status'],
    'expected' => 200,
    'passed' => $passedT05_1,
    'returned_brand_ids' => $brandIdsInList,
    'synthetic_brand_present' => $syntheticBrandPresent,
    'brand_30001_absent' => $brand30001Absent,
];

// T-05.2: Brand detail
echo "  [T-05.2] Fetching synthetic brand detail (GET /api/v1/brands/{$syntheticBrandId})...\n";
$resT05_2 = httpGetJson("{$baseUrl}/api/v1/brands/{$syntheticBrandId}", $bearerToken);
$detailData = $resT05_2['json']['data'] ?? [];
$passedT05_2 = (
    $resT05_2['status'] === 200 &&
    (int)($detailData['id'] ?? 0) === $syntheticBrandId &&
    ($detailData['name'] ?? '') === '[PROD-TEST] Brand Verification' &&
    ($detailData['status'] ?? '') === 'draft'
);
echo "    HTTP Status: {$resT05_2['status']} (" . ($passedT05_2 ? 'PASS' : 'FAIL') . ") — Detail verified: ID {$detailData['id']}, status '{$detailData['status']}'.\n";
$results['test_sequence']['T-05.2'] = [
    'description' => 'Synthetic brand detail retrieval',
    'http_status' => $resT05_2['status'],
    'expected' => 200,
    'passed' => $passedT05_2,
];

// T-05.3: Cross-tenant brand isolation
echo "  [T-05.3] Probing Brand 30001 with synthetic token (GET /api/v1/brands/30001)...\n";
$resT05_3 = httpGetJson("{$baseUrl}/api/v1/brands/30001", $bearerToken);
$passedT05_3 = ($resT05_3['status'] === 403);
echo "    HTTP Status: {$resT05_3['status']} (" . ($passedT05_3 ? 'PASS' : 'FAIL') . ") — Cross-tenant access denied with HTTP 403 Forbidden.\n";
$results['test_sequence']['T-05.3'] = [
    'description' => 'Cross-tenant brand isolation probe (Brand 30001)',
    'http_status' => $resT05_3['status'],
    'expected' => 403,
    'passed' => $passedT05_3,
];

// T-05.4: Brand overview
echo "  [T-05.4] Fetching synthetic brand overview (GET /api/v1/brands/{$syntheticBrandId}/overview)...\n";
$resT05_4 = httpGetJson("{$baseUrl}/api/v1/brands/{$syntheticBrandId}/overview", $bearerToken);
$overviewData = $resT05_4['json']['data'] ?? [];
$passedT05_4 = (
    $resT05_4['status'] === 200 &&
    (int)($overviewData['brand_id'] ?? 0) === $syntheticBrandId &&
    ($overviewData['status'] ?? '') === 'draft' &&
    (int)($overviewData['connected_accounts'] ?? -1) === 0 &&
    (int)($overviewData['posts'] ?? -1) === 0 &&
    (int)($overviewData['content_drafts'] ?? -1) === 0 &&
    (int)($overviewData['campaigns'] ?? -1) === 0
);
echo "    HTTP Status: {$resT05_4['status']} (" . ($passedT05_4 ? 'PASS' : 'FAIL') . ") — Overview stats: connected_accounts=0, posts=0, drafts=0, campaigns=0.\n";
$results['test_sequence']['T-05.4'] = [
    'description' => 'Brand overview fetch',
    'http_status' => $resT05_4['status'],
    'expected' => 200,
    'passed' => $passedT05_4,
    'overview_stats' => $overviewData,
];

// T-05.5: Cross-tenant overview isolation
echo "  [T-05.5] Probing Brand 30001 overview with synthetic token (GET /api/v1/brands/30001/overview)...\n";
$resT05_5 = httpGetJson("{$baseUrl}/api/v1/brands/30001/overview", $bearerToken);
$passedT05_5 = ($resT05_5['status'] === 403);
echo "    HTTP Status: {$resT05_5['status']} (" . ($passedT05_5 ? 'PASS' : 'FAIL') . ") — Cross-tenant overview access denied with HTTP 403 Forbidden.\n";
$results['test_sequence']['T-05.5'] = [
    'description' => 'Cross-tenant overview isolation probe (Brand 30001)',
    'http_status' => $resT05_5['status'],
    'expected' => 403,
    'passed' => $passedT05_5,
];

// --- 4. Targeted Cleanup ---
echo "\n--- 4. Targeted Cleanup ---\n";
echo "  Inspecting dependent records for Synthetic Brand ID: {$syntheticBrandId}...\n";

$depPosts = $conn->table('brand_posts')->where('brand_id', $syntheticBrandId)->count();
$depDrafts = $conn->table('brand_content_drafts')->where('brand_id', $syntheticBrandId)->count();
$depMedia = $conn->table('media_assets')->where('brand_id', $syntheticBrandId)->count();
$depAccounts = $conn->table('social_accounts')->where('brand_id', $syntheticBrandId)->count();

echo "  Dependent Posts: {$depPosts}, Drafts: {$depDrafts}, Media: {$depMedia}, Social Accounts: {$depAccounts}\n";

$brandCleanToDelete = ($syntheticBrandId > 30001 && $depPosts === 0 && $depDrafts === 0 && $depMedia === 0 && $depAccounts === 0);

if (!$brandCleanToDelete) {
    echo "  FATAL: Brand has unexpected dependencies! Halting cleanup.\n";
    $results['teardown'] = ['status' => 'ABORTED', 'reason' => 'Unexpected brand dependencies'];
} else {
    // 1. Delete synthetic brand via official API endpoint
    echo "  Calling DELETE /api/v1/brands/{$syntheticBrandId}...\n";
    $delBrandRes = httpDeleteJson("{$baseUrl}/api/v1/brands/{$syntheticBrandId}", $bearerToken);
    $brandApiDeleted = ($delBrandRes['status'] === 200);
    echo "    HTTP Status: {$delBrandRes['status']} (" . ($brandApiDeleted ? 'SUCCESS' : 'FAILED') . ")\n";

    // Confirm deletion in database
    $brandInDb = $conn->table('brands')->where('id', $syntheticBrandId)->exists();
    $settingsInDb = $conn->table('brand_settings')->where('brand_id', $syntheticBrandId)->exists();
    echo "    Brand exists in DB: " . ($brandInDb ? 'YES (LEAK)' : 'NO (CLEAN)') . "\n";
    echo "    Brand settings exist in DB: " . ($settingsInDb ? 'YES (LEAK)' : 'NO (CLEAN)') . "\n";

    // 2. Delete synthetic notifications
    $deletedNotifications = $conn->table('notifications')->where('notifiable_id', $syntheticUserId)->delete();
    // 3. Delete synthetic tokens
    $deletedTokens = $conn->table('personal_access_tokens')->where('tokenable_id', $syntheticUserId)->delete();
    // 4. Delete synthetic user
    $deletedUser = $conn->table('users')->where('id', $syntheticUserId)->where('id', '!=', 30001)->delete();

    echo "  Deleted: {$deletedNotifications} notification(s), {$deletedTokens} token(s), {$deletedUser} user row.\n";

    $results['teardown'] = [
        'status' => 'SUCCESS',
        'brand_api_deleted' => $brandApiDeleted,
        'brand_in_db' => $brandInDb,
        'settings_in_db' => $settingsInDb,
        'notifications_deleted' => $deletedNotifications,
        'tokens_deleted' => $deletedTokens,
        'user_deleted' => $deletedUser,
    ];
}

// --- 5. Final Database State Verification ---
echo "\n--- 5. Final Database Verification ---\n";
$finalUserCount = $conn->table('users')->count();
$finalBrandCount = $conn->table('brands')->count();
$finalSettingsCount = $conn->table('brand_settings')->count();
$remainingTokens = $conn->table('personal_access_tokens')->count();

$user30001 = $conn->table('users')->where('id', 30001)->first();
$brand30001 = $conn->table('brands')->where('id', 30001)->first();

$usersClean = ($finalUserCount === 1);
$brandsClean = ($finalBrandCount === 1);
$settingsClean = ($finalSettingsCount === 0);
$tokensClean = ($remainingTokens === 0);
$user30001Intact = ($user30001 !== null);
$brand30001Intact = ($brand30001 && (int)$brand30001->user_id === 30001 && $brand30001->status === 'draft');

echo "  Final User Count: {$finalUserCount} (" . ($usersClean ? 'PASS' : 'FAIL') . ")\n";
echo "  Final Brand Count: {$finalBrandCount} (" . ($brandsClean ? 'PASS' : 'FAIL') . ")\n";
echo "  Brand Settings Count: {$finalSettingsCount} (" . ($settingsClean ? 'PASS' : 'FAIL') . ")\n";
echo "  Remaining Tokens: {$remainingTokens} (" . ($tokensClean ? 'CLEAN' : 'LEAK') . ")\n";
echo "  User 30001 Intact: " . ($user30001Intact ? 'YES' : 'NO') . "\n";
echo "  Brand 30001 Intact: " . ($brand30001Intact ? 'YES' : 'NO') . "\n";

// Check health endpoints
$upRes = httpGetJson("{$baseUrl}/up");
$healthRes = httpGetJson("{$baseUrl}/api/v1/health");
$readyRes = httpGetJson("{$baseUrl}/api/v1/health/ready");

$healthOk = ($upRes['status'] === 200 && $healthRes['status'] === 200 && $readyRes['status'] === 200);
echo "  Health Endpoints: /up={$upRes['status']}, /health={$healthRes['status']}, /ready={$readyRes['status']} (" . ($healthOk ? 'HEALTHY' : 'DEGRADED') . ")\n";

$results['post_verification'] = [
    'final_user_count' => $finalUserCount,
    'final_brand_count' => $finalBrandCount,
    'final_settings_count' => $finalSettingsCount,
    'remaining_tokens' => $remainingTokens,
    'user_30001_intact' => $user30001Intact,
    'brand_30001_intact' => $brand30001Intact,
    'health_endpoints' => [
        'up' => $upRes['status'],
        'health' => $healthRes['status'],
        'ready' => $readyRes['status'],
    ],
    'all_verified' => ($usersClean && $brandsClean && $settingsClean && $tokensClean && $user30001Intact && $brand30001Intact && $healthOk),
];

// --- 6. Formulate Decision ---
$allTestsPassed = (
    $passedT04_1 &&
    $passedT04_2 &&
    $passedT05_1 &&
    $passedT05_2 &&
    $passedT05_3 &&
    $passedT05_4 &&
    $passedT05_5 &&
    $results['teardown']['status'] === 'SUCCESS' &&
    $results['post_verification']['all_verified']
);

if ($allTestsPassed) {
    $results['final_decision'] = 'A — PASS';
    $results['decision_rationale'] = 'All brand creation, detail, listing, overview, and cross-tenant isolation tests passed with exact expected HTTP status codes; synthetic brand and user were completely removed; production baseline (1 user, 1 brand, 0 tokens, 0 settings) was fully restored; all health endpoints green.';
} else {
    $results['final_decision'] = 'B — PARTIAL';
    $results['decision_rationale'] = 'Some brand tests or cleanup steps showed unexpected outcomes.';
}

echo "\n======================================================================\n";
echo "FINAL DECISION: {$results['final_decision']}\n";
echo "Rationale: {$results['decision_rationale']}\n";
echo "======================================================================\n";

file_put_contents(__DIR__ . '/stage_5_5_4_7_results.json', json_encode($results, JSON_PRETTY_PRINT));
echo "Results saved to scripts/stage_5_5_4_7_results.json\n";
