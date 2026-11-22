<?php
/**
 * STAGE 5.5.3.2 — Production Migration Audit Script
 * READ-ONLY: No schema changes, no data mutations.
 * Compares database/migrations files vs production `migrations` table.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$results = [];
$results['timestamp'] = date('c');
$results['mode'] = 'READ-ONLY AUDIT — NO MUTATIONS';

// ── 1. Identify which connection/database we're on ──────────────────────────
$adminConn = 'tidb_migration_test'; // uses root credentials

try {
    $row = DB::connection($adminConn)->selectOne('SELECT current_user() AS cu, database() AS db');
    $results['admin_identity'] = [
        'current_user' => $row->cu,
        'database'     => $row->db,
    ];
    echo "Connected as: {$row->cu}\n";
} catch (\Exception $e) {
    $results['admin_connect_error'] = $e->getMessage();
    echo "ERROR connecting admin: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/stage_5_5_3_2_results.json', json_encode($results, JSON_PRETTY_PRINT));
    exit(1);
}

// ── 2. Query production migrations table ────────────────────────────────────
try {
    DB::connection($adminConn)->statement('USE `socialpilot`');
    $results['target_database'] = 'socialpilot';

    $ranMigrations = DB::connection($adminConn)
        ->table('migrations')
        ->orderBy('id')
        ->get(['id', 'migration', 'batch'])
        ->toArray();

    $results['production_migrations_count'] = count($ranMigrations);
    $results['production_migrations'] = array_map(fn($r) => [
        'id'        => $r->id,
        'migration' => $r->migration,
        'batch'     => $r->batch,
    ], $ranMigrations);

    echo "Production ran migrations: " . count($ranMigrations) . "\n";
    foreach ($ranMigrations as $m) {
        echo "  [batch {$m->batch}] {$m->migration}\n";
    }
} catch (\Exception $e) {
    $results['production_query_error'] = $e->getMessage();
    echo "ERROR querying production: " . $e->getMessage() . "\n";
}

// ── 3. Query staging migrations table ───────────────────────────────────────
try {
    DB::connection($adminConn)->statement('USE `socialpilot_staging`');
    $stagingMigrations = DB::connection($adminConn)
        ->table('migrations')
        ->orderBy('id')
        ->get(['id', 'migration', 'batch'])
        ->toArray();

    $results['staging_migrations_count'] = count($stagingMigrations);
    $results['staging_migrations'] = array_map(fn($r) => [
        'id'        => $r->id,
        'migration' => $r->migration,
        'batch'     => $r->batch,
    ], $stagingMigrations);

    echo "\nStaging ran migrations: " . count($stagingMigrations) . "\n";
    foreach ($stagingMigrations as $m) {
        echo "  [batch {$m->batch}] {$m->migration}\n";
    }
} catch (\Exception $e) {
    $results['staging_query_error'] = $e->getMessage();
    echo "ERROR querying staging: " . $e->getMessage() . "\n";
}

// ── 4. Enumerate local migration files ──────────────────────────────────────
$migrationsPath = __DIR__ . '/../database/migrations';
$localFiles = [];
foreach (glob($migrationsPath . '/*.php') as $file) {
    $localFiles[] = pathinfo($file, PATHINFO_FILENAME);
}
sort($localFiles);

$results['local_migration_files_count'] = count($localFiles);
$results['local_migration_files'] = $localFiles;

echo "\nLocal migration files: " . count($localFiles) . "\n";

// ── 5. Compute delta (pending in production) ─────────────────────────────────
$ranNames  = array_map(fn($r) => $r['migration'], $results['production_migrations'] ?? []);
$pendingInProd = array_values(array_diff($localFiles, $ranNames));

$results['pending_in_production'] = $pendingInProd;
$results['pending_count']         = count($pendingInProd);

echo "\nPending in production (" . count($pendingInProd) . "):\n";
foreach ($pendingInProd as $p) {
    echo "  - $p\n";
}

// ── 6. Verify production TLS ─────────────────────────────────────────────────
try {
    DB::connection($adminConn)->statement('USE `socialpilot`');
    $tlsRow = DB::connection($adminConn)->selectOne(
        "SHOW STATUS LIKE 'Ssl_cipher'"
    );
    $results['production_tls_cipher'] = $tlsRow ? $tlsRow->Value : 'NOT FOUND';
    echo "\nProduction TLS cipher: " . ($tlsRow ? $tlsRow->Value : 'NOT FOUND') . "\n";
} catch (\Exception $e) {
    $results['production_tls_error'] = $e->getMessage();
}

// ── 7. Check key tables exist in production ──────────────────────────────────
try {
    DB::connection($adminConn)->statement('USE `socialpilot`');
    $tables = DB::connection($adminConn)
        ->select("SHOW TABLES");
    $tableNames = array_map(fn($r) => array_values((array)$r)[0], $tables);
    $results['production_table_count'] = count($tableNames);

    // Check specifically if the two tables from the pending migration exist and have the indexes
    $checkTables = ['brand_content_drafts', 'content_revisions'];
    foreach ($checkTables as $tbl) {
        $exists = in_array($tbl, $tableNames);
        $results['table_exists'][$tbl] = $exists;
        if ($exists) {
            // Check for the specific unique indexes the pending migration would add
            $indexes = DB::connection($adminConn)
                ->select("SHOW INDEX FROM `{$tbl}`");
            $indexNames = array_map(fn($r) => $r->Key_name, $indexes);
            $results['table_indexes'][$tbl] = $indexNames;
            echo "Table `{$tbl}` exists. Indexes: " . implode(', ', $indexNames) . "\n";
        } else {
            echo "Table `{$tbl}` does NOT exist in production.\n";
        }
    }

    // Check for duplicate data that would block migration
    if (in_array('brand_content_drafts', $tableNames)) {
        $dupJobs = DB::connection($adminConn)
            ->select("SELECT generation_job_id, COUNT(*) as cnt FROM `socialpilot`.`brand_content_drafts` WHERE generation_job_id IS NOT NULL GROUP BY generation_job_id HAVING cnt > 1");
        $results['duplicate_generation_job_ids'] = count($dupJobs);
        echo "Duplicate generation_job_id rows in prod: " . count($dupJobs) . "\n";
    }

    if (in_array('content_revisions', $tableNames)) {
        $dupRevs = DB::connection($adminConn)
            ->select("SELECT draft_id, revision_number, COUNT(*) as cnt FROM `socialpilot`.`content_revisions` GROUP BY draft_id, revision_number HAVING cnt > 1");
        $results['duplicate_content_revisions'] = count($dupRevs);
        echo "Duplicate (draft_id, revision_number) rows in prod: " . count($dupRevs) . "\n";
    }
} catch (\Exception $e) {
    $results['table_check_error'] = $e->getMessage();
    echo "ERROR checking tables: " . $e->getMessage() . "\n";
}

// ── Save results ─────────────────────────────────────────────────────────────
file_put_contents(__DIR__ . '/stage_5_5_3_2_results.json', json_encode($results, JSON_PRETTY_PRINT));
echo "\nResults saved to scripts/stage_5_5_3_2_results.json\n";
echo "\nAUDIT COMPLETE — NO MUTATIONS MADE\n";
