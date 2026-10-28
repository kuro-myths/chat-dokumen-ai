<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoiceNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'audio' => ['required', 'file', 'mimes:webm,mp3,wav,m4a,ogg,mp4,flac', 'max:30720'],
            'document_id' => ['nullable', 'exists:documents,id'],
            'section_index' => ['nullable', 'integer', 'min:0'],
            'duration' => ['nullable', 'numeric', 'min:0'],
            'mode' => ['nullable', 'in:append,new-section'],
        ];
    }
}
