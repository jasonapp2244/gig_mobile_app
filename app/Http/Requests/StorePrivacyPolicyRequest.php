<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrivacyPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'sometimes|boolean',
            'effective_date' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'The privacy policy title is required.',
            'title.string' => 'The title must be a valid text string.',
            'title.max' => 'The title cannot exceed 255 characters.',
            'content.required' => 'The privacy policy content is required.',
            'content.string' => 'The content must be a valid text string.',
            'is_active.boolean' => 'The active status must be true or false.',
            'effective_date.date' => 'Please enter a valid effective date.',
        ];
    }
}
