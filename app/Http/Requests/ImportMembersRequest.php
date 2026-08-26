<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import-members') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'], // 10 MB
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Selecciona un archivo Excel.',
            'file.mimes'    => 'El archivo debe ser .xlsx o .xls.',
        ];
    }
}
