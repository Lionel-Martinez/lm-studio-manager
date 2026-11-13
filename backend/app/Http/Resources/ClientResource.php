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
            'commercial_status' => $this->commercial_status?->value,
            'commercial_status_label' => $this->commercial_status?->label(),
            'first_contact_at' => $this->first_contact_at?->toDateString(),
            'source' => $this->source,
            'notes' => $this->notes,
            'country_id' => $this->country_id,
            'region_id' => $this->region_id,
            'locality_id' => $this->locality_id,
            'country_name' => $this->whenLoaded('country', fn () => $this->country?->name),
            // region_id guarda el lugar más específico: departamento si lo hay, si no la provincia.
            'region_name' => $this->whenLoaded('region', fn () => $this->region?->name),
            'province_id' => $this->whenLoaded('region', fn () => $this->region?->parent_id ?? $this->region?->id),
            'province_name' => $this->whenLoaded('region', fn () => $this->region?->parent?->name ?? $this->region?->name),
            'location_label' => $this->whenLoaded('region', fn () => $this->locationLabel()),
            'created_at' => $this->created_at?->toIso8601String(),
            'projects' => ProjectResource::collection($this->whenLoaded('projects')),
        ];
    }
}
