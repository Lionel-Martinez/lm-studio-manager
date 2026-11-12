<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Registro de acciones sensibles: inicios de sesión, cambios de contraseña
 * y altas, cambios y bajas de datos. Nunca guarda contraseñas.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  array<string, mixed>  $metadata  Datos de contexto; no incluir secretos.
     * @param  int|null  $userId  Por defecto, el usuario autenticado.
     */
    public static function record(string $event, ?Model $subject = null, array $metadata = [], ?int $userId = null): self
    {
        $request = request();

        return static::create([
            'user_id' => $userId ?? Auth::id(),
            'event' => $event,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? Str::limit($request->userAgent(), 250, '') : null,
            'metadata' => $metadata ?: null,
        ]);
    }
}
