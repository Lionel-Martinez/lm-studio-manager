<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'client_name' => $this->client?->display_name,
            'project_id' => $this->project_id,
            'project_name' => $this->project ? ($this->project->name ?: $this->project->service_type->label()) : null,
            'concept' => $this->concept,
            'amount' => (float) $this->amount,
            'due_date' => $this->due_date->toDateString(),
            'paid_at' => $this->paid_at?->toDateString(),
            'status' => $this->status,
            'method' => $this->method,
            'notes' => $this->notes,
            'receipt_id' => $this->whenLoaded('receipt', fn () => $this->receipt?->id),
            'receipt_code' => $this->whenLoaded('receipt', fn () => $this->receipt?->code),
            'receipt_voided' => $this->whenLoaded('receipt', fn () => $this->receipt?->isVoided()),
        ];
    }
}
