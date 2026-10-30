<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Review\ThesisReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ReviewController extends Controller
{
    /**
     * Proses review SATU batch per request (chunk) supaya request selalu
     * pendek — dokumen panjang tidak menggantung, progres terlihat, dan
     * bila terputus proses bisa dilanjutkan dari batch terakhir.
     * Frontend memanggil endpoint ini berulang sampai status "reviewed".
     */
    public function store(Request $request, Document $document, ThesisReviewService $service): JsonResponse
    {
        $request->validate([
            'provider' => ['nullable', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'integer'], // index section awal (BAB terpilih)
            'to' => ['nullable', 'integer'],   // index section akhir
        ]);

        try {
            $progress = $service->reviewChunk($document, $request->input('provider'), $request->input('model'),
                $request->input('from') !== null ? (int) $request->input('from') : null,
                $request->input('to') !== null ? (int) $request->input('to') : null);
        } catch (Throwable $e) {
            report($e);

            $document->update([
                'status' => 'review_failed',
                'review_summary' => ['error' => $e->getMessage()],
            ]);

            // HTTP 200 (bukan 5xx) supaya frontend membaca alasan gagal dengan rapi.
            return response()->json([
                'status' => 'review_failed',
                'error' => $e->getMessage(),
                'summary' => ['error' => $e->getMessage()],
            ]);
        }

        return response()->json([
            'status' => $document->status,
            'progress' => $progress,
            'summary' => $document->review_summary,
            'highlights_count' => $document->highlights()->count(),
        ]);
    }

    public function show(Document $document): JsonResponse
    {
        $summary = $document->review_summary;

        // Sembunyikan cursor teknis dari ringkasan publik.
        if (is_array($summary) && array_key_exists('cursor', $summary)) {
            $summary = array_filter($summary, fn ($k) => $k !== 'cursor', ARRAY_FILTER_USE_KEY);
        }

        return response()->json([
            'status' => $document->status,
            'summary' => $summary,
            'highlights_count' => $document->highlights()->count(),
            'comments_count' => $document->comments()->count(),
        ]);
    }

    /** Review sinkron penuh — untuk dokumen pendek / testing. */
    public function sync(Request $request, Document $document, ThesisReviewService $service): JsonResponse
    {
        $summary = $service->review($document, $request->input('provider'), $request->input('model'));

        return response()->json(['message' => 'Review selesai.', 'summary' => $summary]);
    }
}
