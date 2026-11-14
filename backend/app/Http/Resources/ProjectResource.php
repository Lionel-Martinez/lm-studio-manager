<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Project */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'service_type' => $this->service_type->value,
            'service_type_label' => $this->service_type->label(),
            'name' => $this->name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'entry_date' => $this->entry_date?->toDateString(),
            'estimated_start_date' => $this->estimated_start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'delay_reason' => $this->delay_reason,
            'total_price' => $this->total_price !== null ? (float) $this->total_price : null,
            'payment_mode' => $this->payment_mode?->value,
            'payment_mode_label' => $this->payment_mode?->label(),
            'deposit_percent' => $this->deposit_percent,
            'installments_count' => $this->installments_count,
            'plan_start_date' => $this->plan_start_date?->toDateString(),
            'monthly_fee' => $this->monthly_fee !== null ? (float) $this->monthly_fee : null,
            'monthly_months' => $this->monthly_months,
            'free_months' => (int) $this->free_months,
            'trial_value' => (int) $this->trial_value,
            'trial_unit' => $this->trial_unit?->value,
        ];
    }
}
