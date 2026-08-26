<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-members') ?? false;
    }

    public function rules(): array
    {
        $memberId = $this->route('member')?->id;

        return [
            'socio_number' => [
                'required', 'integer', 'min:1',
                Rule::unique('members', 'socio_number')->ignore($memberId)->whereNull('deleted_at'),
            ],
            'first_name'         => ['required', 'string', 'max:120'],
            'last_name_1'        => ['required', 'string', 'max:120'],
            'last_name_2'        => ['nullable', 'string', 'max:120'],
            'phone'              => ['nullable', 'string', 'max:30'],
            'email'              => ['nullable', 'email', 'max:180'],
            'membership_type_id' => ['nullable', 'exists:membership_types,id'],
            'next_billing_date'  => ['nullable', 'date'],
            'status'             => ['required', 'string', 'max:30'],
            'fee'                => ['nullable', 'numeric', 'min:0'],
            'notes'              => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'socio_number.unique'   => 'Ya existe un socio con ese número.',
            'socio_number.required' => 'El número de socio es obligatorio.',
            'first_name.required'   => 'El nombre es obligatorio.',
            'last_name_1.required'  => 'El primer apellido es obligatorio.',
        ];
    }
}
