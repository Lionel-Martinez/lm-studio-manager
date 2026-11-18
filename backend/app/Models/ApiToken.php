<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Token de acceso de un dispositivo (las apps nativas no usan cookies). */
class ApiToken extends Model
{
    /** Días sin usar el dispositivo antes de que el token venza. */
    public const LIFETIME_DAYS = 90;

    protected $fillable = ['user_id', 'name', 'platform', 'token_hash', 'last_used_at', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** Crea un token nuevo. El valor en claro se devuelve una sola vez: en la base queda solo su hash. */
    public static function issue(User $user, string $name, ?string $platform): array
    {
        $plain = 'lmc_'.Str::random(60);

        $token = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'platform' => $platform,
            'token_hash' => static::hash($plain),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(self::LIFETIME_DAYS),
        ]);

        return [$plain, $token];
    }
}
