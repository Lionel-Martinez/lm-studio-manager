<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
            'remind_on' => ['required', 'date'],
            'done' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id' => 'cliente',
            'title' => 'título',
            'notes' => 'notas',
            'remind_on' => 'fecha',
        ];
    }
}
