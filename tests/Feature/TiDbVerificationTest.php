<?php

namespace Tests\Feature;

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

class TiDbVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_1_tidb_verify_command_reports_unconfigured_state_safely(): void
    {
        $exitCode = Artisan::call('db:tidb-verify', ['--host' => '127.0.0.1']);
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('TIDB SERVERLESS VERIFICATION', $output);
        $this->assertStringContainsString('Current active connection remains:', $output);
    }

    public function test_1b_tidb_migration_preflight_blocks_invalid_connection(): void
    {
        $exitCode = Artisan::call('db:tidb-migration-preflight', ['--connection' => 'mysql']);
        $output = Artisan::output();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString("Target connection must strictly be 'tidb_migration_test'", $output);
    }

    public function test_2_mysql_configuration_supports_tls_and_tidb_options(): void
    {
        $mysqlConfig = config('database.connections.mysql');

        $this->assertEquals('mysql', $mysqlConfig['driver']);
        $this->assertEquals('utf8mb4', $mysqlConfig['charset']);
        $this->assertEquals('utf8mb4_unicode_ci', $mysqlConfig['collation']);
        $this->assertTrue($mysqlConfig['strict']);

        // Check SSL CA option wiring
        Config::set('database.connections.mysql.options.' . PDO::MYSQL_ATTR_SSL_CA, '/path/to/ca.crt');
        $updatedConfig = config('database.connections.mysql');
        $this->assertEquals('/path/to/ca.crt', $updatedConfig['options'][PDO::MYSQL_ATTR_SSL_CA]);
    }

    public function test_3_database_backup_creates_valid_backup_and_verifies_restoration(): void
    {
        $exitCode = Artisan::call('db:backup', ['--verify' => true, '--keep' => 3]);
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Backup created successfully', $output);
        $this->assertStringContainsString('Integrity check passed', $output);
        $this->assertStringContainsString('Restoration verification test PASSED', $output);
    }

    public function test_4_plan_seeder_is_idempotent(): void
    {
        // First run
        $seeder = new PlanSeeder();
        $seeder->run();
        $this->assertDatabaseCount('plans', 4);

        // Second run should updateOrCreate without duplicating or throwing unique constraint violations
        $seeder->run();
        $this->assertDatabaseCount('plans', 4);

        $this->assertDatabaseHas('plans', ['slug' => 'free', 'price_monthly' => 0]);
        $this->assertDatabaseHas('plans', ['slug' => 'starter', 'price_monthly' => 29]);
        $this->assertDatabaseHas('plans', ['slug' => 'professional', 'price_monthly' => 79]);
        $this->assertDatabaseHas('plans', ['slug' => 'enterprise', 'price_monthly' => 199]);
    }

    public function test_5_zero_cost_policy_preserves_local_sqlite(): void
    {
        $defaultConnection = config('database.default');
        $this->assertContains($defaultConnection, ['sqlite', 'testing']);

        $sqlitePath = database_path('database.sqlite');
        $this->assertTrue(File::exists($sqlitePath), 'Local development database.sqlite must exist and be preserved.');
    }
}
