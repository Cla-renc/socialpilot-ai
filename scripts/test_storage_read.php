<?php
$caPath = 'C:\\Program Files\\Git\\mingw64\\etc\\ssl\\certs\\ca-bundle.crt';
if (file_exists($caPath)) {
    ini_set('curl.cainfo', $caPath);
    ini_set('openssl.cafile', $caPath);
}

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

if (file_exists($caPath)) {
    Config::set('filesystems.disks.supabase.http.verify', $caPath);
    Config::set('filesystems.disks.supabase.client.http.verify', $caPath);
}

try {
    $disk = Storage::disk('supabase');
    echo "Disk initialized: OK\n";
    $files = $disk->allFiles();
    echo "Total files found in media bucket: " . count($files) . "\n";
    foreach (array_slice($files, 0, 10) as $f) {
        echo " - " . $f . "\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
