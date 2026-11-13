<?php

namespace App\Http\Requests;

use App\Enums\ClientType;
use App\Enums\CommercialStatus;
use App\Enums\ProjectStatus;
use App\Enums\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Un cliente nuevo puede venir con su primer proyecto (servicio y fechas).
     */
    public function rules(): array
    {
        $dueDateRules = ['nullable', 'date'];

        // Solo se compara contra el inicio si el inicio fue informado.
        if ($this->filled('project.estimated_start_date')) {
            $dueDateRules[] = 'after_or_equal:project.estimated_start_date';
        }

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'region_id' => [
                'nullable',
                'integer',
                'exists:regions,id',
                // La provincia o el departamento tiene que ser del país elegido.
                function (string $attribute, mixed $value, \Closure $fail) {
                    $countryId = $this->input('country_id');

                    if ($value && $countryId && ! \App\Models\Region::whereKey($value)->where('country_id', $countryId)->exists()) {
                        $fail('La provincia o el departamento no pertenece al país elegido.');
                    }
                },
            ],
            'locality_id' => ['nullable', 'integer', 'exists:localities,id'],
            'first_contact_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:255'],
            'client_type' => ['nullable', Rule::enum(ClientType::class)],
            'commercial_status' => ['nullable', Rule::enum(CommercialStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],

            'project' => ['nullable', 'array'],
            'project.service_type' => ['required_with:project', Rule::enum(ServiceType::class)],
            'project.name' => ['nullable', 'string', 'max:150'],
            'project.status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'project.entry_date' => ['nullable', 'date'],
            'project.estimated_start_date' => ['nullable', 'date'],
            'project.due_date' => $dueDateRules,
            'project.delay_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'company_name' => 'empresa o negocio',
            'tax_id' => 'CUIT o DNI',
            'email' => 'correo',
            'phone' => 'teléfono',
            'whatsapp' => 'WhatsApp',
            'website' => 'sitio web',
            'country_id' => 'país',
            'region_id' => 'provincia o departamento',
            'locality_id' => 'localidad',
            'first_contact_at' => 'fecha de primer contacto',
            'source' => 'origen',
            'client_type' => 'tipo de cliente',
            'commercial_status' => 'estado del cliente',
            'notes' => 'notas',
            'project.service_type' => 'tipo de servicio',
            'project.name' => 'nombre del proyecto',
            'project.status' => 'estado',
            'project.entry_date' => 'fecha de ingreso',
            'project.estimated_start_date' => 'inicio estimado',
            'project.due_date' => 'entrega estimada',
            'project.delay_reason' => 'motivo de la demora',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'required_with' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute no puede superar los :max caracteres.',
            'email' => 'Ingresá un correo válido.',
            'date' => 'El campo :attribute no es una fecha válida.',
            'exists' => 'El valor elegido para :attribute no existe.',
            'enum' => 'El valor elegido para :attribute no es válido.',
            'after_or_equal' => 'La entrega estimada no puede ser anterior al inicio estimado.',
        ];
    }
}
