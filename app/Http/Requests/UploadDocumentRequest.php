<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'mimes:docx', 'max:20480'],
            'title' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:skripsi,proposal'],
        ];
    }
}
