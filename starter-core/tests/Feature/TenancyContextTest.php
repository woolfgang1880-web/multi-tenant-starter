<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveTenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TenancyContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_returns_null_without_authenticated_user(): void
    {
        $this->assertNull(app(\App\Contracts\Tenancy\TenantResolver::class)->resolve(Request::create('/')));
    }

    public function test_resolver_returns_tenant_for_authenticated_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $resolved = app(\App\Contracts\Tenancy\TenantResolver::class)->resolve($request);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($tenant));
    }

    public function test_middleware_sets_context_during_request_and_clears_after(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $middleware = app(ResolveTenantContext::class);
        $manager = app(TenantManager::class);

        $middleware->handle($request, function () use ($manager, $tenant) {
            $this->assertTrue($manager->current()->is($tenant));
            $this->assertSame($tenant->id, $manager->id());

            return new Response('ok');
        });

        $this->assertNull($manager->current());
        $this->assertNull($manager->id());
    }
}
