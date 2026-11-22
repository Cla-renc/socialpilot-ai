<?php
/**
 * STAGE 5.5.3.3 — Production Migrator Readiness Audit Script
 * READ-ONLY: No schema changes, no account creation, no data mutations.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$results = [];
$results['timestamp'] = date('c');
$results['mode'] = 'READ-ONLY AUDIT — NO MUTATIONS';

$adminConn = 'tidb_migration_test'; // uses root credentials

// ── 1. Admin identity & cluster verification ────────────────────────────────
try {
    $row = DB::connection($adminConn)->selectOne('SELECT current_user() AS cu, @@hostname AS host, @@version AS ver');
    $results['admin_identity'] = [
        'current_user' => $row->cu,
        'host'         => $row->host,
        'version'      => $row->ver,
    ];
    echo "Admin connected as: {$row->cu}\n";
    echo "Host: {$row->host}  Version: {$row->ver}\n\n";
} catch (\Exception $e) {
    echo "ERROR connecting admin: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/stage_5_5_3_3_results.json', json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT));
    exit(1);
}

// ── 2. Full account inventory ────────────────────────────────────────────────
echo "=== ACCOUNT INVENTORY ===\n";
try {
    $users = DB::connection($adminConn)->select(
        "SELECT user, host, plugin, password_expired, ssl_type FROM mysql.user ORDER BY user"
    );
    $results['account_inventory'] = [];
    foreach ($users as $u) {
        $entry = [
            'user'              => $u->user,
            'host'              => $u->host,
            'plugin'            => $u->plugin,
            'password_expired'  => $u->password_expired,
            'ssl_type'          => $u->ssl_type,
        ];
        $results['account_inventory'][] = $entry;
        echo "  {$u->user}@{$u->host}  plugin={$u->plugin}  ssl={$u->ssl_type}\n";
    }
} catch (\Exception $e) {
    $results['account_inventory_error'] = $e->getMessage();
    echo "ERROR listing accounts: " . $e->getMessage() . "\n";
}

// ── 3. Verify prod_app grants ────────────────────────────────────────────────
echo "\n=== prod_app GRANTS ===\n";
$prodAppUser = 'AZDARrqgy4MBFGE.prod_app';
try {
    $grants = DB::connection($adminConn)->select("SHOW GRANTS FOR '{$prodAppUser}'@'%'");
    $results['prod_app_grants'] = [];
    foreach ($grants as $g) {
        $grantStr = array_values((array)$g)[0];
        $results['prod_app_grants'][] = $grantStr;
        echo "  $grantStr\n";
    }
    $results['prod_app_exists'] = true;
} catch (\Exception $e) {
    $results['prod_app_exists'] = false;
    $results['prod_app_error'] = $e->getMessage();
    echo "  prod_app NOT FOUND or error: " . $e->getMessage() . "\n";
}

// ── 4. Check if prod_migrator already exists ─────────────────────────────────
echo "\n=== prod_migrator EXISTENCE CHECK ===\n";
$prodMigratorUser = 'AZDARrqgy4MBFGE.prod_migrator';
try {
    $grants = DB::connection($adminConn)->select("SHOW GRANTS FOR '{$prodMigratorUser}'@'%'");
    $results['prod_migrator_exists'] = true;
    $results['prod_migrator_grants'] = [];
    foreach ($grants as $g) {
        $grantStr = array_values((array)$g)[0];
        $results['prod_migrator_grants'][] = $grantStr;
        echo "  ALREADY EXISTS: $grantStr\n";
    }
} catch (\Exception $e) {
    $results['prod_migrator_exists'] = false;
    echo "  prod_migrator does NOT exist (expected): " . $e->getMessage() . "\n";
}

// ── 5. Verify staging_migrator grants (reference model) ─────────────────────
echo "\n=== staging_migrator GRANTS (reference model) ===\n";
$stagingMigratorUser = 'AZDARrqgy4MBFGE.staging_migrator';
try {
    $grants = DB::connection($adminConn)->select("SHOW GRANTS FOR '{$stagingMigratorUser}'@'%'");
    $results['staging_migrator_grants'] = [];
    foreach ($grants as $g) {
        $grantStr = array_values((array)$g)[0];
        $results['staging_migrator_grants'][] = $grantStr;
        echo "  $grantStr\n";
    }
} catch (\Exception $e) {
    $results['staging_migrator_error'] = $e->getMessage();
    echo "  Error: " . $e->getMessage() . "\n";
}

// ── 6. Verify pending migration state in production ──────────────────────────
echo "\n=== PENDING MIGRATION VERIFICATION ===\n";
$pendingMigration = '2026_11_16_200000_add_integrity_constraints_to_content_generation_tables';
try {
    DB::connection($adminConn)->statement('USE `socialpilot`');
    $ran = DB::connection($adminConn)->selectOne(
        "SELECT id, migration, batch FROM `socialpilot`.`migrations` WHERE migration = ?",
        [$pendingMigration]
    );
    if ($ran) {
        $results['pending_migration_still_pending'] = false;
        $results['pending_migration_note'] = "ALREADY RAN — batch {$ran->batch}";
        echo "  ALREADY RAN in batch {$ran->batch} — migration is NOT pending\n";
    } else {
        $results['pending_migration_still_pending'] = true;
        $results['pending_migration_note'] = "CONFIRMED PENDING";
        echo "  CONFIRMED PENDING — not yet in migrations table\n";
    }
} catch (\Exception $e) {
    $results['pending_migration_error'] = $e->getMessage();
    echo "  Error: " . $e->getMessage() . "\n";
}

// ── 7. Inspect existing indexes (conflicting index check) ────────────────────
echo "\n=== INDEX CONFLICT CHECK ===\n";
$indexChecks = [
    'brand_content_drafts'  => ['bcd_generation_job_id_unique', 'brand_content_drafts_generation_job_id_unique'],
    'content_revisions'     => ['cr_draft_id_revision_number_unique', 'content_revisions_draft_id_revision_number_unique'],
];
$results['index_conflicts'] = [];
foreach ($indexChecks as $table => $watchIndexes) {
    try {
        $indexes = DB::connection($adminConn)->select("SHOW INDEX FROM `socialpilot`.`{$table}`");
        $allIndexNames = array_map(fn($r) => $r->Key_name, $indexes);
        $conflicts = array_intersect($allIndexNames, $watchIndexes);
        $results['index_conflicts'][$table] = [
            'all_indexes'      => $allIndexNames,
            'conflicting'      => array_values($conflicts),
            'conflict_found'   => !empty($conflicts),
        ];
        echo "  Table `{$table}`:\n";
        echo "    All indexes: " . implode(', ', $allIndexNames) . "\n";
        echo "    Conflicts: " . (empty($conflicts) ? 'NONE' : implode(', ', $conflicts)) . "\n";
    } catch (\Exception $e) {
        $results['index_conflicts'][$table] = ['error' => $e->getMessage()];
        echo "  Error on {$table}: " . $e->getMessage() . "\n";
    }
}

// ── 8. Re-run duplicate pre-flight checks ───────────────────────────────────
echo "\n=== DUPLICATE PRE-FLIGHT CHECKS ===\n";
try {
    $dupJobs = DB::connection($adminConn)->select(
        "SELECT generation_job_id, COUNT(*) as cnt
         FROM `socialpilot`.`brand_content_drafts`
         WHERE generation_job_id IS NOT NULL
         GROUP BY generation_job_id
         HAVING cnt > 1"
    );
    $results['preflight_duplicate_generation_job_ids'] = count($dupJobs);
    echo "  Duplicate generation_job_id: " . count($dupJobs) . "\n";
} catch (\Exception $e) {
    $results['preflight_duplicate_error_1'] = $e->getMessage();
    echo "  Error: " . $e->getMessage() . "\n";
}

try {
    $dupRevs = DB::connection($adminConn)->select(
        "SELECT draft_id, revision_number, COUNT(*) as cnt
         FROM `socialpilot`.`content_revisions`
         GROUP BY draft_id, revision_number
         HAVING cnt > 1"
    );
    $results['preflight_duplicate_revisions'] = count($dupRevs);
    echo "  Duplicate (draft_id, revision_number): " . count($dupRevs) . "\n";
} catch (\Exception $e) {
    $results['preflight_duplicate_error_2'] = $e->getMessage();
    echo "  Error: " . $e->getMessage() . "\n";
}

// ── 9. Production TLS verification ──────────────────────────────────────────
echo "\n=== PRODUCTION TLS ===\n";
try {
    $tlsRow = DB::connection($adminConn)->selectOne("SHOW STATUS LIKE 'Ssl_cipher'");
    $results['production_tls'] = $tlsRow ? $tlsRow->Value : 'NOT FOUND';
    echo "  TLS cipher: " . ($tlsRow ? $tlsRow->Value : 'NOT FOUND') . "\n";
} catch (\Exception $e) {
    $results['production_tls_error'] = $e->getMessage();
}

// ── 10. Verify socialpilot database exists and table count ──────────────────
echo "\n=== PRODUCTION DATABASE VERIFICATION ===\n";
try {
    $tables = DB::connection($adminConn)->select("SHOW TABLES FROM `socialpilot`");
    $results['production_table_count'] = count($tables);
    echo "  Tables in `socialpilot`: " . count($tables) . "\n";
} catch (\Exception $e) {
    $results['production_table_count_error'] = $e->getMessage();
}

// ── Save ─────────────────────────────────────────────────────────────────────
file_put_contents(__DIR__ . '/stage_5_5_3_3_results.json', json_encode($results, JSON_PRETTY_PRINT));
echo "\nResults saved to scripts/stage_5_5_3_3_results.json\n";
echo "\nAUDIT COMPLETE — NO MUTATIONS MADE\n";
