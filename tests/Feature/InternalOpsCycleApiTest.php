<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalOpsCycleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.ops.cron_secret', 'test-secure-ops-secret-key-12345');
        Config::set('queue.default', 'database');
    }

    public function test_cycle_fails_with_503_when_secret_is_unconfigured(): void
    {
        Config::set('services.ops.cron_secret', '');

        $response = $this->postJson('/api/internal/ops/cycle');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'error',
                'message' => 'Internal operations secret is not configured on the server.',
            ]);
    }

    public function test_cycle_rejects_missing_credentials_with_401(): void
    {
        $response = $this->postJson('/api/internal/ops/cycle');

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'unauthorized',
            ]);
    }

    public function test_cycle_rejects_invalid_token_with_401(): void
    {
        $response = $this->postJson('/api/internal/ops/cycle', [], [
            'Authorization' => 'Bearer invalid-secret-token',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'unauthorized',
            ]);
    }

    public function test_cycle_accepts_valid_bearer_token(): void
    {
        $response = $this->postJson('/api/internal/ops/cycle', [], [
            'Authorization' => 'Bearer test-secure-ops-secret-key-12345',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'duration_seconds',
                'memory_peak_mb',
                'scheduler' => ['exit_code', 'output'],
                'queue' => ['exit_code', 'output'],
            ])
            ->assertJson([
                'status' => 'success',
            ]);
    }

    public function test_cycle_accepts_valid_x_ops_secret_header(): void
    {
        $response = $this->postJson('/api/v1/internal/ops/cycle', [], [
            'X-Ops-Secret' => 'test-secure-ops-secret-key-12345',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);
    }

    public function test_cycle_prevents_overlapping_runs_via_lock(): void
    {
        $lock = Cache::lock('internal_ops_cycle_lock', 60);
        $this->assertTrue($lock->get());

        try {
            $response = $this->postJson('/api/internal/ops/cycle', [], [
                'Authorization' => 'Bearer test-secure-ops-secret-key-12345',
            ]);

            $response->assertStatus(429)
                ->assertJson([
                    'status' => 'locked',
                    'message' => 'An operational cycle is already in progress.',
                ]);
        } finally {
            $lock->release();
        }
    }

    public function test_cycle_processes_bounded_queue_job(): void
    {
        // Enqueue a simple closure or job into the database queue
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode([
                'uuid' => 'test-uuid-001',
                'displayName' => 'TestJob',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => [
                    'commandName' => 'stdClass',
                    'command' => serialize(new \stdClass()),
                ],
            ]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->assertEquals(1, DB::table('jobs')->count());

        $response = $this->postJson('/api/internal/ops/cycle', [], [
            'Authorization' => 'Bearer test-secure-ops-secret-key-12345',
        ]);

        $response->assertStatus(200);
        // Job was dequeued and processed (or moved to failed if stdClass is not a valid handler)
        $this->assertEquals(0, DB::table('jobs')->count());
    }

    public function test_cycle_does_not_execute_arbitrary_user_commands(): void
    {
        // Even if user sends malicious command in request payload
        $response = $this->postJson('/api/internal/ops/cycle', [
            'command' => 'migrate:reset',
            'artisan' => 'db:wipe',
            'action' => 'eval("echo hacked");',
        ], [
            'Authorization' => 'Bearer test-secure-ops-secret-key-12345',
        ]);

        $response->assertStatus(200);
        // Verify database tables still exist and were not wiped
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('users'));
    }
}
