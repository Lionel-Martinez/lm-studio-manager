<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'project_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null) {
                        return;
                    }

                    if (! $this->filled('client_id') || ! Project::whereKey($value)->where('client_id', $this->input('client_id'))->exists()) {
                        $fail('El proyecto elegido no pertenece a ese cliente.');
                    }
                },
            ],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:20000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id' => 'cliente',
            'project_id' => 'proyecto',
            'title' => 'título',
            'body' => 'contenido',
        ];
    }
}
