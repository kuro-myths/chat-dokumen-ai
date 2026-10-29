<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\MaxRouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint sinkronisasi dengan Max Router (9Router V3):
 *
 * - GET  /api/ai/router/status : cek router ONLINE (Railway) & LOKAL
 *   (localhost:3001) — dipakai UI untuk titik hijau/merah.
 * - POST /api/ai/router/sync  : sinkron sekarang — tarik daftar model
 *   dari router aktif (online duluan, fallback lokal), daftarkan
 *   aplikasi ini ke router, dan perbarui provider "manual".
 */
class RouterSyncController extends Controller
{
    public function __construct(protected MaxRouterService $router) {}

    public function status(): JsonResponse
    {
        return response()->json($this->router->status());
    }

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prefer' => ['nullable', 'in:auto,online,local'],
        ]);

        $result = $this->router->sync($data['prefer'] ?? 'auto');

        return response()->json($result, $result['ok'] ? 200 : 502);
    }
}
