<?php

namespace App\Http\Requests;

use App\Enums\DeactivationReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeactivateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(DeactivationReason::class)],
            'note' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function attributes(): array
    {
        return ['reason' => 'motivo', 'note' => 'nota'];
    }
}
