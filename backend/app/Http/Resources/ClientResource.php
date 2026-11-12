<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Client */
class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'company_name' => $this->company_name,
            'tax_id' => $this->tax_id,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'website' => $this->website,
            'client_type' => $this->client_type?->value,
            'commercial_status' => $this->commercial_status,
            'first_contact_at' => $this->first_contact_at?->toDateString(),
            'source' => $this->source,
            'notes' => $this->notes,
            'country_id' => $this->country_id,
            'region_id' => $this->region_id,
            'locality_id' => $this->locality_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'projects' => ProjectResource::collection($this->whenLoaded('projects')),
        ];
    }
}
