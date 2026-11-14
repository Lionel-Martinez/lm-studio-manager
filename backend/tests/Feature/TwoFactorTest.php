<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Clave-Segura#1234';

    private function user(): User
    {
        return User::factory()->create(['email' => 'lionel@example.com', 'password' => self::PASSWORD]);
    }

    /** Activa el segundo paso para el usuario autenticado y devuelve [secreto, códigos de recuperación, código usado]. */
    private function enableTwoFactor(User $user): array
    {
        $this->actingAs($user);

        $secret = $this->postJson('/api/account/two-factor/setup')->assertOk()->json('data.secret');

        $used = Totp::code($secret);

        $codes = $this->postJson('/api/account/two-factor/confirm', [
            'password' => self::PASSWORD,
            'code' => $used,
        ])->assertOk()->json('data.recovery_codes');

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return [$secret, $codes, $used];
    }

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('lionel@example.com|127.0.0.1');
    }

    public function test_el_codigo_totp_coincide_con_los_valores_del_rfc_6238(): void
    {
        $secret = Totp::base32Encode('12345678901234567890');

        $this->assertSame('287082', Totp::code($secret, 59));
        $this->assertSame('081804', Totp::code($secret, 1111111109));
        $this->assertSame('005924', Totp::code($secret, 1234567890));
    }

    public function test_un_codigo_usado_no_se_acepta_dos_veces(): void
    {
        $secret = Totp::generateSecret();
        $step = Totp::verify($secret, Totp::code($secret));

        $this->assertNotNull($step);
        $this->assertNull(Totp::verify($secret, Totp::code($secret), 1, $step));
        $this->assertNull(Totp::verify($secret, '12345'));
    }

    public function test_activa_la_verificacion_con_un_codigo_valido(): void
    {
        $user = $this->user();
        [, $codes] = $this->enableTwoFactor($user);

        $this->assertCount(8, $codes);
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.two_factor_enabled']);
    }

    public function test_no_se_activa_con_un_codigo_o_contrasena_incorrectos(): void
    {
        $this->actingAs($this->user());
        $this->postJson('/api/account/two-factor/setup')->assertOk();

        $this->postJson('/api/account/two-factor/confirm', ['password' => self::PASSWORD, 'code' => '000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/account/two-factor/confirm', ['password' => 'mala', 'code' => '123456'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_con_dos_pasos_la_contrasena_sola_no_inicia_sesion(): void
    {
        $user = $this->user();
        [$secret] = $this->enableTwoFactor($user);

        $this->postJson('/api/login', ['email' => 'lionel@example.com', 'password' => self::PASSWORD])
            ->assertStatus(202)->assertJsonPath('two_factor', true);

        $this->assertGuest();
        $this->getJson('/api/clients')->assertUnauthorized();

        $this->postJson('/api/login/two-factor', ['code' => Totp::code($secret)])
            ->assertOk()->assertJsonPath('data.two_factor_enabled', true);

        $this->assertAuthenticatedAs($user);
        $this->getJson('/api/clients')->assertOk();
    }

    public function test_rechaza_un_codigo_incorrecto_y_bloquea_tras_cinco_intentos(): void
    {
        $this->enableTwoFactor($this->user());
        $this->postJson('/api/login', ['email' => 'lionel@example.com', 'password' => self::PASSWORD])->assertStatus(202);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login/two-factor', ['code' => '000000'])->assertUnprocessable();
        }

        $this->postJson('/api/login/two-factor', ['code' => '000000'])->assertStatus(429);
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.two_factor_failed']);
    }

    public function test_sin_haber_pasado_la_contrasena_no_se_puede_usar_el_segundo_paso(): void
    {
        $this->postJson('/api/login/two-factor', ['code' => '123456'])->assertStatus(401);
        $this->assertGuest();
    }

    public function test_un_codigo_de_recuperacion_sirve_una_sola_vez(): void
    {
        $user = $this->user();
        [, $codes] = $this->enableTwoFactor($user);

        $this->postJson('/api/login', ['email' => 'lionel@example.com', 'password' => self::PASSWORD])->assertStatus(202);
        $this->postJson('/api/login/two-factor', ['recovery_code' => $codes[0]])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.recovery_code_used']);

        $this->postJson('/api/logout');
        $this->postJson('/api/login', ['email' => 'lionel@example.com', 'password' => self::PASSWORD])->assertStatus(202);
        $this->postJson('/api/login/two-factor', ['recovery_code' => $codes[0]])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_el_mismo_codigo_de_la_app_no_sirve_dos_veces(): void
    {
        [, , $code] = $this->enableTwoFactor($this->user());

        // El código usado para activar ya quedó gastado: no sirve para entrar.
        $this->postJson('/api/login', ['email' => 'lionel@example.com', 'password' => self::PASSWORD])->assertStatus(202);
        $this->postJson('/api/login/two-factor', ['code' => $code])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_se_desactiva_con_la_contrasena(): void
    {
        $user = $this->user();
        $this->enableTwoFactor($user);
        $this->actingAs($user->fresh());

        $this->deleteJson('/api/account/two-factor', ['password' => 'mala'])->assertUnprocessable();
        $this->deleteJson('/api/account/two-factor', ['password' => self::PASSWORD])->assertNoContent();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_el_secreto_no_se_guarda_en_claro_ni_se_expone(): void
    {
        $user = $this->user();
        [$secret] = $this->enableTwoFactor($user);

        $raw = DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $this->assertNotSame($secret, $raw);
        $this->assertStringNotContainsString('two_factor_secret', json_encode($user->fresh()->toArray()));
    }
}
