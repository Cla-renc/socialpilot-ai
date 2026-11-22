<?php

/**
 * Stage 5.5.2.3 — Live Staging Remote Storage Test Suite
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$baseUrl = 'https://socialpilot-staging.onrender.com';
$results = [];

function callEndpoint(string $method, string $url, $payload = null, ?string $token = null, bool $isMultipart = false): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $headers = ['Accept: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }

    if ($isMultipart) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    } else {
        $headers[] = 'Content-Type: application/json';
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $json = json_decode($raw, true);

    return [
        'code' => $code,
        'body' => $json ?: $raw,
        'error' => $err,
    ];
}

echo "=== 1. CHECKING LIVE STAGING HEALTH ENDPOINTS ===\n";
foreach (['/up', '/api/v1/health', '/api/v1/health/ready'] as $uri) {
    $res = callEndpoint('GET', $baseUrl . $uri);
    echo "  $uri -> HTTP {$res['code']}\n";
    if ($res['code'] !== 200) {
        die("FATAL: Staging health check failed on $uri (HTTP {$res['code']}). Staging may still be building. Halting.\n");
    }
}
echo "✓ Staging web service is healthy and responding.\n\n";

echo "=== 2. DATABASE PREFLIGHT SAFETY CHECK ===\n";
$pdo = Illuminate\Support\Facades\DB::connection('tidb_staging_app')->getPdo();
$db = $pdo->query('SELECT DATABASE()')->fetchColumn();
$user = $pdo->query('SELECT CURRENT_USER()')->fetchColumn();
$ssl = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_ASSOC);

if ($db !== 'socialpilot_staging') {
    die("FATAL: Active DB is '$db', expected 'socialpilot_staging'. Halting.\n");
}
if (!str_starts_with($user, 'AZDARrqgy4MBFGE.staging_app')) {
    die("FATAL: Active user is '$user', expected 'AZDARrqgy4MBFGE.staging_app'. Halting.\n");
}
if (empty($ssl['Value'])) {
    die("FATAL: TLS connection is not active. Halting.\n");
}
echo "✓ Verified Database: $db\n";
echo "✓ Verified User: $user\n";
echo "✓ Verified TLS Cipher: {$ssl['Value']}\n\n";

echo "=== 3. AUTHENTICATING STAGING TEST USER ===\n";
$testUser = $pdo->query("SELECT id, name, email FROM users WHERE email LIKE 'staging_test_%' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$testUser) {
    die("FATAL: No staging test user found. Halting.\n");
}
echo "Found Staging Test User ID {$testUser['id']}: {$testUser['email']}\n";

$loginRes = callEndpoint('POST', "$baseUrl/api/v1/auth/login", [
    'email' => $testUser['email'],
    'password' => 'Password123!',
]);

if ($loginRes['code'] !== 200 || empty($loginRes['body']['token'])) {
    die("FATAL: Authentication failed (HTTP {$loginRes['code']}). Halting.\n");
}
$token = $loginRes['body']['token'];
echo "✓ Bearer token obtained.\n\n";

// Get or create test brand
$brandStmt = $pdo->prepare("SELECT id, name FROM brands WHERE user_id = :uid LIMIT 1");
$brandStmt->execute(['uid' => $testUser['id']]);
$brand = $brandStmt->fetch(PDO::FETCH_ASSOC);

if (!$brand) {
    echo "Creating dedicated test brand in staging DB...\n";
    $insBrand = $pdo->prepare("INSERT INTO brands (user_id, name, industry, status, created_at, updated_at) VALUES (:uid, 'Staging Media Test Brand', 'Technology', 'active', NOW(), NOW())");
    $insBrand->execute(['uid' => $testUser['id']]);
    $brandId = (int) $pdo->lastInsertId();
} else {
    $brandId = (int) $brand['id'];
}
echo "Using Staging Brand ID: $brandId\n\n";

echo "=== 4. LIVE REMOTE MEDIA UPLOAD TO STAGING ===\n";
$tempFile = sys_get_temp_dir() . '/staging_probe_' . time() . '.jpg';
file_put_contents($tempFile, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00\xFF\xDB\x00C\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\t\t\x08\n\x0C\x14\r\x0C\x0B\x0B\x0C\x19\x12\x13\x0F\x14\x1D\x1A\x1F\x1E\x1D\x1A\x1C\x1C $.' \",#\x1C\x1C(7),01444\x1F'9=82<.342\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00\xFF\xC4\x00\x1F\x00\x00\x01\x05\x01\x01\x01\x01\x01\x01\x00\x00\x00\x00\x00\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x08\t\n\x0B\xFF\xDA\x00\x08\x01\x01\x00\x00?\x00\xBF\x00\xFF\xD9");

$cFile = new CURLFile($tempFile, 'image/jpeg', 'staging_probe.jpg');
$uploadPayload = [
    'brand_id' => $brandId,
    'file' => $cFile,
    'title' => 'Staging Remote Storage Verification Image',
];

$uploadRes = callEndpoint('POST', "$baseUrl/api/v1/brands/{$brandId}/media/upload", $uploadPayload, $token, true);
@unlink($tempFile);

echo "Upload Response Code: HTTP {$uploadRes['code']}\n";
if ($uploadRes['code'] !== 201) {
    echo "Upload failed! Response:\n";
    print_r($uploadRes['body']);
    exit(1);
}

$assetData = $uploadRes['body']['data'] ?? $uploadRes['body']['asset'];
$assetId = $assetData['id'];
$storageDisk = $assetData['storage_disk'];
$storagePath = $assetData['storage_path'];
$publicUrl = $assetData['public_url'];

echo "✓ Asset ID: $assetId\n";
echo "✓ Storage Disk: $storageDisk\n";
echo "✓ Storage Path: $storagePath\n";
echo "✓ Public URL: $publicUrl\n\n";

$results['upload'] = [
    'status' => 'PASS',
    'asset_id' => $assetId,
    'storage_disk' => $storageDisk,
    'storage_path' => $storagePath,
    'public_url' => $publicUrl,
];

echo "=== 5. VERIFYING TIDB CLOUD METADATA PERSISTENCE ===\n";
$metaStmt = $pdo->prepare("SELECT id, user_id, brand_id, storage_disk, storage_path, public_url, file_size FROM media_assets WHERE id = :id");
$metaStmt->execute(['id' => $assetId]);
$dbRow = $metaStmt->fetch(PDO::FETCH_ASSOC);

if ($dbRow && $dbRow['storage_disk'] === $storageDisk && $dbRow['storage_path'] === $storagePath) {
    echo "✓ TiDB metadata correctly persisted: ID {$dbRow['id']}, Disk: {$dbRow['storage_disk']}\n\n";
    $results['metadata_persistence'] = 'PASS';
} else {
    echo "✗ TiDB metadata mismatch or not found.\n\n";
    $results['metadata_persistence'] = 'FAIL';
}

echo "=== 6. VERIFYING PUBLIC CDN URL ACCESSIBILITY ===\n";
$publicGet = callEndpoint('GET', $publicUrl);
echo "Public URL GET -> HTTP {$publicGet['code']}\n";
if ($publicGet['code'] === 200) {
    echo "✓ Public CDN URL is directly accessible (HTTP 200)!\n\n";
    $results['public_url_accessible'] = 'PASS';
} else {
    echo "✗ Public CDN URL returned HTTP {$publicGet['code']}.\n\n";
    $results['public_url_accessible'] = 'FAIL';
}

echo "=== 7. VERIFYING FLUTTER / REST API RETRIEVAL ===\n";
$getAssetRes = callEndpoint('GET', "$baseUrl/api/v1/media/{$assetId}", null, $token);
echo "GET /api/v1/media/{$assetId} -> HTTP {$getAssetRes['code']}\n";
if ($getAssetRes['code'] === 200 && ($getAssetRes['body']['data']['id'] ?? null) === $assetId) {
    echo "✓ Media asset correctly retrieved via REST API.\n\n";
    $results['api_retrieval'] = 'PASS';
} else {
    echo "✗ REST API retrieval failed.\n\n";
    $results['api_retrieval'] = 'FAIL';
}

echo "=== 8. LIVE DELETION & CLEANUP ===\n";
$delRes = callEndpoint('DELETE', "$baseUrl/api/v1/media/{$assetId}", null, $token);
echo "DELETE /api/v1/media/{$assetId} -> HTTP {$delRes['code']}\n";
if ($delRes['code'] === 200) {
    echo "✓ Media asset deleted via REST API.\n";
    $results['api_deletion'] = 'PASS';
} else {
    echo "✗ Deletion via REST API failed.\n";
    $results['api_deletion'] = 'FAIL';
}

// Verify soft delete in TiDB
$checkDel = $pdo->prepare("SELECT deleted_at FROM media_assets WHERE id = :id");
$checkDel->execute(['id' => $assetId]);
$delRow = $checkDel->fetch(PDO::FETCH_ASSOC);
echo "Soft delete in TiDB: " . ($delRow['deleted_at'] ? "YES ({$delRow['deleted_at']})" : "NO") . "\n";

// Force delete test record from TiDB so no staging clutter remains
$pdo->prepare("DELETE FROM media_assets WHERE id = :id")->execute(['id' => $assetId]);
echo "✓ Staging test database record purged.\n\n";

echo "=== LIVE STAGING STORAGE VERIFICATION COMPLETE ===\n";
file_put_contents(__DIR__ . '/stage_5_5_2_3_staging_results.json', json_encode($results, JSON_PRETTY_PRINT));
exit(0);
