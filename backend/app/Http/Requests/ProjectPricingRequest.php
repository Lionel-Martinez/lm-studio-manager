<?php

namespace App\Http\Requests;

use App\Enums\PaymentMode;
use App\Enums\TrialUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hasPrice = (float) $this->input('total_price') > 0;
        $hasTrial = (int) $this->input('trial_value') > 0;

        return [
            'total_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'payment_mode' => [$hasPrice ? 'required' : 'nullable', Rule::enum(PaymentMode::class)],
            'deposit_percent' => [$this->input('payment_mode') === 'split' ? 'required' : 'nullable', 'integer', 'min:1', 'max:99'],
            'installments_count' => [$this->input('payment_mode') === 'installments' ? 'required' : 'nullable', 'integer', 'min:2', 'max:60'],
            'plan_start_date' => ['required', 'date'],
            'monthly_fee' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.99'],
            'monthly_months' => [$this->filled('monthly_fee') ? 'required' : 'nullable', 'integer', 'min:1', 'max:60'],
            'free_months' => ['nullable', 'integer', 'min:0', 'max:24'],
            'trial_value' => ['nullable', 'integer', 'min:0', 'max:365'],
            'trial_unit' => [$hasTrial ? 'required' : 'nullable', Rule::enum(TrialUnit::class)],
            'generate' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'total_price' => 'precio total',
            'payment_mode' => 'forma de pago',
            'deposit_percent' => 'porcentaje de anticipo',
            'installments_count' => 'cantidad de cuotas',
            'plan_start_date' => 'inicio del plan',
            'monthly_fee' => 'cuota mensual',
            'monthly_months' => 'cantidad de meses',
            'free_months' => 'meses gratis',
            'trial_value' => 'período de prueba',
            'trial_unit' => 'unidad de la prueba',
        ];
    }

    /** Datos del plan listos para guardar en el proyecto (sin la orden de generar). */
    public function planData(): array
    {
        $d = $this->validated();
        $hasPrice = (float) ($d['total_price'] ?? 0) > 0;
        $mode = $hasPrice ? ($d['payment_mode'] ?? null) : null;

        return [
            'total_price' => $hasPrice ? $d['total_price'] : null,
            'payment_mode' => $mode,
            'deposit_percent' => $mode === 'split' ? $d['deposit_percent'] : null,
            'installments_count' => $mode === 'installments' ? $d['installments_count'] : null,
            'plan_start_date' => $d['plan_start_date'],
            'monthly_fee' => $d['monthly_fee'] ?? null,
            'monthly_months' => isset($d['monthly_fee']) ? $d['monthly_months'] : null,
            'free_months' => (int) ($d['free_months'] ?? 0),
            'trial_value' => (int) ($d['trial_value'] ?? 0),
            'trial_unit' => ((int) ($d['trial_value'] ?? 0)) > 0 ? $d['trial_unit'] : null,
        ];
    }
}
