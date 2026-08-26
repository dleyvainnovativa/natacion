<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-maintenance') ?? false;
    }

    public function rules(): array
    {
        return [
            'pool_id'       => ['nullable', 'exists:pools,id'],
            'title'         => ['required', 'string', 'max:150'],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'scheduled_for' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Describe la tarea de mantenimiento.',
        ];
    }
}
