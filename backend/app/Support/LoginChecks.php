<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Verificaciones de acceso compartidas por el login con cookie (web) y con token (apps). */
class LoginChecks
{
    public const MAX_ATTEMPTS = 5;

    /** Hash de relleno (bcrypt, costo 12) para que un correo inexistente tarde lo mismo que uno real. */
    private const DUMMY_HASH = '$2y$12$mtiknmxcDolCbhE4.pkTKubiunpmi6yZhv5vw0ZO5xLGlsDzKLht6';

    /** Valida correo y contraseña con límite de intentos. Devuelve el usuario o lanza un error de validación. */
    public static function credentials(Request $request): User
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $email = Str::lower($credentials['email']);
        $throttleKey = $email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            AuditLog::record('auth.lockout', null, ['email' => $email]);

            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Esperá '.ceil(RateLimiter::availableIn($throttleKey) / 60).' minuto(s) e intentá de nuevo.',
            ])->status(429);
        }

        $user = User::where('email', $email)->first();

        // Siempre se calcula un hash (contra uno de relleno si el correo no existe)
        // para no revelar qué cuentas existen por el tiempo de respuesta.
        $matches = Hash::check($credentials['password'], $user?->password ?? self::DUMMY_HASH);

        if (! $user || ! $matches) {
            RateLimiter::hit($throttleKey, 15 * 60);
            AuditLog::record('auth.failed', null, ['email' => $email]);

            // Mismo mensaje exista o no el correo: no revela qué cuentas existen.
            throw ValidationException::withMessages(['email' => 'Las credenciales no son correctas.']);
        }

        RateLimiter::clear($throttleKey);

        return $user;
    }

    /**
     * Verifica el segundo paso (código de la app o de recuperación) con límite de intentos.
     * Lanza un error de validación (429 si hay demasiados intentos).
     *
     * @param  array{code?: ?string, recovery_code?: ?string}  $data
     */
    public static function secondFactor(Request $request, ?User $user, array $data): void
    {
        $throttleKey = 'two-factor|'.($user?->id).'|'.$request->ip();

        if (! $user || RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            AuditLog::record('auth.lockout', null, ['email' => $user?->email, 'step' => 'two_factor']);

            throw ValidationException::withMessages(['code' => 'Demasiados intentos. Volvé a empezar en unos minutos.'])->status(429);
        }

        $ok = false;

        if (! empty($data['code'])) {
            $step = Totp::verify($user->two_factor_secret, $data['code'], 1, $user->two_factor_last_step);

            if ($step !== null) {
                $user->forceFill(['two_factor_last_step' => $step])->save();
                $ok = true;
            }
        } elseif (! empty($data['recovery_code']) && $user->consumeRecoveryCode($data['recovery_code'])) {
            $ok = true;
            AuditLog::record('auth.recovery_code_used', null, [], $user->id);
        }

        if (! $ok) {
            RateLimiter::hit($throttleKey, 15 * 60);
            AuditLog::record('auth.two_factor_failed', null, ['email' => $user->email], $user->id);

            throw ValidationException::withMessages(['code' => 'El código no es correcto.']);
        }

        RateLimiter::clear($throttleKey);
    }
}
