<?php

namespace App\Http\Requests;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-members') ?? false;
    }

    public function rules(): array
    {
        return [
            // La unicidad se valida en withValidator() para poder nombrar al
            // socio que ya tiene el número (y detectar también los dados de baja,
            // que el índice UNIQUE de la BD sí cuenta → antes reventaba en INSERT).
            'socio_number' => ['required', 'integer', 'min:1'],
            'first_name'         => ['required', 'string', 'max:120'],
            'last_name_1'        => ['required', 'string', 'max:120'],
            'last_name_2'        => ['nullable', 'string', 'max:120'],
            'birth_date'         => ['nullable', 'date', 'before:today'],
            'phone'              => ['nullable', 'string', 'max:30'],
            'email'              => ['nullable', 'email', 'max:180'],
            'membership_type_id' => ['nullable', 'exists:membership_types,id'],
            'next_billing_date'  => ['nullable', 'date'],
            'status'             => ['required', 'string', 'max:30'],
            'fee'                => ['nullable', 'numeric', 'min:0'],
            'notes'              => ['nullable', 'string', 'max:2000'],

            // Inscripción opcional a clases al registrar (ver EnrollmentService).
            'slot_ids'           => ['nullable', 'array'],
            'slot_ids.*'         => ['integer', 'exists:schedule_slots,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'socio_number.required' => 'El número de socio es obligatorio.',
            'first_name.required'   => 'El nombre es obligatorio.',
            'last_name_1.required'  => 'El primer apellido es obligatorio.',
        ];
    }

    /**
     * Unicidad del número de socio con mensaje que NOMBRA al socio que ya lo
     * tiene. Considera también los dados de baja (soft delete): el índice UNIQUE
     * de la BD los cuenta, así que sin esto el INSERT reventaba con una página de
     * error. Si el choque es contra una baja, ofrecemos reactivarla.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $number = $this->input('socio_number');

            // Si ya falló required/integer, no duplicar el error.
            if (! $number || $validator->errors()->has('socio_number')) {
                return;
            }

            $memberId = $this->route('member')?->id;

            $existing = Member::withTrashed()
                ->where('socio_number', $number)
                ->when($memberId, fn ($q) => $q->whereKeyNot($memberId))
                ->first();

            if (! $existing) {
                return;
            }

            $name = $existing->fullName();

            if ($existing->trashed()) {
                $msg = "El número {$number} pertenece a {$name}, dado de baja. Reactívalo en vez de crear uno nuevo.";
                // Para pintar el botón "Reactivar" en el formulario.
                session()->flash('dup_member', ['id' => $existing->id, 'name' => $name]);
            } else {
                $msg = "El número {$number} ya está en uso por {$name}.";
            }

            $validator->errors()->add('socio_number', $msg);

            // Toast además del recuadro de error (puente flash→toast en el layout).
            session()->flash('toast', [['msg' => $msg, 'type' => 'error']]);
        });
    }
}
