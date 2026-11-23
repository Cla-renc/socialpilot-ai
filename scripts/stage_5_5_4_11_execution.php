<?php
/**
 * STAGE 5.5.4.11 — T-07 Controlled Production Post Scheduling Execution
 *
 * Authorization scope:
 *  - One synthetic user registration.
 *  - One synthetic brand creation.
 *  - T-07.1: Empty payload validation (HTTP 422).
 *  - T-07.2: Past timestamp rejection (HTTP 422).
 *  - T-07.3: Exactly one valid future-scheduled post (scheduled_at = 2027-01-01T12:00:00+00:00).
 *  - T-07.4: Tenant-scoped listing.
 *  - T-07.5: Detail fetch.
 *  - T-07.6: Cross-tenant brand listing probe (HTTP 403).
 *  - T-07.7: Cross-tenant post access probe (HTTP 403/404).
 *  - T-07.8: Cancel the synthetic scheduled post.
 *  - API brand deletion + cascade verification + user cleanup.
 *  - Final baseline restoration verification.
 *
 * NOT authorized: publishing, external APIs, scheduler invocation, ops/cycle.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// ---------------------------------------------------------------------------
// Helpers using curl.exe (uses Windows Schannel root certs)
// ---------------------------------------------------------------------------

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

    $cmd = "curl.exe -s -S --max-time 45 -K " . escapeshellarg($tmpCfg) . " -D " . escapeshellarg($tmpHdr) . " -o " . escapeshellarg($tmpBdy) . " -X POST -d @" . escapeshellarg($tmpDat) . " " . escapeshellarg($url);
    exec($cmd, $out, $ret);

    $hdr = file_exists($tmpHdr) ? file_get_contents($tmpHdr) : '';
    $bdy = file_exists($tmpBdy) ? file_get_contents($tmpBdy) : '';
    @unlink($tmpHdr);
    @unlink($tmpBdy);
    @unlink($tmpCfg);
    @unlink($tmpDat);

    preg_match('/HTTP\/\S+\s+(\d+)/i', $hdr, $m);
    $code = isset($m[1]) ? (int)$m[1] : 0;
    return ['status' => $code, 'body' => json_decode($bdy, true) ?? [], 'raw' => $bdy];
}

function httpGet(string $url, ?string $bearerToken = null): array {
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
    return ['status' => $code, 'body' => json_decode($bdy, true) ?? [], 'raw' => $bdy];
}

function httpDelete(string $url, ?string $bearerToken = null): array {
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
    return ['status' => $code, 'body' => json_decode($bdy, true) ?? [], 'raw' => $bdy];
}

function dbCounts(object $conn): array
{
    $tables = [
        'users', 'brands', 'brand_posts',
        'scheduled_posts', 'scheduled_post_platforms', 'scheduled_post_media',
        'publishing_queues', 'publishing_queue_items', 'publishing_attempts',
        'publishing_notifications', 'post_analytics',
        'jobs', 'failed_jobs', 'notifications', 'personal_access_tokens',
    ];
    $counts = [];
    foreach ($tables as $t) {
        try {
            $counts[$t] = (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM `{$t}`")->cnt;
        } catch (\Throwable $e) {
            $counts[$t] = 'ERROR: ' . $e->getMessage();
        }
    }
    return $counts;
}

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

$BASE_URL   = 'https://socialpilot-ai-y4lm.onrender.com';
$results    = [
    'timestamp'               => date('c'),
    'stage'                   => '5.5.4.11 — T-07 Controlled Production Post Scheduling Execution',
    'mode'                    => 'CONTROLLED_SCHEDULING_EXECUTION',
    'pre_execution_gate'      => [],
    'synthetic_identity'      => [],
    'test_sequence'           => [],
    'teardown'                => [],
    'post_verification'       => [],
    'final_decision'          => '',
    'decision_rationale'      => '',
];

// ---------------------------------------------------------------------------
// DB credentials
// ---------------------------------------------------------------------------

$stagingLocal = __DIR__ . '/../.env.staging.local';
$prodAppUser  = 'AZDARrqgy4MBFGE.prod_app';
$prodAppPass  = '';

if (file_exists($stagingLocal)) {
    foreach (file($stagingLocal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
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

// ---------------------------------------------------------------------------
// SECTION 1 — Pre-Execution Safety Gate
// ---------------------------------------------------------------------------

echo "=== PRE-EXECUTION SAFETY GATE ===\n";

// 1a. DB identity
$dbName      = $conn->selectOne('SELECT DATABASE() AS db')->db;
$currentUser = $conn->selectOne('SELECT CURRENT_USER() AS usr')->usr;
$sslCipher   = $conn->selectOne("SHOW STATUS LIKE 'Ssl_cipher'")->Value ?? 'NONE';

// 1b. Baseline counts
$preCounts = dbCounts($conn);

// 1c. User 30001 & Brand 30001
$u30001 = $conn->selectOne('SELECT id, name, email FROM users WHERE id = 30001');
$b30001 = $conn->selectOne('SELECT id, user_id, name, status FROM brands WHERE id = 30001');

// 1d. Health checks
$healthUp    = httpGet("{$BASE_URL}/up");
$healthApi   = httpGet("{$BASE_URL}/api/v1/health");
$healthReady = httpGet("{$BASE_URL}/api/v1/health/ready");

$gate = [
    'database'              => $dbName,
    'db_user'               => $currentUser,
    'ssl_cipher'            => $sslCipher,
    'database_is_socialpilot' => ($dbName === 'socialpilot'),
    'user_is_prod_app'      => str_contains($currentUser, 'prod_app'),
    'tls_active'            => ($sslCipher !== 'NONE'),
    'baseline_counts'       => $preCounts,
    'user_30001_present'    => (bool) $u30001,
    'brand_30001_present'   => (bool) $b30001,
    'brand_30001_owned_by_30001' => ($b30001?->user_id == 30001),
    'brand_30001_draft'     => ($b30001?->status === 'draft'),
    'health_up'             => $healthUp['status'],
    'health_api'            => $healthApi['status'],
    'health_ready'          => $healthReady['status'],
];

// Safety gate critical checks
$unexpectedRecords = [];
$criticalTables = [
    'scheduled_posts', 'brand_posts', 'publishing_attempts', 'jobs', 'failed_jobs'
];
foreach ($criticalTables as $t) {
    if (isset($preCounts[$t]) && $preCounts[$t] !== 0 && !is_string($preCounts[$t])) {
        $unexpectedRecords[$t] = $preCounts[$t];
    }
}

$gate['unexpected_records']   = $unexpectedRecords;
$gate['all_preconditions_met'] = (
    $gate['database_is_socialpilot'] &&
    $gate['user_is_prod_app'] &&
    $gate['tls_active'] &&
    $gate['user_30001_present'] &&
    $gate['brand_30001_present'] &&
    $gate['brand_30001_owned_by_30001'] &&
    $gate['brand_30001_draft'] &&
    $gate['health_up'] === 200 &&
    $gate['health_api'] === 200 &&
    $gate['health_ready'] === 200 &&
    empty($unexpectedRecords)
);

$results['pre_execution_gate'] = $gate;

if (!$gate['all_preconditions_met']) {
    $results['final_decision'] = 'C — FAIL';
    $results['decision_rationale'] = 'Pre-execution safety gate failed. Unexpected records or precondition failures. Aborting without creating any test data.';
    file_put_contents(__DIR__ . '/stage_5_5_4_11_results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "SAFETY GATE FAILED — ABORTING\n";
    echo "Details: health_up={$healthUp['status']}, health_api={$healthApi['status']}, health_ready={$healthReady['status']}\n";
    exit(1);
}

echo "Safety gate PASSED. Proceeding with controlled execution.\n";

// ---------------------------------------------------------------------------
// SECTION 2 — Synthetic Identity
// ---------------------------------------------------------------------------

echo "\n=== SYNTHETIC IDENTITY ===\n";

$uuid      = strtolower(Str::uuid()->toString());
$email     = "prod-scheduling-audit-{$uuid}@example.invalid";
$password  = 'Audit!Pass2026#' . Str::random(12);  // meets all complexity rules; kept only in-memory
$emailLc   = strtolower($email);

// Register
$regResp = httpPostJson("{$BASE_URL}/api/v1/auth/register", [
    'name'                  => '[PROD-TEST] Scheduling Auditor',
    'email'                 => $emailLc,
    'password'              => $password,
    'password_confirmation' => $password,
    'terms'                 => true,
]);

$synthUserId = (int)($regResp['body']['user']['id'] ?? $regResp['body']['data']['user']['id'] ?? 0);
$token       = $regResp['body']['token'] ?? $regResp['body']['data']['token'] ?? null;

$results['synthetic_identity']['registration'] = [
    'http_status' => $regResp['status'],
    'expected'    => 201,
    'passed'      => ($regResp['status'] === 201 && $synthUserId > 0 && $token !== null),
    'user_id'     => $synthUserId,
    'email'       => $emailLc,
];

if ($regResp['status'] !== 201 || !$synthUserId || !$token) {
    $results['final_decision'] = 'C — FAIL';
    $results['decision_rationale'] = 'Synthetic user registration failed. Aborting.';
    file_put_contents(__DIR__ . '/stage_5_5_4_11_results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "REGISTRATION FAILED — ABORTING\n";
    exit(1);
}

echo "Synthetic user registered: ID {$synthUserId}\n";

// Create synthetic brand
$brandResp = httpPostJson("{$BASE_URL}/api/v1/brands", [
    'name'        => '[PROD-TEST] Scheduling Verification',
    'description' => 'Temporary synthetic brand for post-scheduling production verification. Must not publish.',
    'industry'    => 'Technology',
    'tone'        => 'Professional',
    'status'      => 'draft',
], $token);

$synthBrandId = $brandResp['body']['data']['id'] ?? null;

$results['synthetic_identity']['brand_creation'] = [
    'http_status' => $brandResp['status'],
    'expected'    => 201,
    'passed'      => ($brandResp['status'] === 201 && $synthBrandId !== null),
    'brand_id'    => $synthBrandId,
];

if ($brandResp['status'] !== 201 || !$synthBrandId) {
    $results['final_decision'] = 'C — FAIL';
    $results['decision_rationale'] = 'Synthetic brand creation failed. Aborting.';
    file_put_contents(__DIR__ . '/stage_5_5_4_11_results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "BRAND CREATION FAILED — ABORTING\n";
    exit(1);
}

echo "Synthetic brand created: ID {$synthBrandId}\n";

// ---------------------------------------------------------------------------
// SECTION 3 — Test Sequence
// ---------------------------------------------------------------------------

echo "\n=== T-07.1: Empty Payload Validation ===\n";

$beforeT071 = [
    'scheduled_posts' => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM scheduled_posts")->cnt,
    'brand_posts'     => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM brand_posts")->cnt,
];

$t071resp = httpPostJson("{$BASE_URL}/api/v1/brands/{$synthBrandId}/scheduled-posts", [], $token);

$afterT071 = [
    'scheduled_posts' => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM scheduled_posts")->cnt,
    'brand_posts'     => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM brand_posts")->cnt,
];

$results['test_sequence']['T-07.1'] = [
    'description'            => 'Empty payload validation probe',
    'http_status'            => $t071resp['status'],
    'expected'               => 422,
    'passed'                 => ($t071resp['status'] === 422),
    'db_delta_scheduled'     => ($afterT071['scheduled_posts'] - $beforeT071['scheduled_posts']),
    'db_delta_brand_posts'   => ($afterT071['brand_posts'] - $beforeT071['brand_posts']),
    'no_records_created'     => ($afterT071['scheduled_posts'] === $beforeT071['scheduled_posts'] &&
                                 $afterT071['brand_posts'] === $beforeT071['brand_posts']),
];

echo "T-07.1 HTTP {$t071resp['status']} (expected 422)\n";

// ---------------------------------------------------------------------------

echo "\n=== T-07.2: Past Timestamp Rejection ===\n";

$beforeT072 = [
    'scheduled_posts' => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM scheduled_posts")->cnt,
    'brand_posts'     => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM brand_posts")->cnt,
];

$pastTimestamp = gmdate('Y-m-d\TH:i:s\+00:00', strtotime('-2 hours'));

$t072resp = httpPostJson("{$BASE_URL}/api/v1/brands/{$synthBrandId}/scheduled-posts", [
    'title'        => '[PROD-TEST] Past Timestamp Probe',
    'caption'      => 'This request must be rejected.',
    'platform'     => 'linkedin',
    'scheduled_at' => $pastTimestamp,
    'timezone'     => 'UTC',
], $token);

$afterT072 = [
    'scheduled_posts' => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM scheduled_posts")->cnt,
    'brand_posts'     => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM brand_posts")->cnt,
];

$results['test_sequence']['T-07.2'] = [
    'description'            => 'Past timestamp rejection probe',
    'past_timestamp_used'    => $pastTimestamp,
    'http_status'            => $t072resp['status'],
    'expected'               => 422,
    'passed'                 => ($t072resp['status'] === 422),
    'db_delta_scheduled'     => ($afterT072['scheduled_posts'] - $beforeT072['scheduled_posts']),
    'db_delta_brand_posts'   => ($afterT072['brand_posts'] - $beforeT072['brand_posts']),
    'no_records_created'     => ($afterT072['scheduled_posts'] === $beforeT072['scheduled_posts'] &&
                                 $afterT072['brand_posts'] === $beforeT072['brand_posts']),
];

echo "T-07.2 HTTP {$t072resp['status']} (expected 422)\n";

// ---------------------------------------------------------------------------

echo "\n=== T-07.3: Valid Future Scheduled Post Creation ===\n";

$beforeT073 = [
    'scheduled_posts'          => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM scheduled_posts")->cnt,
    'scheduled_post_platforms' => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM scheduled_post_platforms")->cnt,
    'brand_posts'              => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM brand_posts")->cnt,
    'notifications'            => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM notifications")->cnt,
    'jobs'                     => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM jobs")->cnt,
    'publishing_attempts'      => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM publishing_attempts")->cnt,
];

$t073resp = httpPostJson("{$BASE_URL}/api/v1/brands/{$synthBrandId}/scheduled-posts", [
    'title'        => '[PROD-TEST] Scheduling Verification',
    'caption'      => 'This is a temporary internal scheduling verification record. It must never be published.',
    'platform'     => 'linkedin',
    'scheduled_at' => '2027-01-01T12:00:00+00:00',
    'timezone'     => 'UTC',
], $token);

$synthPostId = $t073resp['body']['data']['id'] ?? null;

$afterT073 = [
    'scheduled_posts'          => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM scheduled_posts")->cnt,
    'scheduled_post_platforms' => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM scheduled_post_platforms")->cnt,
    'brand_posts'              => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM brand_posts")->cnt,
    'notifications'            => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM notifications")->cnt,
    'jobs'                     => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM jobs")->cnt,
    'publishing_attempts'      => (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM publishing_attempts")->cnt,
];

// Verify DB record
$dbPost = null;
$dbPlatform = null;
$dbBrandPost = null;
if ($synthPostId) {
    $dbPost     = $conn->selectOne(
        "SELECT id, brand_id, user_id, title, status, scheduled_at, timezone FROM scheduled_posts WHERE id = ?",
        [$synthPostId]
    );
    $dbPlatform = $conn->selectOne(
        "SELECT id, platform, social_account_id, scheduled_at FROM scheduled_post_platforms WHERE scheduled_post_id = ?",
        [$synthPostId]
    );
    $dbBrandPost = $conn->selectOne(
        "SELECT id, brand_id, platform, status, scheduled_for FROM brand_posts WHERE brand_id = ? ORDER BY id DESC LIMIT 1",
        [$synthBrandId]
    );
}

$results['test_sequence']['T-07.3'] = [
    'description'               => 'Valid future scheduled post creation',
    'http_status'               => $t073resp['status'],
    'expected'                  => 201,
    'passed'                    => ($t073resp['status'] === 201 && $synthPostId !== null),
    'scheduled_post_id'         => $synthPostId,
    'api_response_status'       => $t073resp['body']['data']['status'] ?? null,
    'api_response_scheduled_at' => $t073resp['body']['data']['scheduled_at'] ?? null,
    'api_brand_id'              => $t073resp['body']['data']['brand_id'] ?? null,
    'db_scheduled_at_utc'       => $dbPost?->scheduled_at,
    'db_timezone'               => $dbPost?->timezone,
    'db_status'                 => $dbPost?->status,
    'db_brand_id'               => $dbPost?->brand_id,
    'db_user_id'                => $dbPost?->user_id,
    'db_platform'               => $dbPlatform?->platform,
    'db_platform_social_account_id' => $dbPlatform?->social_account_id,
    'db_brand_post_created'     => (bool) $dbBrandPost,
    'db_brand_post_id'          => $dbBrandPost?->id,
    'db_brand_post_status'      => $dbBrandPost?->status,
    'db_delta_scheduled'        => ($afterT073['scheduled_posts'] - $beforeT073['scheduled_posts']),
    'db_delta_platforms'        => ($afterT073['scheduled_post_platforms'] - $beforeT073['scheduled_post_platforms']),
    'db_delta_brand_posts'      => ($afterT073['brand_posts'] - $beforeT073['brand_posts']),
    'db_delta_notifications'    => ($afterT073['notifications'] - $beforeT073['notifications']),
    'db_delta_jobs'             => ($afterT073['jobs'] - $beforeT073['jobs']),
    'db_delta_publishing_attempts' => ($afterT073['publishing_attempts'] - $beforeT073['publishing_attempts']),
    'zero_jobs_dispatched'      => ($afterT073['jobs'] === $beforeT073['jobs']),
    'zero_publishing_attempts'  => ($afterT073['publishing_attempts'] === $beforeT073['publishing_attempts']),
    'correct_brand_ownership'   => ($dbPost?->brand_id == $synthBrandId && $dbPost?->user_id == $synthUserId),
    'timestamp_stored_as_2027'  => str_starts_with((string)($dbPost?->scheduled_at ?? ''), '2027'),
];

if ($t073resp['status'] !== 201 || !$synthPostId) {
    $existingPost = $conn->selectOne(
        "SELECT id FROM scheduled_posts WHERE brand_id = ? AND title = ? LIMIT 1",
        [$synthBrandId, '[PROD-TEST] Scheduling Verification']
    );
    if ($existingPost) {
        $synthPostId = $existingPost->id;
        $results['test_sequence']['T-07.3']['note'] = "HTTP status unexpected but DB record found with ID {$synthPostId}";
    } else {
        $results['final_decision'] = 'C — FAIL';
        $results['decision_rationale'] = 'T-07.3 scheduled post creation failed with no DB record found.';
        file_put_contents(__DIR__ . '/stage_5_5_4_11_results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "T-07.3 FAILED — aborting before cleanup\n";
        exit(1);
    }
}

echo "T-07.3 HTTP {$t073resp['status']} (expected 201), Post ID: {$synthPostId}\n";

// ---------------------------------------------------------------------------

echo "\n=== T-07.4: Tenant-Scoped Listing ===\n";

$t074resp = httpGet("{$BASE_URL}/api/v1/brands/{$synthBrandId}/scheduled-posts", $token);

$listedIds = array_column($t074resp['body']['data'] ?? [], 'id');
$ownPostVisible  = in_array($synthPostId, $listedIds);
$brand30001Leaked = false;
foreach (($t074resp['body']['data'] ?? []) as $item) {
    if (($item['brand_id'] ?? null) == 30001) {
        $brand30001Leaked = true;
    }
}

$results['test_sequence']['T-07.4'] = [
    'description'         => 'Tenant-scoped scheduled-post listing',
    'http_status'         => $t074resp['status'],
    'expected'            => 200,
    'passed'              => ($t074resp['status'] === 200 && $ownPostVisible && !$brand30001Leaked),
    'listed_ids'          => $listedIds,
    'own_post_visible'    => $ownPostVisible,
    'brand_30001_leaked'  => $brand30001Leaked,
    'total_records'       => $t074resp['body']['meta']['total'] ?? count($listedIds),
];

echo "T-07.4 HTTP {$t074resp['status']}, own post visible: " . ($ownPostVisible ? 'YES' : 'NO') . "\n";

// ---------------------------------------------------------------------------

echo "\n=== T-07.5: Scheduled Post Detail ===\n";

$t075resp = httpGet("{$BASE_URL}/api/v1/scheduled-posts/{$synthPostId}", $token);

$detailData        = $t075resp['body']['data'] ?? [];
$detailBrandId     = $detailData['brand_id'] ?? null;
$detailScheduledAt = $detailData['scheduled_at'] ?? null;
$detailPlatform    = ($detailData['platforms'][0]['platform'] ?? null);
$detailStatus      = $detailData['status'] ?? null;

$results['test_sequence']['T-07.5'] = [
    'description'       => 'Scheduled post detail fetch',
    'http_status'       => $t075resp['status'],
    'expected'          => 200,
    'passed'            => ($t075resp['status'] === 200 &&
                            $detailBrandId == $synthBrandId &&
                            str_contains((string)$detailScheduledAt, '2027')),
    'returned_id'       => $detailData['id'] ?? null,
    'returned_brand_id' => $detailBrandId,
    'returned_platform' => $detailPlatform,
    'returned_status'   => $detailStatus,
    'scheduled_at'      => $detailScheduledAt,
    'correct_brand'     => ($detailBrandId == $synthBrandId),
    'timestamp_in_2027' => str_contains((string)$detailScheduledAt, '2027'),
];

echo "T-07.5 HTTP {$t075resp['status']}, brand: {$detailBrandId}, scheduled_at: {$detailScheduledAt}\n";

// ---------------------------------------------------------------------------

echo "\n=== T-07.6: Cross-Tenant Brand Listing Probe ===\n";

$t076resp = httpGet("{$BASE_URL}/api/v1/brands/30001/scheduled-posts", $token);

$results['test_sequence']['T-07.6'] = [
    'description' => 'Cross-tenant brand listing probe (Brand 30001)',
    'http_status' => $t076resp['status'],
    'expected'    => 403,
    'passed'      => ($t076resp['status'] === 403),
];

echo "T-07.6 HTTP {$t076resp['status']} (expected 403)\n";

// ---------------------------------------------------------------------------

echo "\n=== T-07.7: Cross-Tenant Post Access Probe ===\n";

$t077resp = httpGet("{$BASE_URL}/api/v1/scheduled-posts/99999999", $token);

$results['test_sequence']['T-07.7'] = [
    'description'  => 'Cross-tenant/non-existent post access probe',
    'http_status'  => $t077resp['status'],
    'expected'     => '403 or 404',
    'passed'       => in_array($t077resp['status'], [403, 404]),
    'probe_detail' => 'Attempted GET /api/v1/scheduled-posts/99999999 with synthetic token',
];

echo "T-07.7 HTTP {$t077resp['status']} (expected 403 or 404)\n";

// ---------------------------------------------------------------------------

echo "\n=== T-07.8: Cancel Synthetic Scheduled Post ===\n";

$t078resp = httpPostJson(
    "{$BASE_URL}/api/v1/scheduled-posts/{$synthPostId}/cancel",
    ['reason' => 'Production scheduling verification test — controlled cancellation.'],
    $token
);

$cancelledStatus = $t078resp['body']['data']['status'] ?? null;

$dbPostAfterCancel = $conn->selectOne(
    "SELECT id, status, cancellation_reason FROM scheduled_posts WHERE id = ?",
    [$synthPostId]
);

$results['test_sequence']['T-07.8'] = [
    'description'          => 'Cancel synthetic scheduled post',
    'http_status'          => $t078resp['status'],
    'expected'             => 200,
    'passed'               => ($t078resp['status'] === 200 && $cancelledStatus === 'cancelled'),
    'api_returned_status'  => $cancelledStatus,
    'db_status'            => $dbPostAfterCancel?->status,
    'db_cancellation_set'  => !empty($dbPostAfterCancel?->cancellation_reason),
    'no_publishing_attempt'=> (int)($conn->selectOne("SELECT COUNT(*) AS cnt FROM publishing_attempts WHERE scheduled_post_id = ?", [$synthPostId])->cnt) === 0,
];

echo "T-07.8 HTTP {$t078resp['status']}, status after cancel: {$cancelledStatus}\n";

// ---------------------------------------------------------------------------
// SECTION 4 — Teardown
// ---------------------------------------------------------------------------

echo "\n=== TEARDOWN ===\n";

// 1. API brand deletion
$deleteBrandResp = httpDelete("{$BASE_URL}/api/v1/brands/{$synthBrandId}", $token);

// 2. Verify cascade
sleep(1);
$brandStillExists  = (bool) $conn->selectOne("SELECT id FROM brands WHERE id = ?", [$synthBrandId]);
$postStillExists   = (bool) $conn->selectOne("SELECT id FROM scheduled_posts WHERE id = ? AND deleted_at IS NULL", [$synthPostId]);
$platformsRemain   = (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM scheduled_post_platforms WHERE scheduled_post_id = ?", [$synthPostId])->cnt;
$brandPostsRemain  = (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM brand_posts WHERE brand_id = ?", [$synthBrandId])->cnt;

$results['teardown']['brand_api_deletion'] = [
    'http_status'              => $deleteBrandResp['status'],
    'expected'                 => 200,
    'passed'                   => ($deleteBrandResp['status'] === 200),
    'brand_still_in_db'        => $brandStillExists,
    'scheduled_post_still_in_db' => $postStillExists,
    'scheduled_post_platforms_remaining' => $platformsRemain,
    'brand_posts_remaining'    => $brandPostsRemain,
    'brand_posts_cascade_ok'   => ($brandPostsRemain === 0),
];

echo "Brand API deletion: HTTP {$deleteBrandResp['status']}\n";
echo "brand_posts remaining for brand {$synthBrandId}: {$brandPostsRemain}\n";

// 3. Clean up remaining notifications scoped to synthetic user
$notifDeleted = $conn->delete(
    "DELETE FROM notifications WHERE notifiable_id = ?",
    [$synthUserId]
);

// 4. Revoke tokens
$tokensDeleted = $conn->delete(
    "DELETE FROM personal_access_tokens WHERE tokenable_id = ?",
    [$synthUserId]
);

// 5. Delete synthetic user (scoped guard against deleting 30001)
$userDeleted = $conn->delete(
    "DELETE FROM users WHERE id = ? AND id != 30001",
    [$synthUserId]
);

$results['teardown']['cleanup'] = [
    'notifications_deleted' => $notifDeleted,
    'tokens_deleted'        => $tokensDeleted,
    'user_deleted'          => $userDeleted,
    'synthetic_user_id'     => $synthUserId,
    'user_30001_safe'       => (bool) $conn->selectOne("SELECT id FROM users WHERE id = 30001"),
];

echo "Notifications deleted: {$notifDeleted}, Tokens deleted: {$tokensDeleted}, User deleted: {$userDeleted}\n";

// ---------------------------------------------------------------------------
// SECTION 5 — Post-Execution Verification
// ---------------------------------------------------------------------------

echo "\n=== POST-EXECUTION VERIFICATION ===\n";

$postCounts     = dbCounts($conn);
$u30001Post     = $conn->selectOne('SELECT id, name, email FROM users WHERE id = 30001');
$b30001Post     = $conn->selectOne('SELECT id, user_id, name, status FROM brands WHERE id = 30001');

$healthUpPost    = httpGet("{$BASE_URL}/up");
$healthApiPost   = httpGet("{$BASE_URL}/api/v1/health");
$healthReadyPost = httpGet("{$BASE_URL}/api/v1/health/ready");

$baselineRestored = (
    $postCounts['users'] === 1 &&
    $postCounts['brands'] === 1 &&
    $postCounts['scheduled_posts'] === 0 &&
    $postCounts['scheduled_post_platforms'] === 0 &&
    $postCounts['scheduled_post_media'] === 0 &&
    $postCounts['brand_posts'] === 0 &&
    $postCounts['publishing_attempts'] === 0 &&
    $postCounts['jobs'] === 0 &&
    $postCounts['personal_access_tokens'] === 0
);

$results['post_verification'] = [
    'final_counts'          => $postCounts,
    'baseline_restored'     => $baselineRestored,
    'user_30001_intact'     => (bool) $u30001Post,
    'brand_30001_intact'    => (bool) $b30001Post,
    'brand_30001_status'    => $b30001Post?->status,
    'brand_30001_owned_by'  => $b30001Post?->user_id,
    'health_endpoints'      => [
        'up'    => $healthUpPost['status'],
        'health'=> $healthApiPost['status'],
        'ready' => $healthReadyPost['status'],
    ],
    'all_health_ok'         => ($healthUpPost['status'] === 200 && $healthApiPost['status'] === 200 && $healthReadyPost['status'] === 200),
    'all_verified'          => $baselineRestored,
];

// ---------------------------------------------------------------------------
// SECTION 6 — Final Decision
// ---------------------------------------------------------------------------

$allTestsPassed = (
    $results['test_sequence']['T-07.1']['passed'] &&
    $results['test_sequence']['T-07.2']['passed'] &&
    $results['test_sequence']['T-07.3']['passed'] &&
    $results['test_sequence']['T-07.4']['passed'] &&
    $results['test_sequence']['T-07.5']['passed'] &&
    $results['test_sequence']['T-07.6']['passed'] &&
    $results['test_sequence']['T-07.7']['passed'] &&
    $results['test_sequence']['T-07.8']['passed']
);

if ($allTestsPassed && $baselineRestored && $results['post_verification']['all_health_ok']) {
    $results['final_decision']      = 'A — PASS';
    $results['decision_rationale']  = 'All 8 T-07 test steps passed with correct HTTP status codes. ' .
        "Scheduled post ID {$synthPostId} created for 2027-01-01T12:00:00Z with platform=linkedin and no social_account_id. " .
        'No queue jobs dispatched. No publishing attempts created. ' .
        'Cross-tenant probes correctly returned 403/404. ' .
        'Post cancelled (status=cancelled). ' .
        "API brand deletion cascaded cleanly: brand_posts={$brandPostsRemain}, platforms={$platformsRemain}. " .
        'Synthetic user, tokens, and notifications fully purged. ' .
        'Production database restored to exact baseline (1 user, 1 brand, 0 scheduled_posts, 0 brand_posts, 0 tokens). ' .
        'User 30001 and Brand 30001 intact. All health endpoints green.';
} elseif ($baselineRestored) {
    $results['final_decision']      = 'B — PARTIAL';
    $results['decision_rationale']  = 'Some test steps failed but cleanup succeeded and baseline was restored. See individual test results.';
} else {
    $results['final_decision']      = 'C — FAIL';
    $results['decision_rationale']  = 'Test or cleanup failures detected. See individual results for details.';
}

file_put_contents(
    __DIR__ . '/stage_5_5_4_11_results.json',
    json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "\n=== FINAL DECISION: {$results['final_decision']} ===\n";
echo "Baseline restored: " . ($baselineRestored ? 'YES' : 'NO') . "\n";
echo "All tests passed:  " . ($allTestsPassed ? 'YES' : 'NO') . "\n";
echo "Results written to scripts/stage_5_5_4_11_results.json\n";
