<?php

namespace App\Models;

use App\Enums\PaymentMode;
use App\Enums\ProjectStatus;
use App\Enums\ServiceType;
use App\Enums\TrialUnit;
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
        'total_price',
        'payment_mode',
        'deposit_percent',
        'installments_count',
        'plan_start_date',
        'monthly_fee',
        'monthly_months',
        'free_months',
        'trial_value',
        'trial_unit',
    ];

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'status' => ProjectStatus::class,
            'entry_date' => 'date',
            'estimated_start_date' => 'date',
            'due_date' => 'date',
            'total_price' => 'decimal:2',
            'monthly_fee' => 'decimal:2',
            'payment_mode' => PaymentMode::class,
            'trial_unit' => TrialUnit::class,
            'plan_start_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
