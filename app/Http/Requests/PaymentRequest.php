<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('record-payments') ?? false;
    }

    public function rules(): array
    {
        return [
            'type'         => ['required', 'in:monthly,one_off'],
            'concept'      => ['required', 'string', 'max:120'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'paid_on'      => ['required', 'date'],
            'period_start' => ['nullable', 'date'],
            'period_end'   => ['nullable', 'date', 'after_or_equal:period_start'],
        ];
    }

    public function messages(): array
    {
        return [
            'concept.required' => 'Indica el concepto del pago.',
            'amount.required'  => 'Indica el monto.',
            'paid_on.required' => 'Indica la fecha de pago.',
        ];
    }
}
