<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoteActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'type' => ['required', Rule::in(['task', 'meeting', 'reminder'])],
            'selected_items' => 'required|array|min:1|max:10',
            'selected_items.*' => 'required|string|max:200',
            'title' => 'required|string|min:2|max:120',
        ];

        $type = $this->input('type');

        if ($type === 'task') {
            $rules['due_date'] = 'nullable|date';
            $rules['priority'] = ['nullable', Rule::in(['low', 'medium', 'high'])];
        }

        if ($type === 'meeting') {
            $rules['date'] = 'required|date';
            $rules['time'] = 'required|date_format:H:i';
            $rules['duration_minutes'] = 'nullable|integer|min:15|max:180';
            $rules['attendees'] = 'nullable|string|max:500';
        }

        if ($type === 'reminder') {
            $rules['remind_at'] = 'required|date';
            $rules['reminder_note'] = 'nullable|string|max:500';
        }

        return $rules;
    }
}
