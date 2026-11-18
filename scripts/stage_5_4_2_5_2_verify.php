<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$pdo = DB::connection('tidb_staging_migrator')->getPdo();

echo "=== STAGE 5.4.2.5.2 LIVE VERIFICATION ===" . PHP_EOL;

// 1. Database & User Context
$dbStmt = $pdo->query("SELECT DATABASE() AS active_db, USER() AS active_user");
$dbInfo = $dbStmt->fetch(PDO::FETCH_ASSOC);
echo "Database: " . $dbInfo['active_db'] . PHP_EOL;
echo "User: " . $dbInfo['active_user'] . PHP_EOL;

// 2. TLS Cipher
$sslStmt = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'");
$sslInfo = $sslStmt->fetch(PDO::FETCH_ASSOC);
echo "SSL Cipher: " . ($sslInfo['Value'] ?? 'None') . PHP_EOL;

// 3. Staging Table Count
$tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'");
$tables = [];
while ($row = $tablesStmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}
sort($tables);
echo "Total Tables: " . count($tables) . PHP_EOL;

// 4. Migration Records Count & Details
$migStmt = $pdo->query("SELECT id, migration, batch FROM migrations ORDER BY id ASC");
$migrations = $migStmt->fetchAll(PDO::FETCH_ASSOC);
echo "Migration Records: " . count($migrations) . PHP_EOL;

// 5. Media Tables Existence & Row Counts
$mediaTables = [
    'media_assets',
    'media_generation_jobs',
    'media_storyboards',
    'media_storyboard_scenes',
    'media_visual_settings'
];

foreach ($mediaTables as $table) {
    if (in_array($table, $tables)) {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        echo "Table `{$table}`: EXISTS, Rows = {$count}" . PHP_EOL;
    } else {
        echo "Table `{$table}`: DOES NOT EXIST" . PHP_EOL;
    }
}

// 6. External Foreign Key References
$fkQuery = "
    SELECT 
        kcu.TABLE_NAME, 
        kcu.COLUMN_NAME, 
        kcu.CONSTRAINT_NAME, 
        kcu.REFERENCED_TABLE_NAME, 
        kcu.REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
    JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
      ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
      AND kcu.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
    WHERE kcu.TABLE_SCHEMA = 'socialpilot_staging'
      AND kcu.REFERENCED_TABLE_NAME IN ('media_assets', 'media_generation_jobs')
";
$fkStmt = $pdo->query($fkQuery);
$externalFks = $fkStmt->fetchAll(PDO::FETCH_ASSOC);
echo "External FK references pointing to media_assets or media_generation_jobs: " . count($externalFks) . PHP_EOL;
foreach ($externalFks as $fk) {
    echo "  {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']} ({$fk['CONSTRAINT_NAME']})" . PHP_EOL;
}

// 7. Isolation Check
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
