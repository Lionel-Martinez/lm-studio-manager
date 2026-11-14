<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Support\Totp;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    /** Hash de relleno (bcrypt, costo 12) para que un correo inexistente tarde lo mismo que uno real. */
    private const DUMMY_HASH = '$2y$12$mtiknmxcDolCbhE4.pkTKubiunpmi6yZhv5vw0ZO5xLGlsDzKLht6';

    private const PENDING_KEY = 'two_factor.pending';

    public function login(Request $request): JsonResponse
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
        $valid = $user !== null && $matches;

        if (! $user || ! $valid) {
            RateLimiter::hit($throttleKey, 15 * 60);
            AuditLog::record('auth.failed', null, ['email' => $email]);

            // Mismo mensaje exista o no el correo: no revela qué cuentas existen.
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        if ($user->hasTwoFactorEnabled()) {
            // Contraseña correcta, pero falta el segundo paso: todavía NO se inicia sesión.
            $request->session()->regenerate();
            $request->session()->put(self::PENDING_KEY, ['user_id' => $user->id, 'expires_at' => now()->addMinutes(5)->timestamp]);

            return response()->json(['two_factor' => true], 202);
        }

        return $this->completeLogin($request, $user);
    }

    /** Segundo paso del login: código de la app o código de recuperación. */
    public function twoFactorChallenge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:20', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:30', 'required_without:code'],
        ]);

        $pending = $request->session()->get(self::PENDING_KEY);

        if (! $pending || $pending['expires_at'] < now()->timestamp) {
            $request->session()->forget(self::PENDING_KEY);

            throw ValidationException::withMessages(['code' => 'La verificación venció. Volvé a ingresar tu correo y contraseña.'])->status(401);
        }

        $user = User::find($pending['user_id']);
        $throttleKey = 'two-factor|'.$pending['user_id'].'|'.$request->ip();

        if (! $user || RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            AuditLog::record('auth.lockout', null, ['email' => $user?->email, 'step' => 'two_factor']);
            $request->session()->forget(self::PENDING_KEY);

            throw ValidationException::withMessages(['code' => 'Demasiados intentos. Volvé a empezar en unos minutos.'])->status(429);
        }

        $ok = false;

        if (! empty($data['code'])) {
            $step = Totp::verify($user->two_factor_secret, $data['code'], 1, $user->two_factor_last_step);

            if ($step !== null) {
                $user->forceFill(['two_factor_last_step' => $step])->save();
                $ok = true;
            }
        } elseif ($user->consumeRecoveryCode($data['recovery_code'])) {
            $ok = true;
            AuditLog::record('auth.recovery_code_used', null, [], $user->id);
        }

        if (! $ok) {
            RateLimiter::hit($throttleKey, 15 * 60);
            AuditLog::record('auth.two_factor_failed', null, ['email' => $user->email], $user->id);

            throw ValidationException::withMessages(['code' => 'El código no es correcto.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->forget(self::PENDING_KEY);

        return $this->completeLogin($request, $user);
    }

    private function completeLogin(Request $request, User $user): JsonResponse
    {
        Auth::login($user);

        // Nueva sesión al entrar: evita la fijación de sesión.
        $request->session()->regenerate();

        AuditLog::record('auth.login', null, [], $user->id);

        return response()->json(['data' => $this->present($user)]);
    }

    public function logout(Request $request): Response
    {
        AuditLog::record('auth.logout');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present($request->user())]);
    }

    /** @return array{id:int,name:string,email:string,two_factor_enabled:bool} */
    private function present(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'two_factor_enabled' => $user->hasTwoFactorEnabled()];
    }
}
