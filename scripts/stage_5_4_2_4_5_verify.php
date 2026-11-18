<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$pdo = DB::connection('tidb_staging_migrator')->getPdo();

echo '=== PHASE A: LIVE STATE VERIFICATION ===' . PHP_EOL;

// 1. Active database + user
$r = $pdo->query('SELECT DATABASE() AS db, USER() AS usr')->fetch(PDO::FETCH_ASSOC);
echo 'Active DB   : ' . $r['db'] . PHP_EOL;
echo 'Active User : ' . $r['usr'] . PHP_EOL;

// 2. TLS cipher
$r2 = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_ASSOC);
echo 'TLS Cipher  : ' . $r2['Value'] . PHP_EOL;

// 3. Table count
$tables = $pdo->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
sort($tables);
echo 'Table Count : ' . count($tables) . PHP_EOL;
echo 'Tables      : ' . implode(', ', $tables) . PHP_EOL;

// 4. Migration records
$migs = $pdo->query('SELECT id, migration, batch FROM migrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
echo PHP_EOL . 'Migration Records: ' . count($migs) . PHP_EOL;
foreach ($migs as $m) {
    echo '  [' . str_pad($m['id'], 2, ' ', STR_PAD_LEFT) . '] batch=' . $m['batch'] . '  ' . $m['migration'] . PHP_EOL;
}

// 5. Row counts on partial tables
$tables_check = ['media_assets', 'media_generation_jobs', 'media_storyboards', 'media_storyboard_scenes', 'media_visual_settings'];
echo PHP_EOL . '=== PHASE A: MEDIA TABLE ROW COUNTS ===' . PHP_EOL;
foreach ($tables_check as $t) {
    $exists = in_array($t, $tables);
    if ($exists) {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        echo "  {$t} : EXISTS, rows = {$cnt}" . PHP_EOL;
    } else {
        echo "  {$t} : DOES NOT EXIST" . PHP_EOL;
    }
}

echo PHP_EOL . '=== PHASE B: FOREIGN KEY DEPENDENCIES ON MEDIA TABLES ===' . PHP_EOL;

// Check if any OTHER staging table references media_assets or media_generation_jobs
$fkQuery = "
    SELECT
        kcu.TABLE_NAME AS dependent_table,
        kcu.COLUMN_NAME AS dependent_column,
        kcu.CONSTRAINT_NAME AS fk_name,
        kcu.REFERENCED_TABLE_NAME AS referenced_table,
        kcu.REFERENCED_COLUMN_NAME AS referenced_column
    FROM
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
    JOIN
        INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
        ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
        AND kcu.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
    WHERE
        kcu.TABLE_SCHEMA = 'socialpilot_staging'
        AND kcu.REFERENCED_TABLE_NAME IN ('media_assets', 'media_generation_jobs')
    ORDER BY
        kcu.TABLE_NAME, kcu.COLUMN_NAME
";

$fks = $pdo->query($fkQuery)->fetchAll(PDO::FETCH_ASSOC);
if (empty($fks)) {
    echo 'No external foreign keys found referencing media_assets or media_generation_jobs.' . PHP_EOL;
} else {
    echo 'WARNING: External foreign key dependencies found:' . PHP_EOL;
    foreach ($fks as $fk) {
        echo "  {$fk['dependent_table']}.{$fk['dependent_column']} -> {$fk['referenced_table']}.{$fk['referenced_column']} (FK: {$fk['fk_name']})" . PHP_EOL;
    }
}

echo PHP_EOL . '=== PHASE B: EXISTING FKs ON media_assets ===' . PHP_EOL;
$assetFks = $pdo->query("
    SELECT kcu.COLUMN_NAME, kcu.CONSTRAINT_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME,
           rc.DELETE_RULE
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
    JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
        ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME AND kcu.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
    WHERE kcu.TABLE_SCHEMA = 'socialpilot_staging' AND kcu.TABLE_NAME = 'media_assets'
    AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY kcu.COLUMN_NAME
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($assetFks as $fk) {
    echo "  FK {$fk['CONSTRAINT_NAME']}: {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']} ON DELETE {$fk['DELETE_RULE']}" . PHP_EOL;
}

echo PHP_EOL . '=== PHASE B: EXISTING FKs ON media_generation_jobs ===' . PHP_EOL;
$jobFks = $pdo->query("
    SELECT kcu.COLUMN_NAME, kcu.CONSTRAINT_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME,
           rc.DELETE_RULE
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
    JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
        ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME AND kcu.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
    WHERE kcu.TABLE_SCHEMA = 'socialpilot_staging' AND kcu.TABLE_NAME = 'media_generation_jobs'
    AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY kcu.COLUMN_NAME
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($jobFks as $fk) {
    echo "  FK {$fk['CONSTRAINT_NAME']}: {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']} ON DELETE {$fk['DELETE_RULE']}" . PHP_EOL;
}

echo PHP_EOL . '=== PHASE B: INDEXES ON media_assets ===' . PHP_EOL;
$assetIdxs = $pdo->query("SHOW INDEX FROM `media_assets`")->fetchAll(PDO::FETCH_ASSOC);
foreach ($assetIdxs as $idx) {
    echo "  " . $idx['Key_name'] . " (col: " . $idx['Column_name'] . ", unique: " . ($idx['Non_unique'] == 0 ? 'YES' : 'NO') . ")" . PHP_EOL;
}

echo PHP_EOL . '=== PHASE B: INDEXES ON media_generation_jobs ===' . PHP_EOL;
$jobIdxs = $pdo->query("SHOW INDEX FROM `media_generation_jobs`")->fetchAll(PDO::FETCH_ASSOC);
foreach ($jobIdxs as $idx) {
    echo "  " . $idx['Key_name'] . " (col: " . $idx['Column_name'] . ", unique: " . ($idx['Non_unique'] == 0 ? 'YES' : 'NO') . ")" . PHP_EOL;
}

echo PHP_EOL . '=== PHASE C: BACKUP TOOL AVAILABILITY ===' . PHP_EOL;
$mysqldump = shell_exec('where mysqldump 2>&1');
echo 'mysqldump: ' . trim($mysqldump) . PHP_EOL;
$mysqlsh = shell_exec('where mysqlsh 2>&1');
echo 'mysqlsh: ' . trim($mysqlsh) . PHP_EOL;
$php = shell_exec('php --version 2>&1');
echo 'PHP: ' . strtok(trim($php), PHP_EOL) . PHP_EOL;
// Check storage/app/backups dir
$backupDir = __DIR__ . '/../storage/app/backups';
echo 'Backup dir writable: ' . (is_dir($backupDir) || is_writable(dirname($backupDir)) ? 'YES' : 'NO (needs creation)') . PHP_EOL;
