<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Highlight;
use App\Services\Review\RevisionService;
use Illuminate\Http\JsonResponse;

class HighlightController extends Controller
{
    public function index(Document $document): JsonResponse
    {
        return response()->json(
            $document->highlights()->with('section:id,heading,index')->get()
        );
    }

    /** Terima revisi: teks asli diganti replacement + versi tersimpan. */
    public function accept(Highlight $highlight, RevisionService $revisions): JsonResponse
    {
        return response()->json([
            'message' => 'Revisi diterima.',
            'highlight' => $revisions->accept($highlight),
        ]);
    }

    public function reject(Highlight $highlight, RevisionService $revisions): JsonResponse
    {
        return response()->json([
            'message' => 'Revisi ditolak.',
            'highlight' => $revisions->reject($highlight),
        ]);
    }

    public function acceptAll(Document $document, RevisionService $revisions): JsonResponse
    {
        $count = $revisions->acceptAll($document);

        return response()->json([
            'message' => "{$count} revisi diterapkan.",
            'count' => $count,
        ]);
    }
}
