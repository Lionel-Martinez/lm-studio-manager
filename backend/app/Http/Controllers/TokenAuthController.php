<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\LoginChecks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Acceso de las apps nativas (escritorio y celular): devuelve un token por dispositivo en vez de una cookie. */
class TokenAuthController extends Controller
{
    private const CHALLENGE_PREFIX = 'app-login:';

    public function login(Request $request): JsonResponse
    {
        $device = $request->validate($this->deviceRules());

        $user = LoginChecks::credentials($request);

        if ($user->hasTwoFactorEnabled()) {
            // Falta el segundo paso. El "desafío" vale 5 minutos y se usa una sola vez.
            $challenge = Str::random(48);
            Cache::put(self::CHALLENGE_PREFIX.ApiToken::hash($challenge), $user->id, now()->addMinutes(5));

            return response()->json(['two_factor' => true, 'challenge' => $challenge], 202);
        }

        return $this->issue($user, $device);
    }

    public function twoFactorChallenge(Request $request): JsonResponse
    {
        $data = $request->validate($this->deviceRules() + [
            'challenge' => ['required', 'string', 'size:48'],
            'code' => ['nullable', 'string', 'max:20', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:30', 'required_without:code'],
        ]);

        $cacheKey = self::CHALLENGE_PREFIX.ApiToken::hash($data['challenge']);
        $userId = Cache::get($cacheKey);

        if (! $userId) {
            throw ValidationException::withMessages(['code' => 'La verificación venció. Volvé a ingresar tu correo y contraseña.'])->status(401);
        }

        try {
            LoginChecks::secondFactor($request, User::find($userId), $data);
        } catch (ValidationException $e) {
            // Con demasiados intentos hay que empezar de nuevo con la contraseña.
            if ($e->status === 429) {
                Cache::forget($cacheKey);
            }

            throw $e;
        }

        Cache::forget($cacheKey);

        return $this->issue(User::findOrFail($userId), $data);
    }

    /** Cierra la sesión de este dispositivo: el token deja de servir. */
    public function logout(Request $request): Response
    {
        AuditLog::record('auth.logout');

        $request->attributes->get('api_token')?->delete();

        return response()->noContent();
    }

    private function issue(User $user, array $device): JsonResponse
    {
        [$plain, $token] = ApiToken::issue($user, $device['device_name'], $device['platform'] ?? null);

        AuditLog::record('auth.login', null, ['device' => $token->name, 'platform' => $token->platform], $user->id);

        return response()->json([
            'data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'two_factor_enabled' => $user->hasTwoFactorEnabled()],
            'token' => $plain,
            'expires_at' => $token->expires_at->toIso8601String(),
        ]);
    }

    private function deviceRules(): array
    {
        return [
            'device_name' => ['required', 'string', 'max:60'],
            'platform' => ['nullable', 'string', 'in:linux,android,ios,other'],
        ];
    }
}
