<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentConfigurationTest extends TestCase
{
    public function test_website_and_health_routes_respond(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('DeenQ AI — Grow closer, one day at a time')
            ->assertSee('Make your practice your own.');
        $this->get('/up')->assertOk();
    }

    public function test_api_cors_allows_configured_origin_without_credentials(): void
    {
        config(['cors.allowed_origins' => ['http://deenmate-ai.test']]);

        $response = $this->withHeaders([
            'Origin' => 'http://deenmate-ai.test',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization,accept',
        ])->options('/api/v1/config');

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://deenmate-ai.test');
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_api_cors_rejects_unconfigured_origin(): void
    {
        config(['cors.allowed_origins' => ['http://deenmate-ai.test']]);

        $response = $this->withHeaders([
            'Origin' => 'https://untrusted.example',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/config');

        $this->assertNotSame(
            'https://untrusted.example',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }
}
