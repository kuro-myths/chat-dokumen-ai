<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDocumentRequest;
use App\Models\Document;
use App\Services\Docx\DocxExportService;
use App\Services\Docx\DocxParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DocumentController extends Controller
{
    /**
     * Daftar dokumen + statistik kartu (jumlah paragraf & temuan AI)
     * untuk kartu dokumen di popup beranda.
     */
    public function index()
    {
        return Document::query()
            ->withCount(['sections', 'highlights'])
            ->latest()
            ->get(['id', 'title', 'type', 'status', 'created_at', 'updated_at']);
    }

    /** Upload .docx skripsi/proposal lalu impor paragrafnya. */
    public function store(UploadDocumentRequest $request, DocxParserService $parser): JsonResponse
    {
        $file = $request->file('document');
        $path = $file->store('documents', 'local');

        $document = Document::create([
            'user_id' => $request->user()?->id,
            'title' => $request->input('title') ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'type' => $request->input('type', 'skripsi'),
            'source_path' => $path,
        ]);

        try {
            $parser->import($document, Storage::disk('local')->path($path));
        } catch (Throwable $e) {
            report($e);

            $document->delete();
            Storage::disk('local')->delete($path);

            return response()->json([
                'message' => 'Dokumen gagal dibaca: pastikan file .docx valid (bukan .doc lama, bukan file rusak/terproteksi password), lalu unggah ulang.',
                'error' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Dokumen berhasil diimpor.',
            'document' => $document->load('sections'),
        ], 201);
    }

    public function show(Document $document)
    {
        return $document->load([
            'sections' => fn ($q) => $q->orderBy('index'),
            'highlights',
            'comments' => fn ($q) => $q->with('replies')->whereNull('parent_id')->oldest(),
            'versions',
        ]);
    }

    /** Unduh .docx dengan highlight warna + laporan review AI. */
    public function export(Document $document, DocxExportService $exporter): Response
    {
        $path = $exporter->export($document);

        return response()->download($path, basename($path));
    }

    public function destroy(Document $document): JsonResponse
    {
        $document->delete();

        return response()->json(['message' => 'Dokumen dihapus.']);
    }

    /** Tambah paragraf manual (misal hasil ketik/voice tanpa dokumen AI). */
    public function addSection(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
            'heading' => ['nullable', 'string', 'max:255'],
        ]);

        $last = $document->sections()->orderByDesc('index')->first();

        $section = $document->sections()->create([
            'index' => ($last?->index ?? -1) + 1,
            'heading' => $data['heading'] ?? null,
            'level' => isset($data['heading']) ? 2 : null,
            'body' => $data['body'],
        ]);

        return response()->json($section, 201);
    }
}
