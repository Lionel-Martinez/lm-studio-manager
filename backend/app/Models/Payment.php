<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = ['client_id', 'project_id', 'concept', 'amount', 'due_date', 'paid_at', 'method', 'notes'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /** paid | overdue | pending, calculado a partir de las fechas. */
    public function getStatusAttribute(): string
    {
        if ($this->paid_at) {
            return 'paid';
        }

        return $this->due_date->lt(today()) ? 'overdue' : 'pending';
    }

    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'paid' => $query->whereNotNull('paid_at'),
            'pending' => $query->whereNull('paid_at')->whereDate('due_date', '>=', today()),
            'overdue' => $query->whereNull('paid_at')->whereDate('due_date', '<', today()),
            default => $query,
        };
    }
}
