<?php

/**
 * Stage 5.5.3.1 — Authorized Production Database User Provisioning & Verification
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

echo "=== STAGE 5.5.3.1 PRE-EXECUTION IDENTITY VERIFICATION ===\n";

$adminPdo = DB::connection('tidb_migration_test')->getPdo();

$currentUser = $adminPdo->query("SELECT CURRENT_USER()")->fetchColumn();
echo "1. Connected as Admin: " . $currentUser . "\n";
if (!str_starts_with($currentUser, 'AZDARrqgy4MBFGE.root')) {
    die("FATAL: Admin account verification failed (expected root). Halting.\n");
}

$sslCipher = $adminPdo->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_ASSOC);
echo "2. TLS Cipher Active: " . ($sslCipher['Value'] ?? 'NONE') . "\n";
if (empty($sslCipher['Value'])) {
    die("FATAL: TLS connection is not active. Halting.\n");
}

$databases = $adminPdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
echo "3. Databases on cluster: " . implode(', ', $databases) . "\n";
if (!in_array('socialpilot', $databases)) {
    die("FATAL: Production database 'socialpilot' does not exist. Halting.\n");
}

$targetUsername = 'AZDARrqgy4MBFGE.prod_app';
$existingUsers = $adminPdo->query("SELECT User FROM mysql.user WHERE User = '{$targetUsername}'")->fetchAll(PDO::FETCH_COLUMN);

// Retrieve or generate password
$envPath = __DIR__ . '/../.env.staging.local';
$generatedPassword = null;
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if (preg_match('/TIDB_PROD_APP_PASSWORD=([a-f0-9]+)/', $envContent, $m)) {
        $generatedPassword = $m[1];
    }
}

if (empty($existingUsers)) {
    echo "4. Verified '{$targetUsername}' does not already exist. Proceeding to create.\n";
    $generatedPassword = bin2hex(random_bytes(24));

    // 1. Create User
    $adminPdo->exec("CREATE USER '{$targetUsername}'@'%' IDENTIFIED BY '{$generatedPassword}'");
    echo "✓ Account created successfully.\n";

    // 2. Grant strictly DML on socialpilot.*
    $adminPdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON `socialpilot`.* TO '{$targetUsername}'@'%'");
    echo "✓ Granted SELECT, INSERT, UPDATE, DELETE on socialpilot.*.\n";

    // 3. Require SSL
    $adminPdo->exec("ALTER USER '{$targetUsername}'@'%' REQUIRE SSL");
    echo "✓ Enforced REQUIRE SSL.\n";

    // 4. Flush privileges
    $adminPdo->exec("FLUSH PRIVILEGES");
    echo "✓ Privileges flushed.\n\n";

    // Save to .env.staging.local (gitignored)
    if (strpos($envContent, 'TIDB_PROD_APP_USERNAME=') !== false) {
        $envContent = preg_replace('/TIDB_PROD_APP_USERNAME=.*/', "TIDB_PROD_APP_USERNAME={$targetUsername}", $envContent);
    } else {
        $envContent .= "\nTIDB_PROD_APP_USERNAME={$targetUsername}\n";
    }
    if (strpos($envContent, 'TIDB_PROD_APP_PASSWORD=') !== false) {
        $envContent = preg_replace('/TIDB_PROD_APP_PASSWORD=.*/', "TIDB_PROD_APP_PASSWORD={$generatedPassword}", $envContent);
    } else {
        $envContent .= "TIDB_PROD_APP_PASSWORD={$generatedPassword}\n";
    }
    file_put_contents($envPath, $envContent);
} else {
    echo "4. Account '{$targetUsername}' already provisioned in previous step. Proceeding with verification.\n";
    if (empty($generatedPassword)) {
        // If password wasn't saved, reset it
        $generatedPassword = bin2hex(random_bytes(24));
        $adminPdo->exec("ALTER USER '{$targetUsername}'@'%' IDENTIFIED BY '{$generatedPassword}'");
        $envContent = file_get_contents($envPath);
        if (strpos($envContent, 'TIDB_PROD_APP_USERNAME=') !== false) {
            $envContent = preg_replace('/TIDB_PROD_APP_USERNAME=.*/', "TIDB_PROD_APP_USERNAME={$targetUsername}", $envContent);
        } else {
            $envContent .= "\nTIDB_PROD_APP_USERNAME={$targetUsername}\n";
        }
        if (strpos($envContent, 'TIDB_PROD_APP_PASSWORD=') !== false) {
            $envContent = preg_replace('/TIDB_PROD_APP_PASSWORD=.*/', "TIDB_PROD_APP_PASSWORD={$generatedPassword}", $envContent);
        } else {
            $envContent .= "TIDB_PROD_APP_PASSWORD={$generatedPassword}\n";
        }
        file_put_contents($envPath, $envContent);
    }
}

echo "\n=== VERIFYING GRANTS FROM ADMIN PERSPECTIVE ===\n";
$grants = $adminPdo->query("SHOW GRANTS FOR '{$targetUsername}'@'%'")->fetchAll(PDO::FETCH_COLUMN);
echo "Assigned Grants:\n";
foreach ($grants as $g) {
    echo "  -> " . $g . "\n";
}

