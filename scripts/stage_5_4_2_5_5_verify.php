<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$pdo = DB::connection('tidb_staging_migrator')->getPdo();

echo "=== STAGE 5.4.2.5.5 STEP 1: LIVE VERIFICATION ===" . PHP_EOL;

// 1. Database & User Context
$ctx = $pdo->query("SELECT DATABASE() AS db, USER() AS usr")->fetch(PDO::FETCH_ASSOC);
echo "Active DB: {$ctx['db']}" . PHP_EOL;
echo "Active User: {$ctx['usr']}" . PHP_EOL;

// 2. TLS Check
$ssl = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_ASSOC);
echo "TLS Cipher: " . ($ssl['Value'] ?? 'None') . PHP_EOL;

// 3. Table Count
$tables = $pdo->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
sort($tables);
echo "Base Table Count: " . count($tables) . PHP_EOL;

// 4. Migration Records Count
$migs = $pdo->query("SELECT id, migration, batch FROM migrations ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
echo "Completed Migration Records: " . count($migs) . PHP_EOL;

// 5. Cross-database isolation
echo "Testing Isolation from socialpilot (production): ";
try {
    $pdo->query("SELECT COUNT(*) FROM socialpilot.users");
    echo "FAILED (accessible!)" . PHP_EOL;
} catch (\Throwable $e) {
    echo "PASSED (denied: " . $e->getCode() . ")" . PHP_EOL;
}

echo "Testing Isolation from socialpilot_migration_test: ";
try {
    $pdo->query("SELECT COUNT(*) FROM socialpilot_migration_test.users");
    echo "FAILED (accessible!)" . PHP_EOL;
} catch (\Throwable $e) {
    echo "PASSED (denied: " . $e->getCode() . ")" . PHP_EOL;
}
