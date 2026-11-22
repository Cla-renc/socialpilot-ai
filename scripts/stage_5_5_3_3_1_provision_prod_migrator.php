<?php
/**
 * STAGE 5.5.3.3.1 — Authorized Production Migrator Account Provisioning
 * AUTHORIZED by user — explicit authorization received.
 * Target: AZDARrqgy4MBFGE.prod_migrator on socialpilot.*
 *
 * Credential is generated locally and stored ONLY in .env.staging.local.
 * Never printed to stdout, logs, or reports.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$results = [];
$results['timestamp'] = date('c');
$results['mode'] = 'AUTHORIZED PROVISIONING — prod_migrator';
$results['target_account'] = 'AZDARrqgy4MBFGE.prod_migrator';
$results['target_database'] = 'socialpilot';

$adminConn = 'tidb_migration_test';
$newUser   = 'AZDARrqgy4MBFGE.prod_migrator';
$newHost   = '%';

echo "=== STAGE 5.5.3.3.1 — PRODUCTION MIGRATOR PROVISIONING ===\n\n";

// ── PHASE 1: Pre-execution identity verification ─────────────────────────────
echo "--- PHASE 1: Identity Verification ---\n";

// 1a. Connect and verify admin identity
try {
    $identRow = DB::connection($adminConn)->selectOne(
        "SELECT current_user() AS cu, @@hostname AS host, @@version AS ver"
    );
    $results['pre_check']['admin_user']    = $identRow->cu;
    $results['pre_check']['cluster_host']  = $identRow->host;
    $results['pre_check']['tidb_version']  = $identRow->ver;
    echo "Admin user: {$identRow->cu}\n";
    echo "Cluster host: {$identRow->host}\n";
    echo "TiDB version: {$identRow->ver}\n";

    if (!str_contains($identRow->cu, 'root') && !str_contains($identRow->cu, 'cloud_admin')) {
        $results['pre_check']['identity_guard'] = 'FAIL — not root or cloud_admin';
        echo "IDENTITY GUARD FAIL — aborting.\n";
        file_put_contents(__DIR__ . '/stage_5_5_3_3_1_results.json', json_encode($results, JSON_PRETTY_PRINT));
        exit(1);
    }
    $results['pre_check']['identity_guard'] = 'PASS';
} catch (\Exception $e) {
    echo "FATAL: Cannot connect admin: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/stage_5_5_3_3_1_results.json', json_encode(['fatal' => $e->getMessage()], JSON_PRETTY_PRINT));
    exit(1);
}

// 1b. Verify target database exists
try {
    $dbCheck = DB::connection($adminConn)->selectOne(
        "SELECT schema_name FROM information_schema.schemata WHERE schema_name = 'socialpilot'"
    );
    if (!$dbCheck) {
        $results['pre_check']['socialpilot_exists'] = 'FAIL — database not found';
        echo "GUARD FAIL: `socialpilot` not found — aborting.\n";
        file_put_contents(__DIR__ . '/stage_5_5_3_3_1_results.json', json_encode($results, JSON_PRETTY_PRINT));
        exit(1);
    }
    $results['pre_check']['socialpilot_exists'] = 'PASS';
    echo "Database `socialpilot`: EXISTS\n";
} catch (\Exception $e) {
    echo "ERROR checking database: " . $e->getMessage() . "\n";
    exit(1);
}

// 1c. Verify prod_migrator does NOT already exist
try {
    DB::connection($adminConn)->select("SHOW GRANTS FOR '{$newUser}'@'{$newHost}'");
    // If we reach here, the account exists — STOP
    $results['pre_check']['prod_migrator_pre_exists'] = 'EXISTS — ABORTING (do not modify existing account)';
    echo "GUARD FAIL: '{$newUser}' already exists — aborting without modification.\n";
    file_put_contents(__DIR__ . '/stage_5_5_3_3_1_results.json', json_encode($results, JSON_PRETTY_PRINT));
    exit(1);
} catch (\Exception $e) {
    if (str_contains($e->getMessage(), '1141') || str_contains($e->getMessage(), 'no such grant')) {
        $results['pre_check']['prod_migrator_pre_exists'] = 'PASS — does not exist';
        echo "Guard: prod_migrator does NOT exist — safe to create\n";
    } else {
        $results['pre_check']['prod_migrator_pre_exists'] = 'ERROR: ' . $e->getMessage();
        echo "ERROR checking prod_migrator: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// 1d. Verify prod_app grants remain at baseline
try {
    $prodAppGrants = DB::connection($adminConn)->select("SHOW GRANTS FOR 'AZDARrqgy4MBFGE.prod_app'@'%'");
    $grantStrings  = array_map(fn($g) => array_values((array)$g)[0], $prodAppGrants);
    $results['pre_check']['prod_app_grants_before'] = $grantStrings;

    $hasUsage  = count(array_filter($grantStrings, fn($s) => str_contains($s, 'USAGE ON *.*'))) > 0;
    $hasDml    = count(array_filter($grantStrings, fn($s) =>
        str_contains($s, 'SELECT,INSERT,UPDATE,DELETE') &&
        str_contains($s, '`socialpilot`.*')
    )) > 0;
    $hasExtra  = count($grantStrings) > 2;

    if (!$hasUsage || !$hasDml || $hasExtra) {
        $results['pre_check']['prod_app_baseline'] = 'FAIL — unexpected grant state';
        echo "GUARD FAIL: prod_app grants unexpected — aborting.\n";
        file_put_contents(__DIR__ . '/stage_5_5_3_3_1_results.json', json_encode($results, JSON_PRETTY_PRINT));
        exit(1);
    }
    $results['pre_check']['prod_app_baseline'] = 'PASS — matches 5.5.3.1 specification';
    echo "prod_app baseline: PASS\n";
} catch (\Exception $e) {
    $results['pre_check']['prod_app_error'] = $e->getMessage();
    echo "ERROR verifying prod_app: " . $e->getMessage() . "\n";
    exit(1);
}

// 1e. TLS check on admin connection
try {
    $tlsRow = DB::connection($adminConn)->selectOne("SHOW STATUS LIKE 'Ssl_cipher'");
    $results['pre_check']['admin_tls'] = $tlsRow ? $tlsRow->Value : 'NONE';
    echo "Admin TLS cipher: " . ($tlsRow ? $tlsRow->Value : 'NONE') . "\n";
} catch (\Exception $e) {
    $results['pre_check']['admin_tls'] = 'ERROR: ' . $e->getMessage();
}

echo "\nAll pre-execution guards PASSED.\n\n";

// ── PHASE 2: Generate credential (never printed) ──────────────────────────────
echo "--- PHASE 2: Credential Generation ---\n";

// Generate locally using random_bytes — cryptographically secure, offline, never printed
$rawBytes        = random_bytes(32);
$generatedPassword = bin2hex($rawBytes); // 64-char hex string

// Sanitize for SQL interpolation safety (hex string is alphanumeric — no special chars possible)
if (!preg_match('/^[0-9a-f]{64}$/', $generatedPassword)) {
    echo "FATAL: Generated password failed sanity check — aborting.\n";
    exit(1);
}
$results['credential_generated'] = true;
echo "Credential generated: YES (64-char hex, never printed)\n";

// Store in .env.staging.local (append if key absent, replace if present)
$envFile = __DIR__ . '/../.env.staging.local';
$envContent = file_exists($envFile) ? file_get_contents($envFile) : '';

$userKey = 'TIDB_PROD_MIGRATOR_USERNAME';
$passKey = 'TIDB_PROD_MIGRATOR_PASSWORD';
$userVal = $newUser;

// Update or append USERNAME
if (preg_match('/^' . preg_quote($userKey) . '=.*/m', $envContent)) {
    $envContent = preg_replace('/^' . preg_quote($userKey) . '=.*/m', $userKey . '=' . $userVal, $envContent);
} else {
    $envContent .= PHP_EOL . $userKey . '=' . $userVal;
}

