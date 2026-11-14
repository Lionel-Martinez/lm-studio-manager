<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    protected $fillable = [
        'payment_id', 'number', 'client_name', 'client_tax_id', 'concept', 'project_name',
        'amount', 'paid_at', 'method', 'issued_at', 'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class)->withTrashed();
    }

    public function getCodeAttribute(): string
    {
        return 'C-'.str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
