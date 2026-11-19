<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Client;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class TokenAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Clave-Segura#1234';

    private function user(): User
    {
        return User::factory()->create(['email' => 'lionel@example.com', 'password' => self::PASSWORD]);
    }

    private function login(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/app/login', array_merge(['email' => 'lionel@example.com', 'password' => self::PASSWORD, 'device_name' => 'Celular de Lionel', 'platform' => 'android'], $extra));
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('lionel@example.com|127.0.0.1');
    }

    public function test_sin_token_no_se_puede_usar_la_api_de_las_apps(): void
    {
        $this->getJson('/api/app/clients')->assertUnauthorized();
        $this->getJson('/api/app/clients', $this->bearer('lmc_inventado'))->assertUnauthorized();
    }

    public function test_login_entrega_un_token_que_sirve_y_se_guarda_solo_el_hash(): void
    {
        $this->user();

        $response = $this->login()->assertOk()->assertJsonPath('data.email', 'lionel@example.com');
        $token = $response->json('token');

        $this->assertStringStartsWith('lmc_', $token);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $token]);
        $this->assertDatabaseHas('api_tokens', ['token_hash' => ApiToken::hash($token), 'name' => 'Celular de Lionel', 'platform' => 'android']);

        Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $this->getJson('/api/app/clients', $this->bearer($token))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/app/user', $this->bearer($token))->assertOk()->assertJsonPath('data.email', 'lionel@example.com');
    }

    public function test_puede_escribir_sin_cookie_ni_csrf(): void
    {
        $this->user();
        $token = $this->login()->json('token');

        $this->postJson('/api/app/clients', ['first_name' => 'Leo', 'last_name' => 'Paz'], $this->bearer($token))->assertCreated();
        $this->assertDatabaseHas('audit_logs', ['event' => 'client.created']);
    }

    public function test_credenciales_incorrectas_no_dan_token(): void
    {
        $this->user();

        $this->login(['password' => 'otra-clave'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_pide_nombre_de_dispositivo_y_plataforma_valida(): void
    {
        $this->user();

        $this->postJson('/api/app/login', ['email' => 'lionel@example.com', 'password' => self::PASSWORD])->assertUnprocessable()->assertJsonValidationErrors('device_name');
        $this->login(['platform' => 'windows'])->assertUnprocessable()->assertJsonValidationErrors('platform');
    }

    public function test_con_verificacion_en_dos_pasos_primero_da_un_desafio(): void
    {
        $user = $this->user();
        $secret = Totp::generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $first = $this->login()->assertStatus(202)->assertJsonPath('two_factor', true);
        $this->assertNull($first->json('token'));
        $this->assertDatabaseCount('api_tokens', 0);

        $challenge = $first->json('challenge');

        $this->postJson('/api/app/login/two-factor', ['challenge' => $challenge, 'code' => '000000', 'device_name' => 'PC', 'platform' => 'linux'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->postJson('/api/app/login/two-factor', ['challenge' => $challenge, 'code' => Totp::code($secret), 'device_name' => 'PC', 'platform' => 'linux'])
            ->assertOk()->assertJsonStructure(['token', 'expires_at']);

        // El desafío se usa una sola vez.
        $this->postJson('/api/app/login/two-factor', ['challenge' => $challenge, 'code' => Totp::code($secret), 'device_name' => 'PC'])->assertUnauthorized();
    }

    public function test_un_desafio_inventado_no_sirve(): void
    {
        $this->postJson('/api/app/login/two-factor', ['challenge' => str_repeat('a', 48), 'code' => '123456', 'device_name' => 'PC'])->assertUnauthorized();
    }

    public function test_cerrar_sesion_invalida_el_token(): void
    {
        $this->user();
        $token = $this->login()->json('token');

        $this->postJson('/api/app/logout', [], $this->bearer($token))->assertNoContent();

        $this->getJson('/api/app/clients', $this->bearer($token))->assertUnauthorized();
        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_un_token_vencido_no_sirve_y_usarlo_renueva_el_plazo(): void
    {
        $user = $this->user();
        $token = $this->login()->json('token');
        $row = ApiToken::where('user_id', $user->id)->first();

        $row->forceFill(['last_used_at' => now()->subDay(), 'expires_at' => now()->addDay()])->save();
        $this->getJson('/api/app/clients', $this->bearer($token))->assertOk();
        $this->assertTrue($row->fresh()->expires_at->greaterThan(now()->addDays(80)));

        $row->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->getJson('/api/app/clients', $this->bearer($token))->assertUnauthorized();
    }

    public function test_lista_y_desconecta_dispositivos(): void
    {
        $this->user();
        $phone = $this->login()->json('token');
        $pc = $this->login(['device_name' => 'PC de escritorio', 'platform' => 'linux'])->json('token');

        $devices = $this->getJson('/api/app/account/devices', $this->bearer($pc))->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->assertSame(1, collect($devices)->where('current', true)->count());
        $this->assertSame('PC de escritorio', collect($devices)->firstWhere('current', true)['name']);

        $phoneId = collect($devices)->firstWhere('name', 'Celular de Lionel')['id'];
        $this->deleteJson("/api/app/account/devices/{$phoneId}", [], $this->bearer($pc))->assertNoContent();

        $this->getJson('/api/app/clients', $this->bearer($phone))->assertUnauthorized();
        $this->getJson('/api/app/clients', $this->bearer($pc))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'device.revoked']);
    }

    public function test_no_se_pueden_desconectar_dispositivos_de_otra_cuenta(): void
    {
        $this->user();
        $mine = $this->login()->json('token');

        $other = User::factory()->create();
        [, $foreign] = ApiToken::issue($other, 'Ajeno', 'android');

        $this->deleteJson("/api/app/account/devices/{$foreign->id}", [], $this->bearer($mine))->assertNotFound();
        $this->assertDatabaseHas('api_tokens', ['id' => $foreign->id]);
    }

    public function test_cambiar_la_contrasena_desconecta_los_otros_dispositivos(): void
    {
        $this->user();
        $phone = $this->login()->json('token');
        $pc = $this->login(['device_name' => 'PC', 'platform' => 'linux'])->json('token');

        $this->putJson('/api/app/account/password', [
            'current_password' => self::PASSWORD,
            'password' => 'Nueva-Clave#2026x',
            'password_confirmation' => 'Nueva-Clave#2026x',
        ], $this->bearer($pc))->assertNoContent();

        $this->getJson('/api/app/clients', $this->bearer($pc))->assertOk();
        $this->getJson('/api/app/clients', $this->bearer($phone))->assertUnauthorized();
    }

    public function test_el_login_del_navegador_sigue_funcionando_y_no_sirve_el_token_ahi(): void
    {
        $this->user();
        $token = $this->login()->json('token');

        // /api/clients es la ruta del navegador (cookie): un token no la abre.
        $this->getJson('/api/clients', $this->bearer($token))->assertUnauthorized();
    }
}
