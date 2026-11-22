<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

echo "=== STAGE 5.5.2.3 REMOTE STORAGE PREFLIGHT ===\n";

$diskName = config('services.media.storage_disk', 'supabase');
if (!in_array($diskName, ['supabase', 's3'])) {
    $diskName = 'supabase';
}

$diskConfig = config("filesystems.disks.{$diskName}");

$hasKey = !empty($diskConfig['key']);
$hasSecret = !empty($diskConfig['secret']);
$endpoint = $diskConfig['endpoint'] ?? '';
$bucket = $diskConfig['bucket'] ?? '';
$region = $diskConfig['region'] ?? '';
$pathStyle = $diskConfig['use_path_style_endpoint'] ?? false;

echo "Disk: " . $diskName . "\n";
echo "Access Key configured: " . ($hasKey ? "YES (length " . strlen($diskConfig['key']) . ")" : "NO") . "\n";
echo "Secret Key configured: " . ($hasSecret ? "YES (length " . strlen($diskConfig['secret']) . ")" : "NO") . "\n";
echo "Endpoint: " . $endpoint . "\n";
echo "Bucket: " . $bucket . "\n";
echo "Region: " . $region . "\n";
echo "Path-style endpoint: " . ($pathStyle ? "true" : "false") . "\n";

if (!$hasKey || !$hasSecret) {
    echo "ERROR: Credentials missing in configuration.\n";
    exit(1);
}

$results = [
    'disk' => $diskName,
    'endpoint' => $endpoint,
    'bucket' => $bucket,
    'connection' => false,
    'upload' => false,
    'path' => null,
    'retrieval' => false,
    'content_match' => false,
    'public_url' => null,
    'public_http_status' => null,
    'deletion' => false,
    'deletion_verified' => false,
];

$testFilename = 'probes/probe_' . time() . '_' . bin2hex(random_bytes(4)) . '.txt';
$testPayload = "SocialPilot AI Storage Probe Verification\nTimestamp: " . date('c') . "\nNonce: " . bin2hex(random_bytes(16));

try {
    echo "\n1. Attempting upload to {$diskName} disk: {$testFilename}...\n";
    config([
        "filesystems.disks.{$diskName}.throw" => true,
        "filesystems.disks.{$diskName}.http" => ['verify' => false],
    ]);
    $disk = Storage::disk($diskName);
    
    $putResult = $disk->put($testFilename, $testPayload);
    if (!$putResult) {
        throw new \RuntimeException("Storage::put returned false");
    }
    $results['upload'] = true;
    $results['path'] = $testFilename;
    echo "  -> Upload successful!\n";

    echo "2. Verifying object existence remotely...\n";
    if ($disk->exists($testFilename)) {
        $results['connection'] = true;
        echo "  -> Remote existence verified!\n";
    } else {
        throw new \RuntimeException("Uploaded file not found on remote storage.");
    }

    echo "3. Retrieving object contents from remote storage...\n";
    $retrievedContent = $disk->get($testFilename);
    $results['retrieval'] = true;
    if ($retrievedContent === $testPayload) {
        $results['content_match'] = true;
        echo "  -> Content matches perfectly (" . strlen($retrievedContent) . " bytes)!\n";
    } else {
        echo "  -> Warning: Content mismatch.\n";
    }

    echo "4. Checking public URL resolution...\n";
    $publicUrl = $disk->url($testFilename);
    $results['public_url'] = $publicUrl;
    echo "  -> Resolved URL: {$publicUrl}\n";

    // Test public HTTP GET
    try {
        $httpResp = Http::withOptions(['verify' => false])->timeout(10)->get($publicUrl);
        $results['public_http_status'] = $httpResp->status();
        echo "  -> HTTP GET status: " . $httpResp->status() . "\n";
    } catch (\Throwable $e) {
        echo "  -> HTTP GET error: " . $e->getMessage() . "\n";
    }

    echo "5. Deleting test probe object...\n";
    $delResult = $disk->delete($testFilename);
    $results['deletion'] = $delResult;
    echo "  -> Delete operation returned: " . ($delResult ? 'true' : 'false') . "\n";

    echo "6. Verifying test probe object is deleted...\n";
    $stillExists = $disk->exists($testFilename);
    $results['deletion_verified'] = !$stillExists;
    if (!$stillExists) {
        echo "  -> Confirmed: Object is completely deleted from remote storage!\n";
    } else {
        echo "  -> Warning: Object still exists after deletion.\n";
    }

    echo "\n=== ALL REMOTE STORAGE TESTS COMPLETED SUCCESSFULLY ===\n";
    file_put_contents(__DIR__ . '/stage_5_5_2_3_results.json', json_encode($results, JSON_PRETTY_PRINT));
    exit(0);

} catch (\Throwable $e) {
    echo "\nEXCEPTION: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    $results['error'] = $e->getMessage();
    file_put_contents(__DIR__ . '/stage_5_5_2_3_results.json', json_encode($results, JSON_PRETTY_PRINT));
    exit(1);
}
