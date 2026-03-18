<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'topic' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
            'website' => ['nullable', 'max:0'],
            'submitted_from' => ['nullable', 'integer'],
            'cf-turnstile-response' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.min' => 'Please share a bit more detail so we can help properly.',
            'website.max' => 'Security validation failed. Please try again.',
        ];
    }
}
