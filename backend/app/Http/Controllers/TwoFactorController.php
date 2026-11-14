<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Activar y desactivar la verificación en dos pasos de la cuenta. */
class TwoFactorController extends Controller
{
    /** Genera un secreto nuevo (todavía sin activar) para escanear o escribir en la app. */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['code' => 'La verificación en dos pasos ya está activada.']);
        }

        $secret = Totp::generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        return response()->json(['data' => [
            'secret' => $secret,
            'uri' => Totp::uri($secret, $user->email, config('app.name')),
        ]]);
    }

    /** Activa el segundo paso si el código de la app es correcto. Devuelve los códigos de recuperación. */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'current_password'],
            'code' => ['required', 'string', 'max:20'],
        ], ['password.current_password' => 'La contraseña no es correcta.']);

        $user = $request->user();

        if ($user->two_factor_secret === null || $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['code' => 'Primero generá la clave para tu app.']);
        }

        $step = Totp::verify($user->two_factor_secret, $data['code']);

        if ($step === null) {
            throw ValidationException::withMessages(['code' => 'El código no es correcto. Revisá que la hora de tu teléfono sea automática.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step])->save();
        $codes = $user->issueRecoveryCodes();

        AuditLog::record('auth.two_factor_enabled');

        return response()->json(['data' => ['recovery_codes' => $codes]]);
    }

    /** Desactiva el segundo paso. Exige la contraseña. */
    public function disable(Request $request): Response
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ], ['password.current_password' => 'La contraseña no es correcta.']);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_step' => null,
        ])->save();

        AuditLog::record('auth.two_factor_disabled');

        return response()->noContent();
    }

    /** Genera códigos de recuperación nuevos (los anteriores dejan de servir). */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ], ['password.current_password' => 'La contraseña no es correcta.']);

        abort_unless($request->user()->hasTwoFactorEnabled(), 422, 'La verificación en dos pasos no está activada.');

        $codes = $request->user()->issueRecoveryCodes();

        AuditLog::record('auth.recovery_codes_regenerated');

        return response()->json(['data' => ['recovery_codes' => $codes]]);
    }
}
