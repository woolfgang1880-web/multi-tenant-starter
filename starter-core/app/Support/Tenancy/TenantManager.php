<?php

namespace App\Support\Tenancy;

use App\Contracts\Tenancy\TenantResolver;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Fachada de aplicación: resolución + acceso al tenant actual.
 */
final class TenantManager
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantResolver $resolver,
    ) {}

    public function resolveFromRequest(Request $request): void
    {
        $this->context->set($this->resolver->resolve($request));
    }

    public function current(): ?Tenant
    {
        return $this->context->get();
    }

    public function id(): ?int
    {
        return $this->context->id();
    }

    public function clear(): void
    {
        $this->context->clear();
    }
}
