<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function updatePassword(Request $request): Response
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ], [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $data['password']])->save();

        // Cierra las demás sesiones abiertas (otros navegadores) y los demás dispositivos con la app.
        if ($request->hasSession()) {
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $request->session()->getId())
                    ->delete();
            }
            $request->session()->regenerate();
        }

        // Con la app, el dispositivo actual sigue conectado; con el navegador se cierran todos.
        $user->apiTokens()->when($request->attributes->get('api_token'), fn ($q, $current) => $q->where('id', '!=', $current->id))->delete();

        AuditLog::record('auth.password_changed');

        return response()->noContent();
    }

    /** Dispositivos con la app instalada que tienen la sesión abierta. */
    public function devices(Request $request): JsonResponse
    {
        $current = $request->attributes->get('api_token')?->id;

        $devices = $request->user()->apiTokens()->orderByDesc('last_used_at')->get()->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'platform' => $t->platform,
            'last_used_at' => $t->last_used_at?->toIso8601String(),
            'created_at' => $t->created_at->toIso8601String(),
            'current' => $t->id === $current,
        ]);

        return response()->json(['data' => $devices]);
    }

    /** Cierra la sesión de un dispositivo (por ejemplo, un celular perdido). */
    public function revokeDevice(Request $request, int $device): Response
    {
        $token = $request->user()->apiTokens()->findOrFail($device);

        AuditLog::record('device.revoked', null, ['device' => $token->name]);
        $token->delete();

        return response()->noContent();
    }

    /** Últimos movimientos de la cuenta: accesos, intentos fallidos y cambios de datos. */
    public function activity(Request $request): JsonResponse
    {
        $email = strtolower($request->user()->email);

        $logs = AuditLog::query()
            ->where(function ($query) use ($request, $email) {
                $query->where('user_id', $request->user()->id)
                    ->orWhere(fn ($q) => $q->whereNull('user_id')->where('metadata->email', $email));
            })
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'label' => self::LABELS[$log->event] ?? $log->event,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $logs]);
    }

    private const LABELS = [
        'auth.login' => 'Inicio de sesión',
        'auth.logout' => 'Cierre de sesión',
        'auth.failed' => 'Intento de acceso fallido',
        'auth.lockout' => 'Acceso bloqueado por demasiados intentos',
        'auth.password_changed' => 'Cambio de contraseña',
        'auth.two_factor_enabled' => 'Verificación en dos pasos activada',
        'auth.two_factor_disabled' => 'Verificación en dos pasos desactivada',
        'auth.two_factor_failed' => 'Código de verificación incorrecto',
        'auth.recovery_code_used' => 'Se usó un código de recuperación',
        'device.revoked' => 'Dispositivo desconectado',
        'auth.recovery_codes_regenerated' => 'Códigos de recuperación renovados',
        'client.created' => 'Cliente creado',
        'client.updated' => 'Cliente modificado',
        'client.deleted' => 'Cliente eliminado',
        'export.clients' => 'Exportación de clientes (CSV)',
        'export.projects' => 'Exportación de proyectos (CSV)',
        'export.payments' => 'Exportación de pagos (CSV)',
        'backup.created' => 'Copia de seguridad creada',
        'note.created' => 'Nota creada',
        'note.updated' => 'Nota modificada',
        'note.deleted' => 'Nota eliminada',
        'client.deactivated' => 'Cliente desactivado',
        'client.reactivated' => 'Cliente reactivado',
        'reminder.created' => 'Recordatorio creado',
        'reminder.updated' => 'Recordatorio modificado',
        'reminder.deleted' => 'Recordatorio eliminado',
        'receipt.issued' => 'Comprobante emitido',
        'receipt.voided' => 'Comprobante anulado',
        'payment.created' => 'Pago registrado',
        'payment.updated' => 'Pago modificado',
        'payment.deleted' => 'Pago eliminado',
        'project.created' => 'Proyecto creado',
        'project.updated' => 'Proyecto modificado',
        'project.deleted' => 'Proyecto eliminado',
        'project.pricing' => 'Precio y plan de pago del proyecto',
        'user.created' => 'Usuario creado',
    ];
}
