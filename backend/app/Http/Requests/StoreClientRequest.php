<?php

namespace App\Http\Requests;

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
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
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
            'name' => 'nombre',
            'contact_name' => 'persona de contacto',
            'email' => 'correo',
            'phone' => 'teléfono',
            'city' => 'localidad',
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
            'enum' => 'El valor elegido para :attribute no es válido.',
            'after_or_equal' => 'La entrega estimada no puede ser anterior al inicio estimado.',
        ];
    }
}
