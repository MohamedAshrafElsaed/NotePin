<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTextNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'text' => ['nullable', 'string', 'min:10', 'max:20000'],
            'text_file' => ['nullable', 'file', 'mimes:txt,md', 'max:2048'],
            'anonymous_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text.min' => __('textInput.validation.textMin'),
            'text.max' => __('textInput.validation.textMax'),
            'text_file.mimes' => __('textInput.validation.fileMimes'),
            'text_file.max' => __('textInput.validation.fileMax'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasText = filled($this->input('text'));
            $hasFile = $this->hasFile('text_file');

            if (!$hasText && !$hasFile) {
                $validator->errors()->add('text', __('textInput.validation.required'));
            }
        });
    }
}
