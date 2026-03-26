<?php

namespace Tests\Feature;

use Tests\TestCase;

class HttpEdgeHardeningTest extends TestCase
{
    public function test_api_security_headers_are_present(): void
    {
        $res = $this->getJson('/api/v1/health');

        $res->assertOk();
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'DENY');
        $res->assertHeader('Referrer-Policy', 'no-referrer');
        $res->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_cors_preflight_uses_explicit_configuration(): void
    {
        config([
            'cors.allowed_origins' => ['https://frontend.example.test'],
            'cors.allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'cors.allowed_headers' => ['Content-Type', 'Authorization'],
            'cors.supports_credentials' => false,
        ]);

        $res = $this->options('/api/v1/health', [], [
            'HTTP_ORIGIN' => 'https://frontend.example.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Authorization',
        ]);

        $res->assertNoContent();
        $res->assertHeader('Access-Control-Allow-Origin', 'https://frontend.example.test');
    }
}

