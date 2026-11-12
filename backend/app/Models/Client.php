<?php

namespace App\Models;

use App\Enums\ClientType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'company_name',
        'tax_id',
        'email',
        'phone',
        'whatsapp',
        'website',
        'country_id',
        'region_id',
        'locality_id',
        'first_contact_at',
        'source',
        'client_type',
        'commercial_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'first_contact_at' => 'date',
            'client_type' => ClientType::class,
        ];
    }

    /** Nombre para mostrar: el negocio si tiene, o la persona. */
    public function getDisplayNameAttribute(): string
    {
        return $this->company_name ?: trim("{$this->first_name} {$this->last_name}");
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
