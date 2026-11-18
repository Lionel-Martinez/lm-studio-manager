<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Support\LoginChecks;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const PENDING_KEY = 'two_factor.pending';

    public function login(Request $request): JsonResponse
    {
        $user = LoginChecks::credentials($request);

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

        try {
            LoginChecks::secondFactor($request, User::find($pending['user_id']), $data);
        } catch (ValidationException $e) {
            // Con demasiados intentos hay que empezar de nuevo con la contraseña.
            if ($e->status === 429) {
                $request->session()->forget(self::PENDING_KEY);
            }

            throw $e;
        }

        $request->session()->forget(self::PENDING_KEY);

        return $this->completeLogin($request, User::findOrFail($pending['user_id']));
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
