<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'project_id' => [
                'nullable',
                'integer',
                // El proyecto tiene que ser del mismo cliente.
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null) {
                        return;
                    }

                    $clientId = $this->route('payment')?->client_id ?? $this->input('client_id');

                    if (! Project::whereKey($value)->where('client_id', $clientId)->exists()) {
                        $fail('El proyecto elegido no pertenece a ese cliente.');
                    }
                },
            ],
            'concept' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'due_date' => ['required', 'date'],
            'paid_at' => ['nullable', 'date'],
            'method' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id' => 'cliente',
            'project_id' => 'proyecto',
            'concept' => 'concepto',
            'amount' => 'importe',
            'due_date' => 'fecha de vencimiento',
            'paid_at' => 'fecha de cobro',
            'method' => 'medio de pago',
            'notes' => 'notas',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.gt' => 'El importe debe ser mayor a cero.',
            'amount.numeric' => 'El importe debe ser un número.',
            'client_id.prohibited' => 'No se puede cambiar el cliente de un pago.',
        ];
    }
}
