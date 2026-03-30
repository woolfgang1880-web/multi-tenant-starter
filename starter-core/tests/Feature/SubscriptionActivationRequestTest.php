<?php

namespace Tests\Feature;

use App\Models\SubscriptionActivationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionActivationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_subscription_request_activation_registers_and_returns_201(): void
    {
        $res = $this->postJson('/api/v1/subscription/request-activation', [
            'tenant_codigo' => 'DEFAULT',
            'contact_email' => 'user@example.com',
            'message' => 'Necesito acceso',
        ]);

        $res->assertCreated()
            ->assertJsonPath('code', 'OK')
            ->assertJsonPath('data.received', true);

        $this->assertDatabaseHas('subscription_activation_requests', [
            'tenant_codigo' => 'DEFAULT',
            'contact_email' => 'user@example.com',
        ]);

        $this->assertSame(1, SubscriptionActivationRequest::query()->count());
    }

    public function test_post_subscription_request_activation_accepts_empty_body(): void
    {
        $res = $this->postJson('/api/v1/subscription/request-activation', []);

        $res->assertCreated()->assertJsonPath('code', 'OK');
        $this->assertSame(1, SubscriptionActivationRequest::query()->count());
    }
}
