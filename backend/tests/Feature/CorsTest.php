<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    private const APP = 'https://app.ejemplo.com.ar';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cors.allowed_origins' => [self::APP]]);
    }

    public function test_el_frontend_permitido_puede_hacer_peticiones_con_cookies(): void
    {
        $this->call('OPTIONS', '/api/login', [], [], [], [
            'HTTP_ORIGIN' => self::APP,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-xsrf-token',
        ])
            ->assertSuccessful()
            ->assertHeader('Access-Control-Allow-Origin', self::APP)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $this->getJson('/api/csrf', ['Origin' => self::APP])
            ->assertHeader('Access-Control-Allow-Origin', self::APP);
    }

    public function test_otro_origen_no_recibe_permiso(): void
    {
        $this->getJson('/api/csrf', ['Origin' => 'https://sitio-malo.example'])
            ->assertHeaderMissing('Access-Control-Allow-Origin');

        $this->call('OPTIONS', '/api/login', [], [], [], [
            'HTTP_ORIGIN' => 'https://sitio-malo.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ])->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