// Update or append PASSWORD
if (preg_match('/^' . preg_quote($passKey) . '=.*/m', $envContent)) {
    $envContent = preg_replace('/^' . preg_quote($passKey) . '=.*/m', $passKey . '=' . $generatedPassword, $envContent);
} else {
    $envContent .= PHP_EOL . $passKey . '=' . $generatedPassword;
}

file_put_contents($envFile, $envContent);
$results['credential_stored_in'] = '.env.staging.local (gitignored)';
echo "Credential stored: .env.staging.local\n\n";

// ── PHASE 3: Create account ───────────────────────────────────────────────────
echo "--- PHASE 3: Account Creation ---\n";

try {
    // TiDB does not support PDO named placeholders in DDL — use interpolation
    // Password is a validated 64-char hex string (no special characters)
    DB::connection($adminConn)->statement(
        "CREATE USER '{$newUser}'@'{$newHost}' IDENTIFIED BY '{$generatedPassword}' REQUIRE SSL"
    );
    $results['account_creation'] = 'SUCCESS';
    echo "CREATE USER: SUCCESS\n";
} catch (\Exception $e) {
    $results['account_creation'] = 'FAIL: ' . $e->getMessage();
    echo "CREATE USER FAILED: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/stage_5_5_3_3_1_results.json', json_encode($results, JSON_PRETTY_PRINT));
    exit(1);
}

