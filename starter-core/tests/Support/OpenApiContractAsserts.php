<?php

namespace Tests\Support;

use PHPUnit\Framework\Assert as PHPUnitAssert;

/**
 * Aserciones pragmáticas alineadas con docs/openapi/openapi.yaml.
 * No validan el YAML contra JSON Schema automáticamente (ver docs/PASO5_OPENAPI_CONTRACT_TESTS.md).
 */
trait OpenApiContractAsserts
{
    protected function assertApiEnvelopeShape(array $json): void
    {
        PHPUnitAssert::assertArrayHasKey('code', $json, 'Envelope requiere code (OpenAPI ApiEnvelope*)');
        PHPUnitAssert::assertArrayHasKey('message', $json, 'Envelope requiere message');
        PHPUnitAssert::assertArrayHasKey('data', $json, 'Envelope requiere data');
    }

    protected function assertApiErrorEnvelope(array $json, ?string $expectedCode = null): void
    {
        $this->assertApiEnvelopeShape($json);
        PHPUnitAssert::assertIsString($json['code']);
        PHPUnitAssert::assertIsString($json['message']);
        if ($expectedCode !== null) {
            PHPUnitAssert::assertSame($expectedCode, $json['code']);
        }
    }

    protected function assertApiValidationEnvelope(array $json): void
    {
        $this->assertApiErrorEnvelope($json, 'VALIDATION_ERROR');
        PHPUnitAssert::assertIsArray($json['data']);
        PHPUnitAssert::assertArrayHasKey('errors', $json['data']);
        PHPUnitAssert::assertIsArray($json['data']['errors']);
    }

    /** @see openapi.yaml components/schemas/TokenPayload */
    protected function assertTokenPayloadShape(array $data): void
    {
        foreach (['access_token', 'refresh_token', 'token_type', 'expires_in', 'session_uuid'] as $k) {
            PHPUnitAssert::assertArrayHasKey($k, $data, "TokenPayload falta {$k}");
        }
        PHPUnitAssert::assertIsString($data['access_token']);
        PHPUnitAssert::assertIsString($data['refresh_token']);
        PHPUnitAssert::assertIsString($data['token_type']);
        PHPUnitAssert::assertIsInt($data['expires_in']);
        PHPUnitAssert::assertIsString($data['session_uuid']);
    }

    /** @see openapi.yaml components/schemas/ApiEnvelopeMe */
    protected function assertMeDataShape(array $data): void
    {
        PHPUnitAssert::assertArrayHasKey('user', $data);
        PHPUnitAssert::assertArrayHasKey('tenant', $data);
        PHPUnitAssert::assertArrayHasKey('accessible_tenants', $data);
        PHPUnitAssert::assertIsArray($data['accessible_tenants']);
        $u = $data['user'];
        foreach (['id', 'tenant_id', 'usuario', 'activo', 'roles'] as $k) {
            PHPUnitAssert::assertArrayHasKey($k, $u, "Perfil /me falta {$k}");
        }
        PHPUnitAssert::assertIsArray($u['roles']);
    }

    /** @see openapi.yaml components/schemas/UserWithRoles */
    protected function assertUserWithRolesShape(array $data): void
    {
        foreach (['id', 'tenant_id', 'usuario', 'activo', 'roles'] as $k) {
            PHPUnitAssert::assertArrayHasKey($k, $data, "UserWithRoles falta {$k}");
        }
        PHPUnitAssert::assertIsArray($data['roles']);
    }

    /** @see openapi.yaml components/schemas/RoleResource */
    protected function assertRoleResourceShape(array $data): void
    {
        foreach (['id', 'tenant_id', 'nombre', 'slug'] as $k) {
            PHPUnitAssert::assertArrayHasKey($k, $data, "RoleResource falta {$k}");
        }
    }

    /** @see openapi.yaml components/schemas/ApiEnvelopeUserList */
    protected function assertUserListDataShape(array $data): void
    {
        PHPUnitAssert::assertArrayHasKey('items', $data);
        PHPUnitAssert::assertArrayHasKey('meta', $data);
        PHPUnitAssert::assertIsArray($data['items']);
        $m = $data['meta'];
        foreach (['current_page', 'last_page', 'per_page', 'total'] as $k) {
            PHPUnitAssert::assertArrayHasKey($k, $m, "PaginationMeta falta {$k}");
        }
    }
}
