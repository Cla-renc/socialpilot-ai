<?php
/**
 * STAGE 5.5.4.13 — T-08 Controlled Production Synthetic Media Execution
 *
 * Authorization Scope:
 *  - One temporary synthetic user ([PROD-TEST] Media Storage Auditor).
 *  - One temporary synthetic brand ([PROD-TEST] Media Storage Verification).
 *  - One minimal, valid 68-byte PNG image generated locally.
 *  - Exactly one media upload via POST /api/v1/brands/{brand}/media/upload.
 *  - T-08.1: Upload verification (HTTP 201).
 *  - T-08.2: Database persistence verification.
 *  - T-08.3: Supabase Storage object existence check.
 *  - T-08.4: Authenticated media retrieval (GET /api/v1/media/{id} -> HTTP 200).
 *  - T-08.5: Cross-tenant access verification (Brand 30001 media probe -> HTTP 403).
 *  - T-08.6: API deletion of media (DELETE /api/v1/media/{id} -> HTTP 200).
 *  - T-08.7: Storage object deletion verification (S3 object gone).
 *  - Brand API deletion + scoped user cleanup.
 *  - Final baseline restoration verification.
 *
 * Protected Production Assets:
 *  - User 30001, Brand 30001, User 344308 MUST REMAIN INTACT.
 */

$caPath = 'C:\\Program Files\\Git\\mingw64\\etc\\ssl\\certs\\ca-bundle.crt';
if (file_exists($caPath)) {
    ini_set('curl.cainfo', $caPath);
    ini_set('openssl.cafile', $caPath);
}

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

if (file_exists($caPath)) {
    Config::set('filesystems.disks.supabase.http.verify', $caPath);
    Config::set('filesystems.disks.supabase.client.http.verify', $caPath);
}

// ---------------------------------------------------------------------------
// Helpers
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

