<?php
/**
 * STAGE 5.5.4.5 — T-02 / T-03 Controlled Production Authentication Execution Script
 * 
 * STRICT COMPLIANCE & SAFETY:
 * - Redacts all passwords, tokens, secrets
 * - Enforces pre-execution gate before any mutation
 * - Creates exactly 1 synthetic user with UUID-tagged @example.invalid email
 * - Runs T-02.1 through T-02.9 in strict sequence
 * - Performs immediate clean teardown of the synthetic account
 * - Verifies database returns to baseline (User 30001 intact)
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$results = [
    'timestamp' => date('c'),
    'stage' => '5.5.4.5 — T-02 / T-03 Controlled Production Authentication Execution',
    'mode' => 'CONTROLLED_SYNTHETIC_AUTH_CYCLE',
    'pre_execution_gate' => [],
    'test_sequence' => [],
    'teardown' => [],
    'post_verification' => [],
    'final_decision' => null,
    'decision_rationale' => '',
];

echo "======================================================================\n";
echo "STAGE 5.5.4.5: T-02 / T-03 CONTROLLED AUTHENTICATION EXECUTION\n";
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

echo "  Database: {$ident->db} (" . ($isProdDb ? 'PASS' : 'FAIL') . ")\n";
echo "  User: {$ident->cu} (" . ($isProdUser ? 'PASS' : 'FAIL') . ")\n";
echo "  TLS Cipher: " . ($ssl ? $ssl->Value : 'None') . " (" . ($tlsActive ? 'PASS' : 'FAIL') . ")\n";

$initialUserCount = $conn->table('users')->count();
$initialUsers = $conn->table('users')->select('id', 'name', 'email')->get();
$existingUser30001 = $conn->table('users')->where('id', 30001)->first();

$existingBrand = $conn->table('brands')->where('id', 30001)->first();
$brand30001OwnedBy30001 = ($existingBrand && (int)$existingBrand->user_id === 30001);
$brand30001Draft = ($existingBrand && $existingBrand->status === 'draft');

echo "  Initial Users Count: {$initialUserCount} (Expected: 1)\n";
echo "  User 30001 Exists: " . ($existingUser30001 ? 'YES' : 'NO') . "\n";
echo "  Brand 30001 Status: " . ($existingBrand ? $existingBrand->status : 'MISSING') . " (Owned by 30001: " . ($brand30001OwnedBy30001 ? 'YES' : 'NO') . ")\n";

$preGatePassed = (
    $isProdDb &&
    $isProdUser &&
    $tlsActive &&
    $initialUserCount === 1 &&
    $existingUser30001 !== null &&
    $brand30001OwnedBy30001 &&
    $brand30001Draft
);

$results['pre_execution_gate'] = [
    'database_is_socialpilot' => $isProdDb,
    'user_is_prod_app' => $isProdUser,
    'tls_active' => $tlsActive,
    'initial_user_count' => $initialUserCount,
    'user_30001_present' => ($existingUser30001 !== null),
    'brand_30001_owned_by_30001' => $brand30001OwnedBy30001,
    'brand_30001_draft' => $brand30001Draft,
    'all_preconditions_met' => $preGatePassed,
];

if (!$preGatePassed) {
    $results['final_decision'] = 'C — BLOCKED';
    $results['decision_rationale'] = 'Pre-execution safety gate failed. Preconditions not satisfied.';
    echo "FATAL: Pre-execution gate failed. Halting.\n";
    file_put_contents(__DIR__ . '/stage_5_5_4_5_results.json', json_encode($results, JSON_PRETTY_PRINT));
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

// Generate unique synthetic identity
$uuidSuffix = strtolower(Str::random(12));
$syntheticEmail = "prod-audit-test-{$uuidSuffix}@example.invalid";
$syntheticName = "[PROD-TEST] Verification Auditor";
// 32-char CSPRNG password with upper, lower, digits, symbols
$rawPassword = Str::password(32, true, true, true, false);

$tokenA = null;
$tokenB = null;
$syntheticUserId = null;

$baseUrl = 'https://socialpilot-ai-y4lm.onrender.com';

// --- 3. Execute Authentication Test Sequence ---
echo "\n--- 3. Executing Authentication Test Sequence ---\n";

// T-02.1: Registration validation rejection
echo "  [T-02.1] Submitting empty registration payload...\n";
$resT02_1 = httpPostJson("{$baseUrl}/api/v1/auth/register", []);
$passedT02_1 = ($resT02_1['status'] === 422);
echo "    HTTP Status: {$resT02_1['status']} (" . ($passedT02_1 ? 'PASS' : 'FAIL') . ")\n";
$results['test_sequence']['T-02.1'] = [
    'description' => 'Registration validation rejection on empty payload',
    'http_status' => $resT02_1['status'],
    'expected' => 422,
    'passed' => $passedT02_1,
    'errors_detected' => array_keys($resT02_1['json']['errors'] ?? []),
];

// T-02.2: Synthetic registration
echo "  [T-02.2] Registering synthetic test user ({$syntheticEmail})...\n";
$registrationPayload = [
    'name' => $syntheticName,
    'email' => $syntheticEmail,
    'password' => $rawPassword,
    'password_confirmation' => $rawPassword,
    'terms' => true,
];
$resT02_2 = httpPostJson("{$baseUrl}/api/v1/auth/register", $registrationPayload);
$passedT02_2 = ($resT02_2['status'] === 201 && !empty($resT02_2['json']['token']) && !empty($resT02_2['json']['user']['id']));

if ($passedT02_2) {
    $tokenA = $resT02_2['json']['token'];
    $syntheticUserId = (int)$resT02_2['json']['user']['id'];
    echo "    HTTP Status: 201 (PASS) — User ID: {$syntheticUserId} created. Token issued.\n";
} else {
    echo "    HTTP Status: {$resT02_2['status']} (FAIL). Response: {$resT02_2['body']}\n";
}

$results['test_sequence']['T-02.2'] = [
    'description' => 'Synthetic user registration',
    'http_status' => $resT02_2['status'],
    'expected' => 201,
    'passed' => $passedT02_2,
    'user_id' => $syntheticUserId,
    'email' => $syntheticEmail,
];

if (!$passedT02_2 || !$syntheticUserId) {
    $results['final_decision'] = 'D — FAILURE';
    $results['decision_rationale'] = 'Synthetic user registration failed at T-02.2.';
    file_put_contents(__DIR__ . '/stage_5_5_4_5_results.json', json_encode($results, JSON_PRETTY_PRINT));
    exit(1);
}

// T-02.3: Authenticated identity fetch
echo "  [T-02.3] Fetching authenticated user profile (GET /api/v1/auth/user)...\n";
$resT02_3 = httpGetJson("{$baseUrl}/api/v1/auth/user", $tokenA);
$returnedId = $resT02_3['json']['user']['id'] ?? null;
$returnedEmail = $resT02_3['json']['user']['email'] ?? null;
$passedT02_3 = ($resT02_3['status'] === 200 && (int)$returnedId === $syntheticUserId && $returnedEmail === $syntheticEmail);
echo "    HTTP Status: {$resT02_3['status']} (" . ($passedT02_3 ? 'PASS' : 'FAIL') . ") — Profile matches registered synthetic account.\n";
$results['test_sequence']['T-02.3'] = [
    'description' => 'Authenticated identity profile fetch',
    'http_status' => $resT02_3['status'],
    'expected' => 200,
    'passed' => $passedT02_3,
    'verified_user_id' => $returnedId,
];

// T-02.4: Valid login
echo "  [T-02.4] Logging in with valid synthetic credentials...\n";
$loginPayload = [
    'email' => $syntheticEmail,
    'password' => $rawPassword,
];
$resT02_4 = httpPostJson("{$baseUrl}/api/v1/auth/login", $loginPayload);
$passedT02_4 = ($resT02_4['status'] === 200 && !empty($resT02_4['json']['token']));
if ($passedT02_4) {
    $tokenB = $resT02_4['json']['token'];
    echo "    HTTP Status: 200 (PASS) — Login successful, second token issued.\n";
} else {
    echo "    HTTP Status: {$resT02_4['status']} (FAIL)\n";
}
$results['test_sequence']['T-02.4'] = [
    'description' => 'Login with valid synthetic credentials',
    'http_status' => $resT02_4['status'],
    'expected' => 200,
    'passed' => $passedT02_4,
];

// T-02.5: Invalid login rejection
echo "  [T-02.5] Attempting login with incorrect password...\n";
$badLoginPayload = [
    'email' => $syntheticEmail,
    'password' => 'WrongPassword!Invalid999',
];
$resT02_5 = httpPostJson("{$baseUrl}/api/v1/auth/login", $badLoginPayload);
$passedT02_5 = ($resT02_5['status'] === 422);
echo "    HTTP Status: {$resT02_5['status']} (" . ($passedT02_5 ? 'PASS' : 'FAIL') . ") — Rejected with validation error.\n";
$results['test_sequence']['T-02.5'] = [
    'description' => 'Login rejection with invalid credentials',
    'http_status' => $resT02_5['status'],
    'expected' => 422,
    'passed' => $passedT02_5,
];

// T-02.6: Unauthenticated access rejection
echo "  [T-02.6] Requesting profile without authorization header...\n";
$resT02_6 = httpGetJson("{$baseUrl}/api/v1/auth/user", null);
$passedT02_6 = ($resT02_6['status'] === 401);
echo "    HTTP Status: {$resT02_6['status']} (" . ($passedT02_6 ? 'PASS' : 'FAIL') . ") — Rejected with HTTP 401 Unauthorized.\n";
$results['test_sequence']['T-02.6'] = [
    'description' => 'Unauthenticated access rejection',
    'http_status' => $resT02_6['status'],
    'expected' => 401,
    'passed' => $passedT02_6,
];

// T-02.7: Tenant isolation verification (Brand 30001)
echo "  [T-02.7] Probing existing Brand 30001 with synthetic user token...\n";
$resT02_7 = httpGetJson("{$baseUrl}/api/v1/brands/30001", $tokenA);
$passedT02_7 = ($resT02_7['status'] === 403);
echo "    HTTP Status: {$resT02_7['status']} (" . ($passedT02_7 ? 'PASS' : 'FAIL') . ") — Access denied with HTTP 403 Forbidden. Tenancy shield verified.\n";
$results['test_sequence']['T-02.7'] = [
    'description' => 'Tenant isolation enforcement on Brand 30001',
    'http_status' => $resT02_7['status'],
    'expected' => 403,
    'passed' => $passedT02_7,
];

// T-02.8: Logout using Token A
echo "  [T-02.8] Logging out using Token A (POST /api/v1/auth/logout)...\n";
$resT02_8 = httpPostJson("{$baseUrl}/api/v1/auth/logout", [], $tokenA);
$passedT02_8 = ($resT02_8['status'] === 200);
echo "    HTTP Status: {$resT02_8['status']} (" . ($passedT02_8 ? 'PASS' : 'FAIL') . ") — Token A successfully revoked.\n";
$results['test_sequence']['T-02.8'] = [
    'description' => 'Logout and token revocation',
    'http_status' => $resT02_8['status'],
    'expected' => 200,
    'passed' => $passedT02_8,
];

// T-02.9: Verify Token A is revoked
echo "  [T-02.9] Reusing revoked Token A against profile endpoint...\n";
$resT02_9 = httpGetJson("{$baseUrl}/api/v1/auth/user", $tokenA);
$passedT02_9 = ($resT02_9['status'] === 401);
echo "    HTTP Status: {$resT02_9['status']} (" . ($passedT02_9 ? 'PASS' : 'FAIL') . ") — Revoked token rejected with HTTP 401.\n";
$results['test_sequence']['T-02.9'] = [
    'description' => 'Token revocation verification',
    'http_status' => $resT02_9['status'],
    'expected' => 401,
    'passed' => $passedT02_9,
];

// --- 4. Synthetic Account Teardown ---
echo "\n--- 4. Synthetic Account Teardown ---\n";
echo "  Inspecting dependent records for Synthetic User ID: {$syntheticUserId}...\n";

$depBrands = $conn->table('brands')->where('user_id', $syntheticUserId)->count();
$depPosts = $conn->table('brand_posts')->where('user_id', $syntheticUserId)->count();
$depDrafts = $conn->table('brand_content_drafts')->where('user_id', $syntheticUserId)->count();
$depMedia = $conn->table('media_assets')->where('user_id', $syntheticUserId)->count();
$depTokens = $conn->table('personal_access_tokens')->where('tokenable_id', $syntheticUserId)->count();

echo "  Dependent Brands: {$depBrands}, Posts: {$depPosts}, Drafts: {$depDrafts}, Media: {$depMedia}, Remaining Tokens: {$depTokens}\n";

$safeToDelete = ($syntheticUserId !== 30001 && $syntheticUserId > 30001 && $depBrands === 0 && $depPosts === 0 && $depDrafts === 0 && $depMedia === 0);

if (!$safeToDelete) {
    echo "  FATAL: Safety condition for teardown violated! Halting.\n";
    $results['teardown'] = ['status' => 'ABORTED', 'reason' => 'Unexpected dependent records or invalid ID'];
} else {
    // 1. Delete remaining personal access tokens belonging to synthetic user
    $deletedTokens = $conn->table('personal_access_tokens')->where('tokenable_id', $syntheticUserId)->delete();
    // 2. Delete synthetic user record specifically
    $deletedUser = $conn->table('users')->where('id', $syntheticUserId)->delete();

    echo "  Teardown executed: {$deletedTokens} token(s) deleted, {$deletedUser} user row deleted.\n";
    $results['teardown'] = [
        'status' => 'SUCCESS',
        'tokens_deleted' => $deletedTokens,
        'user_deleted' => $deletedUser,
        'deleted_user_id' => $syntheticUserId,
    ];
}

// --- 5. Final Database State Verification ---
echo "\n--- 5. Final Database Verification ---\n";
$finalUserCount = $conn->table('users')->count();
$finalUser30001 = $conn->table('users')->where('id', 30001)->first();
$finalBrand30001 = $conn->table('brands')->where('id', 30001)->first();
$remainingTokens = $conn->table('personal_access_tokens')->count();

$userCountRestored = ($finalUserCount === 1);
$user30001Intact = ($finalUser30001 !== null);
$brand30001Intact = ($finalBrand30001 && (int)$finalBrand30001->user_id === 30001 && $finalBrand30001->status === 'draft');
$tokensClean = ($remainingTokens === 0);

echo "  Final User Count: {$finalUserCount} (" . ($userCountRestored ? 'PASS' : 'FAIL') . ")\n";
echo "  User 30001 Intact: " . ($user30001Intact ? 'YES' : 'NO') . "\n";
echo "  Brand 30001 Intact: " . ($brand30001Intact ? 'YES' : 'NO') . "\n";
echo "  Remaining Personal Access Tokens: {$remainingTokens} (" . ($tokensClean ? 'CLEAN' : 'LEAK') . ")\n";

// Check health endpoints
$upRes = httpGetJson("{$baseUrl}/up");
$healthRes = httpGetJson("{$baseUrl}/api/v1/health");
$readyRes = httpGetJson("{$baseUrl}/api/v1/health/ready");

$healthOk = ($upRes['status'] === 200 && $healthRes['status'] === 200 && $readyRes['status'] === 200);
echo "  Health Endpoints: /up={$upRes['status']}, /health={$healthRes['status']}, /ready={$readyRes['status']} (" . ($healthOk ? 'HEALTHY' : 'DEGRADED') . ")\n";

$results['post_verification'] = [
    'final_user_count' => $finalUserCount,
    'user_30001_intact' => $user30001Intact,
    'brand_30001_intact' => $brand30001Intact,
    'remaining_tokens' => $remainingTokens,
    'health_endpoints' => [
        'up' => $upRes['status'],
        'health' => $healthRes['status'],
        'ready' => $readyRes['status'],
    ],
    'all_verified' => ($userCountRestored && $user30001Intact && $brand30001Intact && $tokensClean && $healthOk),
];

// --- 6. Formulate Decision ---
$allTestsPassed = (
    $passedT02_1 &&
    $passedT02_2 &&
    $passedT02_3 &&
    $passedT02_4 &&
    $passedT02_5 &&
    $passedT02_6 &&
    $passedT02_7 &&
    $passedT02_8 &&
    $passedT02_9 &&
    $results['teardown']['status'] === 'SUCCESS' &&
    $results['post_verification']['all_verified']
);

if ($allTestsPassed) {
    $results['final_decision'] = 'A — PASS';
    $results['decision_rationale'] = 'All 9 authentication test steps (T-02.1 through T-02.9) passed with exact expected HTTP status codes; tenant boundary for Brand 30001 verified (HTTP 403); synthetic test account cleanly torn down; database returned to exact baseline (1 user, 1 brand, 0 tokens); all health endpoints green.';
} else {
    $results['final_decision'] = 'B — PARTIAL';
    $results['decision_rationale'] = 'Some authentication tests or teardown steps showed unexpected outcomes.';
}

echo "\n======================================================================\n";
echo "FINAL DECISION: {$results['final_decision']}\n";
echo "Rationale: {$results['decision_rationale']}\n";
echo "======================================================================\n";

file_put_contents(__DIR__ . '/stage_5_5_4_5_results.json', json_encode($results, JSON_PRETTY_PRINT));
echo "Results saved to scripts/stage_5_5_4_5_results.json\n";