// ── PHASE 4: Grant privileges ─────────────────────────────────────────────────
echo "--- PHASE 4: Grant Privileges ---\n";

try {
    DB::connection($adminConn)->statement(
        "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, ALTER, INDEX
         ON `socialpilot`.*
         TO '{$newUser}'@'{$newHost}'"
    );
    $results['grant'] = 'SUCCESS — SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,REFERENCES,ALTER,INDEX ON socialpilot.*';
    echo "GRANT: SUCCESS\n";
} catch (\Exception $e) {
    $results['grant'] = 'FAIL: ' . $e->getMessage();
    echo "GRANT FAILED: " . $e->getMessage() . "\n";
    // Attempt cleanup
    try {
        DB::connection($adminConn)->statement("DROP USER '{$newUser}'@'{$newHost}'");
        echo "Cleanup: Account dropped after grant failure.\n";
    } catch (\Exception $ex) {
        echo "Cleanup failed: " . $ex->getMessage() . "\n";
    }
    file_put_contents(__DIR__ . '/stage_5_5_3_3_1_results.json', json_encode($results, JSON_PRETTY_PRINT));
    exit(1);
}

// ── PHASE 5: Admin-side grant verification ────────────────────────────────────
echo "\n--- PHASE 5: Admin Grant Verification ---\n";

try {
    $grants = DB::connection($adminConn)->select("SHOW GRANTS FOR '{$newUser}'@'{$newHost}'");
    $grantStrings = array_map(fn($g) => array_values((array)$g)[0], $grants);
    $results['admin_show_grants'] = $grantStrings;

    foreach ($grantStrings as $gs) {
        echo "  $gs\n";
    }

    // Validate: must have USAGE + the 9 DML/DDL privileges, nothing else
    $hasUsage       = (bool) count(array_filter($grantStrings, fn($s) => str_contains($s, 'USAGE ON *.*')));
    $hasPrivileges  = (bool) count(array_filter($grantStrings, fn($s) =>
        str_contains($s, 'SELECT') &&
        str_contains($s, 'INSERT') &&
        str_contains($s, 'UPDATE') &&
        str_contains($s, 'DELETE') &&
        str_contains($s, 'CREATE') &&
        str_contains($s, 'DROP') &&
        str_contains($s, 'REFERENCES') &&
        str_contains($s, 'ALTER') &&
        str_contains($s, 'INDEX') &&
        str_contains($s, '`socialpilot`.*')
    ));
    $hasGrantOption = (bool) count(array_filter($grantStrings, fn($s) => str_contains($s, 'WITH GRANT OPTION')));
    $hasGlobal      = (bool) count(array_filter($grantStrings, fn($s) =>
        (str_contains($s, 'ON *.*') && !str_contains($s, 'USAGE ON *.*'))
    ));
    $hasExtra       = count($grantStrings) > 2;

    $results['grant_validation'] = [
        'has_usage_on_global'     => $hasUsage,
        'has_nine_privileges'     => $hasPrivileges,
        'has_grant_option'        => $hasGrantOption,
        'has_global_privileges'   => $hasGlobal,
        'grant_count'             => count($grantStrings),
        'exactly_two_grant_lines' => !$hasExtra,
    ];

    $adminGrantPass = $hasUsage && $hasPrivileges && !$hasGrantOption && !$hasGlobal && !$hasExtra;
    $results['admin_grant_verification'] = $adminGrantPass ? 'PASS' : 'FAIL';
    echo "Admin grant verification: " . ($adminGrantPass ? 'PASS' : 'FAIL') . "\n";
} catch (\Exception $e) {
    $results['admin_grant_verification'] = 'ERROR: ' . $e->getMessage();
    echo "ERROR: " . $e->getMessage() . "\n";
}

// ── PHASE 6: Live authentication verification ─────────────────────────────────
echo "\n--- PHASE 6: Live prod_migrator Authentication ---\n";

// Build a Laravel connection config dynamically for the new account
Config::set('database.connections.tidb_prod_migrator', array_merge(
    config('database.connections.tidb_staging_app'), // inherit TiDB SSL + host settings
    [
        'database' => 'socialpilot',
        'username' => $newUser,
        'password' => $generatedPassword,
    ]
));

// 6a. Connect and verify identity
try {
    $identRow2 = DB::connection('tidb_prod_migrator')->selectOne(
        "SELECT current_user() AS cu, database() AS db"
    );
    $results['new_account_identity'] = [
        'current_user' => $identRow2->cu,
        'database'     => $identRow2->db,
    ];
    echo "Connected as: {$identRow2->cu}  DB: {$identRow2->db}\n";

    $identOk = str_contains($identRow2->cu, 'prod_migrator');
    $results['new_account_identity_check'] = $identOk ? 'PASS' : 'FAIL';
    echo "Identity check: " . ($identOk ? 'PASS' : 'FAIL') . "\n";
} catch (\Exception $e) {
    $results['new_account_identity'] = 'FAIL: ' . $e->getMessage();
    echo "AUTH FAIL: " . $e->getMessage() . "\n";
}

