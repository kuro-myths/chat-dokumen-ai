<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request, Document $document): JsonResponse
    {
        $query = $document->comments()->with('replies')->whereNull('parent_id');

        if ($request->filled('section_id')) {
            $query->where('document_section_id', $request->integer('section_id'));
        }

        return response()->json($query->oldest()->get());
    }

    /** Komentar mahasiswa/dosen pada paragraf tertentu (mirip Google Docs). */
    public function store(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'document_section_id' => ['nullable', 'exists:document_sections,id'],
            'author_name' => ['nullable', 'string', 'max:120'],
        ]);

        $comment = $document->comments()->create([
            ...$data,
            'document_section_id' => $data['document_section_id'] ?? null,
            'author_name' => $data['author_name'] ?? $request->user()?->name ?? 'Anonim',
            'is_ai' => false,
        ]);

        return response()->json($comment, 201);
    }

    public function reply(Request $request, Comment $comment): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'author_name' => ['nullable', 'string', 'max:120'],
        ]);

        $reply = $comment->replies()->create([
            'document_id' => $comment->document_id,
            'document_section_id' => $comment->document_section_id,
            'highlight_id' => $comment->highlight_id,
            'body' => $data['body'],
            'author_name' => $data['author_name'] ?? $request->user()?->name ?? 'Anonim',
            'is_ai' => false,
        ]);

        return response()->json($reply, 201);
    }

    public function resolve(Comment $comment): JsonResponse
    {
        $comment->update(['resolved' => ! $comment->resolved]);

        return response()->json($comment->fresh());
    }
}
