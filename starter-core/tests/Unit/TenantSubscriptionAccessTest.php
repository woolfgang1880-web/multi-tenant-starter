<?php

namespace Tests\Unit;

use App\Models\Tenant;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TenantSubscriptionAccessTest extends TestCase
{
    public static function allowsAccessMatrix(): array
    {
        return [
            'null_status' => [null, true, null, true],
            'active' => [Tenant::SUBSCRIPTION_ACTIVE, true, null, true],
            'suspended' => [Tenant::SUBSCRIPTION_SUSPENDED, true, null, false],
            'expired_status' => [Tenant::SUBSCRIPTION_EXPIRED, true, null, false],
            'trial_no_end' => [Tenant::SUBSCRIPTION_TRIAL, true, null, true],
            'trial_future_end' => [Tenant::SUBSCRIPTION_TRIAL, true, 'future', true],
            'trial_past_end' => [Tenant::SUBSCRIPTION_TRIAL, true, 'past', false],
            'inactive_tenant' => [Tenant::SUBSCRIPTION_ACTIVE, false, null, false],
        ];
    }

    #[DataProvider('allowsAccessMatrix')]
    public function test_allows_api_access_matrix(?string $status, bool $activo, ?string $endKind, bool $expected): void
    {
        $ends = null;
        if ($endKind === 'past') {
            $ends = now()->subDay();
        }
        if ($endKind === 'future') {
            $ends = now()->addWeek();
        }

        $tenant = new Tenant([
            'activo' => $activo,
            'subscription_status' => $status,
            'trial_ends_at' => $ends,
        ]);

        $this->assertSame($expected, $tenant->allowsApiAccess());
    }
}
