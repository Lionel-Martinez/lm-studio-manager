<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Enums\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $dueDateRules = ['nullable', 'date'];

        if ($this->filled('estimated_start_date')) {
            $dueDateRules[] = 'after_or_equal:estimated_start_date';
        }

        return [
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'name' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'entry_date' => ['nullable', 'date'],
            'estimated_start_date' => ['nullable', 'date'],
            'due_date' => $dueDateRules,
            'delay_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'service_type' => 'tipo de servicio',
            'name' => 'nombre del proyecto',
            'status' => 'estado',
            'entry_date' => 'fecha de ingreso',
            'estimated_start_date' => 'inicio estimado',
            'due_date' => 'entrega estimada',
            'delay_reason' => 'motivo de la demora',
        ];
    }

    public function messages(): array
    {
        return [
            'after_or_equal' => 'La entrega estimada no puede ser anterior al inicio estimado.',
        ];
    }
}
