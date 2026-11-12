<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

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

        if (! Auth::attempt(['email' => $email, 'password' => $credentials['password']])) {
            RateLimiter::hit($throttleKey, 15 * 60);
            AuditLog::record('auth.failed', null, ['email' => $email]);

            // Mismo mensaje exista o no el correo: no revela qué cuentas existen.
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Nueva sesión al entrar: evita la fijación de sesión.
        $request->session()->regenerate();

        AuditLog::record('auth.login');

        return response()->json(['data' => $this->present($request->user())]);
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

    /** @return array{id:int,name:string,email:string} */
    private function present(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
    }
}
