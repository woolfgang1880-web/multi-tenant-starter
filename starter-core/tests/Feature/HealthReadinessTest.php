<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_is_simple_and_available(): void
    {
        $res = $this->getJson('/api/v1/health');

        $res->assertOk()
            ->assertJson([
                'status' => 'ok',
            ])
            ->assertJsonStructure(['status']);
    }

    public function test_readiness_reports_expected_structure_in_normal_conditions(): void
    {
        $res = $this->getJson('/api/v1/ready');

        $res->assertOk();
        $res->assertJsonStructure([
            'status',
            'checks' => [
                'database' => ['ok', 'driver'],
                'auth_schema' => ['ok'],
                'cache' => ['ok', 'store'],
                'queue' => ['ok', 'connection'],
            ],
            'timestamp',
        ]);

        $res->assertJsonPath('status', 'ok');
        $this->assertTrue((bool) $res->json('checks.database.ok'));
        $this->assertTrue((bool) $res->json('checks.auth_schema.ok'));
        $this->assertTrue((bool) $res->json('checks.cache.ok'));
        $this->assertTrue((bool) $res->json('checks.queue.ok'));
    }
}

