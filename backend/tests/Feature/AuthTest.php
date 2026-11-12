<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG = 'Nueva-Clave#2026x';

    private function user(): User
    {
        return User::factory()->create(['email' => 'lionel@example.com', 'password' => 'Clave-Segura#1234']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('lionel@example.com|127.0.0.1');
    }

    public function test_la_api_rechaza_a_quien_no_inicio_sesion(): void
    {
        $this->getJson('/api/clients')->assertUnauthorized();
        $this->postJson('/api/clients', ['first_name' => 'A', 'last_name' => 'B'])->assertUnauthorized();
        $this->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/account/activity')->assertUnauthorized();
    }

    public function test_inicia_sesion_con_credenciales_correctas(): void
    {
        $this->user();

        $this->postJson('/api/login', ['email' => 'LIONEL@example.com', 'password' => 'Clave-Segura#1234'])
            ->assertOk()
            ->assertJsonPath('data.email', 'lionel@example.com')
            ->assertJsonMissingPath('data.password');

        $this->getJson('/api/clients')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login']);
    }

    public function test_rechaza_credenciales_incorrectas_con_el_mismo_mensaje(): void
    {
        $this->user();

        $wrongPassword = $this->postJson('/api/login', ['email' => 'lionel@example.com', 'password' => 'incorrecta']);
        $unknownEmail = $this->postJson('/api/login', ['email' => 'otro@example.com', 'password' => 'incorrecta']);

        $wrongPassword->assertUnprocessable();
        $this->assertSame($wrongPassword->json('errors.email'), $unknownEmail->json('errors.email'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.failed']);
    }

    public function test_bloquea_despues_de_cinco_intentos_fallidos(): void
    {
        $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'lionel@example.com', 'password' => 'incorrecta'])->assertUnprocessable();
        }

        // Aun con la clave correcta, queda bloqueado.
        $this->postJson('/api/login', ['email' => 'lionel@example.com', 'password' => 'Clave-Segura#1234'])
            ->assertStatus(429);
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.lockout']);
    }

    public function test_cierra_sesion(): void
    {
        $this->actingAs($this->user());

        $this->postJson('/api/logout')->assertNoContent();

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.logout']);
    }

    public function test_cambia_la_contrasena(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->putJson('/api/account/password', [
            'current_password' => 'Clave-Segura#1234',
            'password' => self::STRONG,
            'password_confirmation' => self::STRONG,
        ])->assertNoContent();

        $this->assertTrue(Hash::check(self::STRONG, $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.password_changed']);
    }

    public function test_el_cambio_de_contrasena_exige_la_actual_y_una_clave_fuerte(): void
    {
        $this->actingAs($this->user());

        $this->putJson('/api/account/password', [
            'current_password' => 'equivocada',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertUnprocessable()->assertJsonValidationErrors(['current_password', 'password']);
    }

    public function test_no_hay_registro_publico(): void
    {
        $this->postJson('/api/register', ['email' => 'x@example.com', 'password' => self::STRONG])->assertNotFound();
    }

    public function test_la_actividad_de_la_cuenta_se_puede_consultar(): void
    {
        $this->actingAs($this->user());
        \App\Models\AuditLog::record('auth.login');

        $this->getJson('/api/account/activity')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Inicio de sesión');
    }

    public function test_las_respuestas_llevan_cabeceras_de_seguridad(): void
    {
        $response = $this->getJson('/api/clients');

        $response->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
