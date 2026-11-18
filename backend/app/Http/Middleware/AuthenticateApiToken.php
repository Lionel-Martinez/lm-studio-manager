<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica las peticiones de las apps nativas con "Authorization: Bearer <token>".
 * No usa sesión ni cookies, así que tampoco hay CSRF que verificar.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        $token = $plain ? ApiToken::with('user')->where('token_hash', ApiToken::hash($plain))->first() : null;

        if (! $token || ! $token->user || $token->expires_at->isPast()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Vence a los 90 días sin uso; cada uso lo renueva (se escribe a lo sumo cada 5 minutos).
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at' => now(), 'expires_at' => now()->addDays(ApiToken::LIFETIME_DAYS)])->saveQuietly();
        }

        Auth::guard('web')->setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
