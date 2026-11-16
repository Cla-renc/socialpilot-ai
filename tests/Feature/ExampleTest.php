<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertSee('href="#platform"', false)
            ->assertSee('href="#agents"', false)
            ->assertSee('href="#pricing"', false)
            ->assertSee('href="#resources"', false)
            ->assertSee('WORKSPACE PREVIEW')
            ->assertSee('Demo scheduling is not configured');
    }
}
