<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunThesisReview;
use App\Models\Document;
use App\Services\Review\ThesisReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /** Jadwalkan review AI via queue (disarankan untuk skripsi penuh). */
    public function store(Request $request, Document $document): JsonResponse
    {
        $request->validate([
            'provider' => ['nullable', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:120'],
        ]);

        $document->update(['status' => 'reviewing']);
        RunThesisReview::dispatch($document, $request->input('provider'), $request->input('model'));

        return response()->json([
            'message' => 'Review AI dijadwalkan. Cek status di /api/documents/{id}/review.',
            'status' => 'reviewing',
        ], 202);
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json([
            'status' => $document->status,
            'summary' => $document->review_summary,
            'highlights_count' => $document->highlights()->count(),
            'comments_count' => $document->comments()->count(),
        ]);
    }

    /** Review sinkron — untuk dokumen pendek / testing. */
    public function sync(Request $request, Document $document, ThesisReviewService $service): JsonResponse
    {
        $summary = $service->review($document, $request->input('provider'), $request->input('model'));

        return response()->json(['message' => 'Review selesai.', 'summary' => $summary]);
    }
}
