<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'service_type',
        'name',
        'status',
        'entry_date',
        'estimated_start_date',
        'due_date',
        'delay_reason',
    ];

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'status' => ProjectStatus::class,
            'entry_date' => 'date',
            'estimated_start_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
