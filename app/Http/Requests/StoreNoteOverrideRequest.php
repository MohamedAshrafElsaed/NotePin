<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNoteOverrideRequest extends FormRequest
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
        return [
            'title' => 'required|string|min:2|max:120',
            'summary' => 'required|string|min:2|max:4000',
            'action_items' => 'required|array|max:20',
            'action_items.*' => 'required|string|min:2|max:200',
        ];
    }
}
