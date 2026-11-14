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

        // Cierra las demás sesiones abiertas (otros navegadores o dispositivos).
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }
        $request->session()->regenerate();

        AuditLog::record('auth.password_changed');

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
        'receipt.issued' => 'Comprobante emitido',
        'receipt.voided' => 'Comprobante anulado',
        'payment.created' => 'Pago registrado',
        'payment.updated' => 'Pago modificado',
        'payment.deleted' => 'Pago eliminado',
        'project.created' => 'Proyecto creado',
        'project.updated' => 'Proyecto modificado',
        'project.deleted' => 'Proyecto eliminado',
        'user.created' => 'Usuario creado',
    ];
}
