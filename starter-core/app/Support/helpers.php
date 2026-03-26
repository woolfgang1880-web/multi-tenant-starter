<?php

use App\Models\Tenant;
use App\Support\Tenancy\TenantManager;

if (! function_exists('tenant_manager')) {
    function tenant_manager(): TenantManager
    {
        return app(TenantManager::class);
    }
}

if (! function_exists('current_tenant')) {
    function current_tenant(): ?Tenant
    {
        return tenant_manager()->current();
    }
}

if (! function_exists('current_tenant_id')) {
    function current_tenant_id(): ?int
    {
        return tenant_manager()->id();
    }
}
