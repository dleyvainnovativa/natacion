<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('move-classes') ?? false;
    }

    public function rules(): array
    {
        return [
            'program_id'    => ['required', 'exists:programs,id'],
            'instructor_id' => ['nullable', 'exists:instructors,id'],
            'lane_id'       => ['nullable', 'exists:lanes,id'],
            'weekday'       => ['required', 'integer', 'between:1,7'],
            'start_time'    => ['required', 'date_format:H:i'],
            'duration_min'  => ['nullable', 'integer', 'min:5', 'max:240'],
        ];
    }

    public function messages(): array
    {
        return [
            'program_id.required' => 'Elige un programa.',
            'weekday.required'    => 'Elige un día.',
            'start_time.required' => 'Indica la hora de inicio.',
            'start_time.date_format' => 'La hora debe tener formato HH:MM.',
        ];
    }
}