$hasGrantOption = false;
$hasGlobalPrivs = false;
foreach ($grants as $g) {
    if (strpos($g, 'WITH GRANT OPTION') !== false) {
        $hasGrantOption = true;
    }
    if (strpos($g, 'ON *.*') !== false && !str_starts_with($g, 'GRANT USAGE ON *.*')) {
        $hasGlobalPrivs = true;
    }
}

if ($hasGrantOption || $hasGlobalPrivs) {
    die("FATAL: Over-privileged grant detected on {$targetUsername}! Halting.\n");
}
echo "✓ Admin grant verification PASSED (Zero global privileges, Zero GRANT OPTION).\n\n";

echo "=== VERIFYING LIVE CONNECTION AS '{$targetUsername}' ===\n";

// Configure dynamic Laravel connection with matching SSL options
Config::set('database.connections.tidb_prod_app', [
    'driver' => 'mysql',
    'host' => 'gateway01.eu-central-1.prod.aws.tidbcloud.com',
    'port' => 4000,
    'database' => 'socialpilot',
    'username' => $targetUsername,
    'password' => $generatedPassword,
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'options' => Config::get('database.connections.tidb_staging_app.options', []),
]);

$appPdo = DB::connection('tidb_prod_app')->getPdo();
$appUser = $appPdo->query("SELECT CURRENT_USER()")->fetchColumn();
$appDb = $appPdo->query("SELECT DATABASE()")->fetchColumn();
$appSsl = $appPdo->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_ASSOC);

echo "1. Authenticated Current User: " . $appUser . "\n";
echo "2. Connected Database: " . $appDb . "\n";
echo "3. Active TLS Cipher: " . ($appSsl['Value'] ?? 'NONE') . "\n";

if ($appDb !== 'socialpilot') {
    die("FATAL: Connected database is '$appDb', expected 'socialpilot'.\n");
}
if (empty($appSsl['Value'])) {
    die("FATAL: TLS is not active on application account.\n");
}

echo "\n=== VERIFYING LEAST-PRIVILEGE DML ACCESS ===\n";
// 1. Test SELECT on socialpilot
$migrationCount = $appPdo->query("SELECT count(*) as cnt FROM `socialpilot`.`migrations`")->fetch(PDO::FETCH_ASSOC)['cnt'];
echo "1. SELECT verification: Success ({$migrationCount} migrations found in socialpilot.migrations)\n";

// 2. Query schema privileges via information_schema
$privs = $appPdo->query("SELECT PRIVILEGE_TYPE FROM information_schema.schema_privileges WHERE GRANTEE LIKE '%prod_app%'")->fetchAll(PDO::FETCH_COLUMN);
echo "2. Information Schema Privileges for prod_app: " . implode(', ', $privs) . "\n";

// 3. Test Isolation: Try accessing socialpilot_staging
$stagingIsolated = false;
try {
    $appPdo->query("SELECT count(*) FROM `socialpilot_staging`.`users`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), '42000') !== false || strpos($e->getMessage(), 'Access denied') !== false) {
        $stagingIsolated = true;
    }
}
echo "3. Staging Isolation (socialpilot_staging access denied): " . ($stagingIsolated ? "VERIFIED (Access Denied as expected)" : "FAILED (Could access staging)") . "\n";

// 4. Test Isolation: Try accessing socialpilot_migration_test
$testIsolated = false;
try {
    $appPdo->query("SELECT count(*) FROM `socialpilot_migration_test`.`users`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), '42000') !== false || strpos($e->getMessage(), 'Access denied') !== false) {
        $testIsolated = true;
    }
}
echo "4. Migration Test Isolation (socialpilot_migration_test access denied): " . ($testIsolated ? "VERIFIED (Access Denied as expected)" : "FAILED") . "\n";

// 5. Test DDL Denial: Try executing CREATE TABLE
$ddlBlocked = false;
try {
    $appPdo->exec("CREATE TABLE `socialpilot`.`__test_probe_fail__` (id INT)");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), '42000') !== false || strpos($e->getMessage(), 'Access denied') !== false || strpos($e->getMessage(), 'command denied') !== false) {
        $ddlBlocked = true;
    }
}
echo "5. DDL Denial (CREATE TABLE denied): " . ($ddlBlocked ? "VERIFIED (Command Denied as expected)" : "FAILED (DDL was permitted!)") . "\n";

if (!$stagingIsolated || !$testIsolated || !$ddlBlocked) {
    die("FATAL: Privilege isolation verification failed! Halting.\n");
}

echo "✓ Credential securely preserved in local gitignored .env.staging.local (TIDB_PROD_APP_USERNAME, TIDB_PROD_APP_PASSWORD)\n";

$reportData = [
    'account' => $targetUsername,
    'database' => 'socialpilot',
    'host' => '%',
    'tls_cipher' => $appSsl['Value'] ?? null,
    'grants' => $grants,
    'privileges_verified' => $privs,
    'staging_isolated' => $stagingIsolated,
    'migration_test_isolated' => $testIsolated,
    'ddl_denied' => $ddlBlocked,
    'status' => 'SUCCESS',
];
file_put_contents(__DIR__ . '/stage_5_5_3_1_results.json', json_encode($reportData, JSON_PRETTY_PRINT));

echo "\n=== PROVISIONING AND VERIFICATION SUCCEEDED ===\n";
