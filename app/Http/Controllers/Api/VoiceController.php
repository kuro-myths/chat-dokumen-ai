<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVoiceNoteRequest;
use App\Models\Document;
use App\Services\Voice\VoiceTranscriptionService;
use Illuminate\Http\JsonResponse;

class VoiceController extends Controller
{
    /**
     * Auto Voice Input: unggah rekaman suara -> transkripsi otomatis
     * -> (opsional) rapikan bahasa -> langsung masuk dokumen.
     * Digunakan bersama resources/js/voice-input.js di frontend.
     */
    public function store(StoreVoiceNoteRequest $request, VoiceTranscriptionService $voice): JsonResponse
    {
        $document = $request->filled('document_id')
            ? Document::findOrFail($request->input('document_id'))
            : null;

        $transcription = $voice->handle(
            $request->file('audio'),
            $document,
            $request->input('section_index'),
            [
                'duration' => $request->input('duration'),
                'mode' => $request->input('mode', 'append'),
            ],
        );

        return response()->json([
            'message' => 'Transkripsi selesai'.($document ? ' dan teks dimasukkan ke dokumen.' : '.'),
            'text' => $transcription->text,
            'document' => $document?->fresh(['sections']),
        ], 201);
    }
}
