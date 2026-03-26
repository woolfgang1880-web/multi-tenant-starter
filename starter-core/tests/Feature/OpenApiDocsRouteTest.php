<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenApiDocsRouteTest extends TestCase
{
    public function test_openapi_yaml_is_served_and_valid_yaml(): void
    {
        $r = $this->get('/docs/openapi.yaml');

        $r->assertOk();
        $this->assertStringContainsString('yaml', (string) $r->headers->get('content-type'));
        $body = $r->streamedContent();
        $this->assertStringContainsString('openapi: 3.0.3', $body);
        $this->assertStringContainsString('/api/v1/auth/login', $body);
        $this->assertStringContainsString('/api/v1/health', $body);
        $this->assertStringContainsString('/api/v1/ready', $body);
    }

    public function test_swagger_ui_page_loads(): void
    {
        $this->get('/docs/api')
            ->assertOk()
            ->assertSee('swagger-ui', false);
    }
}