function httpPostMultipart(string $url, array $fields, array $files, ?string $bearerToken = null): array {
    $tmpHdr = tempnam(sys_get_temp_dir(), 'hdr_');
    $tmpBdy = tempnam(sys_get_temp_dir(), 'bdy_');
    $tmpCfg = tempnam(sys_get_temp_dir(), 'cfg_');

    $cfg = "header = \"Accept: application/json\"\n";
    if ($bearerToken) {
        $cfg .= "header = \"Authorization: Bearer {$bearerToken}\"\n";
    }
    file_put_contents($tmpCfg, $cfg);

    $formArgs = [];
    foreach ($fields as $k => $v) {
        $formArgs[] = "-F " . escapeshellarg("{$k}={$v}");
    }
    foreach ($files as $k => $path) {
        $formArgs[] = "-F " . escapeshellarg("{$k}=@{$path};type=image/png");
    }

    $cmd = "curl.exe -s -S --max-time 60 -K " . escapeshellarg($tmpCfg) . " -D " . escapeshellarg($tmpHdr) . " -o " . escapeshellarg($tmpBdy) . " " . implode(' ', $formArgs) . " " . escapeshellarg($url);
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
        'users', 'brands', 'media_assets', 'media_generation_jobs',
        'media_storyboards', 'scheduled_post_media', 'brand_content_drafts',
        'scheduled_posts', 'publishing_attempts', 'jobs', 'failed_jobs',
        'notifications', 'personal_access_tokens',
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
// Bootstrap & Configuration
// ---------------------------------------------------------------------------

$BASE_URL = 'https://socialpilot-ai-y4lm.onrender.com';
$results  = [
    'timestamp'          => date('c'),
    'stage'              => '5.5.4.13 — T-08 Controlled Production Synthetic Media Execution',
    'mode'               => 'CONTROLLED_SYNTHETIC_MEDIA_EXECUTION',
    'pre_execution_gate' => [],
    'synthetic_identity' => [],
    'local_test_file'    => [],
    'test_sequence'      => [],
    'teardown'           => [],
    'post_verification'  => [],
    'final_decision'     => '',
    'decision_rationale' => '',
];

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

$dbName      = $conn->selectOne('SELECT DATABASE() AS db')->db;
$currentUser = $conn->selectOne('SELECT CURRENT_USER() AS usr')->usr;
$sslCipher   = $conn->selectOne("SHOW STATUS LIKE 'Ssl_cipher'")->Value ?? 'NONE';

$preCounts = dbCounts($conn);

$u30001 = $conn->selectOne('SELECT id, name, email FROM users WHERE id = 30001');
$b30001 = $conn->selectOne('SELECT id, user_id, name, status FROM brands WHERE id = 30001');
$u344308 = $conn->selectOne('SELECT id, name, email FROM users WHERE id = 344308');

$healthUp    = httpGet("{$BASE_URL}/up");
$healthApi   = httpGet("{$BASE_URL}/api/v1/health");
$healthReady = httpGet("{$BASE_URL}/api/v1/health/ready");

// Storage baseline inspection
$storageDisk = Storage::disk('supabase');
$initialStorageFiles = $storageDisk->allFiles();
$storageBaselineCount = count($initialStorageFiles);

$gate = [
    'database'                   => $dbName,
    'db_user'                    => $currentUser,
    'ssl_cipher'                 => $sslCipher,
    'database_is_socialpilot'    => ($dbName === 'socialpilot'),
    'user_is_prod_app'           => str_contains($currentUser, 'prod_app'),
    'tls_active'                 => ($sslCipher !== 'NONE'),
    'baseline_counts'            => $preCounts,
    'storage_baseline_files'     => $storageBaselineCount,
    'user_30001_present'         => (bool) $u30001,
    'brand_30001_present'        => (bool) $b30001,
    'brand_30001_owned_by_30001' => ($b30001?->user_id == 30001),
    'brand_30001_draft'          => ($b30001?->status === 'draft'),
    'user_344308_present'        => (bool) $u344308,
    'health_up'                  => $healthUp['status'],
    'health_api'                 => $healthApi['status'],
    'health_ready'               => $healthReady['status'],
];

$gate['all_preconditions_met'] = (
    $gate['database_is_socialpilot'] &&
    $gate['user_is_prod_app'] &&
    $gate['tls_active'] &&
    $gate['user_30001_present'] &&
    $gate['brand_30001_present'] &&
    $gate['brand_30001_owned_by_30001'] &&
    $gate['brand_30001_draft'] &&
    $gate['user_344308_present'] &&
    $preCounts['media_assets'] === 0 &&
    $preCounts['media_generation_jobs'] === 0 &&
    $preCounts['media_storyboards'] === 0 &&
    $storageBaselineCount === 0 &&
    $gate['health_up'] === 200 &&
    $gate['health_api'] === 200 &&
    $gate['health_ready'] === 200
);

$results['pre_execution_gate'] = $gate;

if (!$gate['all_preconditions_met']) {
    $results['final_decision'] = 'C — FAIL';
    $results['decision_rationale'] = 'Pre-execution safety gate failed. Preconditions not met.';
    file_put_contents(__DIR__ . '/stage_5_5_4_13_results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "SAFETY GATE FAILED — ABORTING\n";
    exit(1);
}

echo "Safety gate PASSED. Storage baseline: {$storageBaselineCount} files. Proceeding.\n";

// ---------------------------------------------------------------------------
// SECTION 2 — Synthetic Identity
// ---------------------------------------------------------------------------

echo "\n=== SYNTHETIC IDENTITY ===\n";

$uuid      = strtolower(Str::uuid()->toString());
$email     = "prod-media-audit-{$uuid}@example.invalid";
$password  = 'Media!Pass2026#' . Str::random(12);
$emailLc   = strtolower($email);

$regResp = httpPostJson("{$BASE_URL}/api/v1/auth/register", [
    'name'                  => '[PROD-TEST] Media Storage Auditor',
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
    file_put_contents(__DIR__ . '/stage_5_5_4_13_results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "REGISTRATION FAILED — ABORTING\n";
    exit(1);
}

echo "Synthetic user registered: ID {$synthUserId}\n";

$brandResp = httpPostJson("{$BASE_URL}/api/v1/brands", [
    'name'        => '[PROD-TEST] Media Storage Verification',
    'description' => 'Temporary synthetic brand for media storage verification. Must not publish.',
    'industry'    => 'Technology',
    'tone'        => 'Professional',
    'status'      => 'draft',
], $token);

$synthBrandId = (int)($brandResp['body']['data']['id'] ?? 0);

$results['synthetic_identity']['brand_creation'] = [
    'http_status' => $brandResp['status'],
    'expected'    => 201,
    'passed'      => ($brandResp['status'] === 201 && $synthBrandId > 0),
    'brand_id'    => $synthBrandId,
];

if ($brandResp['status'] !== 201 || !$synthBrandId) {
    $results['final_decision'] = 'C — FAIL';
    $results['decision_rationale'] = 'Synthetic brand creation failed. Aborting.';
    file_put_contents(__DIR__ . '/stage_5_5_4_13_results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "BRAND CREATION FAILED — ABORTING\n";
    exit(1);
}

echo "Synthetic brand created: ID {$synthBrandId}\n";

// ---------------------------------------------------------------------------
// SECTION 3 — Local Test File
// ---------------------------------------------------------------------------

echo "\n=== LOCAL TEST FILE ===\n";

// Minimal valid 1x1 transparent PNG
$pngRaw = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
$localPngPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "prod_media_audit_{$uuid}.png";
file_put_contents($localPngPath, $pngRaw);

$localFileSize = filesize($localPngPath);
$localMimeType = mime_content_type($localPngPath);

$results['local_test_file'] = [
    'filename'     => basename($localPngPath),
    'size_bytes'   => $localFileSize,
    'mime_type'    => $localMimeType,
    'valid_png'    => ($localFileSize === 68 && $localMimeType === 'image/png'),
];

echo "Local PNG generated: " . basename($localPngPath) . " ({$localFileSize} bytes, {$localMimeType})\n";

// ---------------------------------------------------------------------------
// SECTION 4 — Test Sequence
// ---------------------------------------------------------------------------

echo "\n=== T-08.1: Upload Exactly One Synthetic Image ===\n";

$uploadUrl = "{$BASE_URL}/api/v1/brands/{$synthBrandId}/media/upload";
$uploadResp = httpPostMultipart(
    $uploadUrl,
    [
        'brand_id' => $synthBrandId,
        'title'    => '[PROD-TEST] Media Storage Probe',
        'platform' => 'linkedin',
    ],
    ['file' => $localPngPath],
    $token
);

$synthMediaId = (int)($uploadResp['body']['data']['id'] ?? $uploadResp['body']['asset']['id'] ?? 0);
$mediaData    = $uploadResp['body']['data'] ?? $uploadResp['body']['asset'] ?? [];

$results['test_sequence']['T-08.1'] = [
    'description'       => 'Upload single synthetic PNG image',
    'http_status'       => $uploadResp['status'],
    'expected'          => 201,
    'passed'            => ($uploadResp['status'] === 201 && $synthMediaId > 0),
    'media_id'          => $synthMediaId,
    'provider'          => $mediaData['provider'] ?? null,
    'generation_status' => $mediaData['generation_status'] ?? null,
    'storage_disk'      => $mediaData['storage_disk'] ?? null,
    'storage_path'      => $mediaData['storage_path'] ?? null,
    'public_url'        => $mediaData['public_url'] ?? null,
];

@unlink($localPngPath); // Clean up local temporary file immediately

if ($uploadResp['status'] !== 201 || !$synthMediaId) {
    $results['final_decision'] = 'C — FAIL';
    $results['decision_rationale'] = 'T-08.1 Media upload failed. Halting before further checks.';
    file_put_contents(__DIR__ . '/stage_5_5_4_13_results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "UPLOAD FAILED — ABORTING\n";
    exit(1);
}

echo "T-08.1 HTTP {$uploadResp['status']} (expected 201), Media ID: {$synthMediaId}\n";
$storagePath = $mediaData['storage_path'];
echo "Storage path: {$storagePath}\n";

// ---------------------------------------------------------------------------

echo "\n=== T-08.2: Verify Database Persistence ===\n";

$dbMedia = $conn->selectOne(
    "SELECT id, user_id, brand_id, file_name, mime_type, file_size, storage_disk, storage_path, provider, generation_status FROM media_assets WHERE id = ?",
    [$synthMediaId]
);

$genJobsCount    = (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM media_generation_jobs WHERE brand_id = ?", [$synthBrandId])->cnt;
$storyboardsCount = (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM media_storyboards WHERE brand_id = ?", [$synthBrandId])->cnt;

$t082passed = (
    $dbMedia !== null &&
    (int)$dbMedia->user_id === $synthUserId &&
    (int)$dbMedia->brand_id === $synthBrandId &&
    $dbMedia->mime_type === 'image/png' &&
    (int)$dbMedia->file_size === 68 &&
    $dbMedia->storage_disk === 'supabase' &&
    str_starts_with($dbMedia->storage_path, "media/{$synthBrandId}/") &&
    $dbMedia->provider === 'uploaded' &&
    $dbMedia->generation_status === 'completed' &&
    $genJobsCount === 0 &&
    $storyboardsCount === 0
);

$results['test_sequence']['T-08.2'] = [
    'description'             => 'Verify database persistence of uploaded media',
    'passed'                  => $t082passed,
    'db_media_id'             => $dbMedia?->id,
    'db_user_id'              => $dbMedia?->user_id,
    'db_brand_id'             => $dbMedia?->brand_id,
    'db_mime_type'            => $dbMedia?->mime_type,
    'db_file_size'            => $dbMedia?->file_size,
    'db_storage_disk'         => $dbMedia?->storage_disk,
    'db_storage_path'         => $dbMedia?->storage_path,
    'db_provider'             => $dbMedia?->provider,
    'db_generation_status'    => $dbMedia?->generation_status,
    'zero_generation_jobs'    => ($genJobsCount === 0),
    'zero_storyboards'        => ($storyboardsCount === 0),
];

echo "T-08.2 DB Persistence: " . ($t082passed ? 'PASSED' : 'FAILED') . "\n";

// ---------------------------------------------------------------------------

echo "\n=== T-08.3: Verify Supabase Object Existence ===\n";

$objectExists = $storageDisk->exists($storagePath);
$objectSize   = $objectExists ? $storageDisk->size($storagePath) : -1;
$isUnderBrandNamespace = str_starts_with($storagePath, "media/{$synthBrandId}/");

$t083passed = ($objectExists && $objectSize === 68 && $isUnderBrandNamespace);

$results['test_sequence']['T-08.3'] = [
    'description'               => 'Verify Supabase storage object existence and size',
    'passed'                    => $t083passed,
    'storage_path'              => $storagePath,
    'object_exists'             => $objectExists,
    'object_size_bytes'         => $objectSize,
    'expected_size'             => 68,
    'is_under_brand_namespace'  => $isUnderBrandNamespace,
];

echo "T-08.3 Object Exists: " . ($objectExists ? 'YES' : 'NO') . " (Size: {$objectSize} bytes)\n";

// ---------------------------------------------------------------------------

echo "\n=== T-08.4: Verify Authenticated Retrieval ===\n";

$getMediaResp = httpGet("{$BASE_URL}/api/v1/media/{$synthMediaId}", $token);

$fetchedAsset = $getMediaResp['body']['data'] ?? $getMediaResp['body']['asset'] ?? [];
$t084passed   = (
    $getMediaResp['status'] === 200 &&
    (int)($fetchedAsset['id'] ?? 0) === $synthMediaId &&
    (int)($fetchedAsset['brand_id'] ?? 0) === $synthBrandId &&
    ($fetchedAsset['mime_type'] ?? '') === 'image/png'
);

$results['test_sequence']['T-08.4'] = [
    'description'        => 'Verify authenticated media retrieval via API',
    'http_status'        => $getMediaResp['status'],
    'expected'           => 200,
    'passed'             => $t084passed,
    'returned_id'        => $fetchedAsset['id'] ?? null,
    'returned_brand_id'  => $fetchedAsset['brand_id'] ?? null,
    'returned_mime_type' => $fetchedAsset['mime_type'] ?? null,
];

echo "T-08.4 HTTP {$getMediaResp['status']} (expected 200)\n";

// ---------------------------------------------------------------------------

echo "\n=== T-08.5: Cross-Tenant Access Verification ===\n";

// Attempt to access Brand 30001 media listing with synthetic token
$crossTenantResp = httpGet("{$BASE_URL}/api/v1/brands/30001/media", $token);

$t085passed = ($crossTenantResp['status'] === 403);

$results['test_sequence']['T-08.5'] = [
    'description' => 'Cross-tenant media listing probe (Brand 30001)',
    'http_status' => $crossTenantResp['status'],
    'expected'    => 403,
    'passed'      => $t085passed,
];

echo "T-08.5 HTTP {$crossTenantResp['status']} (expected 403)\n";

// ---------------------------------------------------------------------------

echo "\n=== T-08.6: Delete Synthetic Media Through API ===\n";

$delMediaResp = httpDelete("{$BASE_URL}/api/v1/media/{$synthMediaId}", $token);

$t086passed = ($delMediaResp['status'] === 200);

$results['test_sequence']['T-08.6'] = [
    'description' => 'Delete synthetic media via API',
    'http_status' => $delMediaResp['status'],
    'expected'    => 200,
    'passed'      => $t086passed,
    'api_message' => $delMediaResp['body']['message'] ?? '',
];

echo "T-08.6 HTTP {$delMediaResp['status']} (expected 200)\n";

// ---------------------------------------------------------------------------

echo "\n=== T-08.7: Verify Storage Deletion ===\n";

// Check if S3 object is gone
sleep(1);
$objectStillExists = $storageDisk->exists($storagePath);

// Check DB state (soft-deleted or hard-deleted)
$dbMediaAfterDel = $conn->selectOne("SELECT id, deleted_at FROM media_assets WHERE id = ?", [$synthMediaId]);
$dbDeleted = ($dbMediaAfterDel === null || $dbMediaAfterDel->deleted_at !== null);

$t087passed = (!$objectStillExists && $dbDeleted);

$results['test_sequence']['T-08.7'] = [
    'description'          => 'Verify Supabase storage object deleted and DB row soft-deleted',
    'passed'               => $t087passed,
    'object_still_exists'  => $objectStillExists,
    'db_record_deleted'    => $dbDeleted,
    'db_deleted_at'        => $dbMediaAfterDel?->deleted_at,
];

echo "T-08.7 Object still in Supabase: " . ($objectStillExists ? 'YES (STALL)' : 'NO (DELETED)') . "\n";
echo "T-08.7 DB Record deleted: " . ($dbDeleted ? 'YES' : 'NO') . "\n";

if ($objectStillExists) {
    echo "CRITICAL: Storage object still exists after API deletion. Halting teardown.\n";
    $results['final_decision'] = 'C — FAIL';
    $results['decision_rationale'] = 'Supabase Storage object was not deleted by API deleteAsset(). Manual inspection required.';
    file_put_contents(__DIR__ . '/stage_5_5_4_13_results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    exit(1);
}

// ---------------------------------------------------------------------------
// SECTION 5 — Brand and User Cleanup
// ---------------------------------------------------------------------------

echo "\n=== BRAND AND USER CLEANUP ===\n";

// 1. Delete synthetic brand via API
$delBrandResp = httpDelete("{$BASE_URL}/api/v1/brands/{$synthBrandId}", $token);

// 2. Cascade check
sleep(1);
$brandInDb = (bool) $conn->selectOne("SELECT id FROM brands WHERE id = ?", [$synthBrandId]);
$mediaInDb = (int) $conn->selectOne("SELECT COUNT(*) AS cnt FROM media_assets WHERE brand_id = ?", [$synthBrandId])->cnt;

// Hard-delete soft-deleted media row if brand cascade left soft-deleted rows
$hardDeletedMedia = $conn->delete("DELETE FROM media_assets WHERE brand_id = ?", [$synthBrandId]);

// 3. Delete notifications for synthetic user
$notifDeleted = $conn->delete("DELETE FROM notifications WHERE notifiable_id = ?", [$synthUserId]);

// 4. Revoke/delete synthetic user tokens
$tokensDeleted = $conn->delete("DELETE FROM personal_access_tokens WHERE tokenable_id = ?", [$synthUserId]);

// 5. Delete synthetic user (strict safety guard against deleting 30001 or 344308)
$userDeleted = $conn->delete("DELETE FROM users WHERE id = ? AND id NOT IN (30001, 344308)", [$synthUserId]);

$results['teardown'] = [
    'brand_api_deletion'     => [
        'http_status'        => $delBrandResp['status'],
        'passed'             => ($delBrandResp['status'] === 200),
        'brand_in_db'        => $brandInDb,
        'media_in_db'        => $mediaInDb,
    ],
    'cleanup'                => [
        'media_hard_deleted' => $hardDeletedMedia,
        'notifications_del'  => $notifDeleted,
        'tokens_del'         => $tokensDeleted,
        'user_deleted'       => $userDeleted,
        'synth_user_id'      => $synthUserId,
        'user_30001_safe'    => (bool) $conn->selectOne("SELECT id FROM users WHERE id = 30001"),
        'user_344308_safe'   => (bool) $conn->selectOne("SELECT id FROM users WHERE id = 344308"),
    ],
];

echo "Brand deleted: HTTP {$delBrandResp['status']}\n";
echo "Tokens deleted: {$tokensDeleted}, User deleted: {$userDeleted}\n";

// ---------------------------------------------------------------------------
// SECTION 6 — Post-Execution Verification
// ---------------------------------------------------------------------------

echo "\n=== POST-EXECUTION VERIFICATION ===\n";

$postCounts = dbCounts($conn);
$u30001Post = $conn->selectOne('SELECT id, name, email FROM users WHERE id = 30001');
$b30001Post = $conn->selectOne('SELECT id, user_id, name, status FROM brands WHERE id = 30001');
$u344308Post = $conn->selectOne('SELECT id, name, email FROM users WHERE id = 344308');

$finalStorageFiles = $storageDisk->allFiles();
$finalStorageCount = count($finalStorageFiles);

$healthUpPost    = httpGet("{$BASE_URL}/up");
$healthApiPost   = httpGet("{$BASE_URL}/api/v1/health");
$healthReadyPost = httpGet("{$BASE_URL}/api/v1/health/ready");

$baselineRestored = (
    $postCounts['users'] === 2 &&
    $postCounts['brands'] === 1 &&
    $postCounts['media_assets'] === 0 &&
    $postCounts['media_generation_jobs'] === 0 &&
    $postCounts['media_storyboards'] === 0 &&
    $postCounts['scheduled_post_media'] === 0 &&
    $postCounts['brand_content_drafts'] === 0 &&
    $postCounts['scheduled_posts'] === 0 &&
    $postCounts['publishing_attempts'] === 0 &&
    $postCounts['jobs'] === 0 &&
    $postCounts['personal_access_tokens'] === 0 &&
    $finalStorageCount === 0 &&
    (bool) $u30001Post &&
    (bool) $b30001Post &&
    (bool) $u344308Post
);

$allHealthOk = (
    $healthUpPost['status'] === 200 &&
    $healthApiPost['status'] === 200 &&
    $healthReadyPost['status'] === 200
);

$results['post_verification'] = [
    'final_counts'            => $postCounts,
    'storage_final_count'     => $finalStorageCount,
    'baseline_restored'       => $baselineRestored,
    'user_30001_intact'       => (bool) $u30001Post,
    'brand_30001_intact'      => (bool) $b30001Post,
    'brand_30001_status'      => $b30001Post?->status,
    'user_344308_intact'      => (bool) $u344308Post,
    'health_endpoints'        => [
        'up'    => $healthUpPost['status'],
        'health'=> $healthApiPost['status'],
        'ready' => $healthReadyPost['status'],
    ],
    'all_health_ok'           => $allHealthOk,
    'all_verified'            => ($baselineRestored && $allHealthOk),
];

// ---------------------------------------------------------------------------
// SECTION 7 — Final Decision
// ---------------------------------------------------------------------------

$allTestsPassed = (
    $results['test_sequence']['T-08.1']['passed'] &&
    $results['test_sequence']['T-08.2']['passed'] &&
    $results['test_sequence']['T-08.3']['passed'] &&
    $results['test_sequence']['T-08.4']['passed'] &&
    $results['test_sequence']['T-08.5']['passed'] &&
    $results['test_sequence']['T-08.6']['passed'] &&
    $results['test_sequence']['T-08.7']['passed']
);

if ($allTestsPassed && $baselineRestored && $allHealthOk) {
    $results['final_decision'] = 'A — PASS';
    $results['decision_rationale'] = 'All 7 T-08 test steps passed with expected status codes. ' .
        "Synthetic 68-byte PNG uploaded to Supabase Storage (Media ID {$synthMediaId}). " .
        'Database persistence verified (provider=uploaded, generation_status=completed). ' .
        'Supabase Storage object existence and exact 68-byte size verified. ' .
        'Authenticated retrieval succeeded (HTTP 200). ' .
        'Cross-tenant probe against Brand 30001 blocked (HTTP 403). ' .
        'API media deletion (DELETE /api/v1/media/{id}) purged the Supabase Storage object. ' .
        'Brand and user teardown succeeded. ' .
        'Database and storage returned to exact baseline (users=2, brands=1, media_assets=0, storage_files=0). ' .
        'User 30001, Brand 30001, and User 344308 intact. All health endpoints green.';
} elseif ($baselineRestored) {
    $results['final_decision'] = 'B — PARTIAL';
    $results['decision_rationale'] = 'Some test checks failed but cleanup succeeded and baseline was restored.';
} else {
    $results['final_decision'] = 'C — FAIL';
    $results['decision_rationale'] = 'Test or cleanup failure occurred. See details.';
}

file_put_contents(
    __DIR__ . '/stage_5_5_4_13_results.json',
    json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "\n=== FINAL DECISION: {$results['final_decision']} ===\n";
echo "Baseline restored: " . ($baselineRestored ? 'YES' : 'NO') . "\n";
echo "All tests passed:  " . ($allTestsPassed ? 'YES' : 'NO') . "\n";
echo "Storage files:     {$finalStorageCount}\n";
echo "Results written to scripts/stage_5_5_4_13_results.json\n";
