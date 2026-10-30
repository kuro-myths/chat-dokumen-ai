<?php

use App\Http\Controllers\Api\AiSetupController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\HighlightController;
use App\Http\Controllers\Api\GenerateController;
use App\Http\Controllers\Api\ModelController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\RouterSyncController;
use App\Http\Controllers\Api\VoiceController;
use Illuminate\Support\Facades\Route;

// ---------- Dokumen (DOCX) ----------
Route::apiResource('documents', DocumentController::class)->only(['index', 'store', 'show', 'destroy']);
Route::post('documents/{document}/sections', [DocumentController::class, 'addSection']);
Route::get('documents/{document}/export', [DocumentController::class, 'export']);
Route::post('documents/{document}/reimport', [DocumentController::class, 'reimport']); // impor ulang dokumen macet

// ---------- Review AI (highlight warna + revisi) ----------
Route::post('documents/{document}/review', [ReviewController::class, 'store']);      // async (queue)
Route::post('documents/{document}/review/sync', [ReviewController::class, 'sync']); // sinkron
Route::get('documents/{document}/review', [ReviewController::class, 'show']);
Route::get('documents/{document}/highlights', [HighlightController::class, 'index']);
Route::post('documents/{document}/highlights/accept-all', [HighlightController::class, 'acceptAll']);
Route::post('highlights/{highlight}/accept', [HighlightController::class, 'accept']);
Route::post('highlights/{highlight}/reject', [HighlightController::class, 'reject']);

// ---------- Komentar (mirip Google Docs) ----------
Route::get('documents/{document}/comments', [CommentController::class, 'index']);
Route::post('documents/{document}/comments', [CommentController::class, 'store']);
Route::post('comments/{comment}/reply', [CommentController::class, 'reply']);
Route::patch('comments/{comment}/resolve', [CommentController::class, 'resolve']);

// ---------- Auto Voice Input ----------
Route::post('voice/transcribe', [VoiceController::class, 'store']);

// ---------- Integrasi API AI apa pun ----------
Route::get('ai/providers', [ProviderController::class, 'index']);
Route::post('ai/providers', [ProviderController::class, 'store']);
Route::put('ai/providers/default', [ProviderController::class, 'setDefault']);

// ---------- Lihat model AI per provider (Gemini, 9Router, OpenAI, dll.) ----------
Route::get('ai/models', [ModelController::class, 'index']);     // ?fetch=1 untuk tarik model semua provider
Route::get('ai/models/{slug}', [ModelController::class, 'show']);

// ---------- Setup 2 dropdown: Gemini (AI Studio) & Manual (Max Router) ----------
// Mode "gemini" dan "manual" berdampingan tanpa bentrok: keduanya
// disimpan di ai_settings, "mode" menentukan yang aktif (is_default).
Route::get('ai/setup', [AiSetupController::class, 'show']);
Route::post('ai/setup', [AiSetupController::class, 'store']);
Route::post('ai/setup/test-gemini', [AiSetupController::class, 'testGemini']);

// ---------- Sinkron Max Router (online: Railway / offline: localhost) ----------
Route::get('ai/router/status', [RouterSyncController::class, 'status']);
Route::post('ai/router/sync', [RouterSyncController::class, 'sync']);

// ---------- Generate gambar & video (Gemini image / Veo, key AI Studio) ----------
Route::post('ai/generate/image', [GenerateController::class, 'image']);
Route::post('ai/generate/video', [GenerateController::class, 'video']);
Route::get('ai/generate/video/status', [GenerateController::class, 'videoStatus']);