// 6b. TLS verification
try {
    $tlsRow2 = DB::connection('tidb_prod_migrator')->selectOne("SHOW STATUS LIKE 'Ssl_cipher'");
    $results['new_account_tls'] = $tlsRow2 ? $tlsRow2->Value : 'NONE';
    echo "TLS cipher: " . ($tlsRow2 ? $tlsRow2->Value : 'NONE') . "\n";
} catch (\Exception $e) {
    $results['new_account_tls'] = 'ERROR: ' . $e->getMessage();
}

// 6c. SELECT on socialpilot (verify read access)
try {
    $migCount = DB::connection('tidb_prod_migrator')->selectOne(
        "SELECT COUNT(*) AS cnt FROM `socialpilot`.`migrations`"
    );
    $results['new_account_select_socialpilot'] = "PASS — {$migCount->cnt} migrations rows";
    echo "SELECT on socialpilot.migrations: PASS ({$migCount->cnt} rows)\n";
} catch (\Exception $e) {
    $results['new_account_select_socialpilot'] = 'FAIL: ' . $e->getMessage();
    echo "SELECT FAIL: " . $e->getMessage() . "\n";
}

// 6d. staging isolation check
try {
    DB::connection('tidb_prod_migrator')->selectOne(
        "SELECT COUNT(*) AS cnt FROM `socialpilot_staging`.`migrations`"
    );
    $results['staging_isolation'] = 'FAIL — access was NOT denied';
    echo "Staging isolation: FAIL — unexpected access\n";
} catch (\Exception $e) {
    if (str_contains($e->getMessage(), 'Access denied') || str_contains($e->getMessage(), '1044') || str_contains($e->getMessage(), '1142')) {
        $results['staging_isolation'] = 'PASS — access denied';
        echo "Staging isolation: PASS (access denied)\n";
    } else {
        $results['staging_isolation'] = 'INCONCLUSIVE: ' . $e->getMessage();
        echo "Staging isolation: INCONCLUSIVE — " . $e->getMessage() . "\n";
    }
}

// 6e. migration_test isolation check
try {
    DB::connection('tidb_prod_migrator')->selectOne(
        "SELECT COUNT(*) AS cnt FROM `socialpilot_migration_test`.`migrations`"
    );
    $results['migration_test_isolation'] = 'FAIL — access was NOT denied';
    echo "Migration-test isolation: FAIL — unexpected access\n";
} catch (\Exception $e) {
    if (str_contains($e->getMessage(), 'Access denied') || str_contains($e->getMessage(), '1044') || str_contains($e->getMessage(), '1142')) {
        $results['migration_test_isolation'] = 'PASS — access denied';
        echo "Migration-test isolation: PASS (access denied)\n";
    } else {
        $results['migration_test_isolation'] = 'INCONCLUSIVE: ' . $e->getMessage();
        echo "Migration-test isolation: INCONCLUSIVE — " . $e->getMessage() . "\n";
    }
}

// ── PHASE 7: Confirm prod_app unchanged ───────────────────────────────────────
echo "\n--- PHASE 7: prod_app Post-Provisioning Verification ---\n";

try {
    $prodAppGrantsAfter = DB::connection($adminConn)->select("SHOW GRANTS FOR 'AZDARrqgy4MBFGE.prod_app'@'%'");
    $prodAppGrantStrings = array_map(fn($g) => array_values((array)$g)[0], $prodAppGrantsAfter);
    $results['prod_app_grants_after'] = $prodAppGrantStrings;

    $unchanged = $prodAppGrantStrings === ($results['pre_check']['prod_app_grants_before'] ?? []);
    $results['prod_app_unchanged'] = $unchanged ? 'PASS — grants identical before and after' : 'FAIL — grants changed';
    echo "prod_app unchanged: " . ($unchanged ? 'PASS' : 'FAIL') . "\n";
    foreach ($prodAppGrantStrings as $gs) {
        echo "  $gs\n";
    }
} catch (\Exception $e) {
    $results['prod_app_unchanged'] = 'ERROR: ' . $e->getMessage();
    echo "ERROR: " . $e->getMessage() . "\n";
}

// ── Save results ──────────────────────────────────────────────────────────────
file_put_contents(__DIR__ . '/stage_5_5_3_3_1_results.json', json_encode($results, JSON_PRETTY_PRINT));
echo "\nResults saved to scripts/stage_5_5_3_3_1_results.json\n";
echo "\nPROVISIONING COMPLETE\n";
