<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiModelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lihat model AI yang tersedia di tiap provider — Gemini, 9Router,
 * OpenAI, Groq, Mistral, Anthropic, Ollama, maupun provider custom
 * dari tabel ai_settings.
 */
class ModelController extends Controller
{
    public function __construct(protected AiModelService $models) {}

    /**
     * GET /api/ai/models — daftar semua provider.
     * Tambahkan ?fetch=1 untuk sekalian menarik daftar model dari tiap provider.
     */
    public function index(Request $request): JsonResponse
    {
        $providers = $this->models->providers();

        if ($request->boolean('fetch')) {
            foreach ($providers as &$provider) {
                $result = $this->models->models($provider['slug']);
                $provider['models'] = $result['models'];
                $provider['error'] = $result['error'];
            }
        }

        return response()->json([
            'default' => $this->models->defaultSlug(),
            'providers' => $providers,
        ]);
    }

    /** GET /api/ai/models/{slug} — daftar model satu provider. */
    public function show(string $slug): JsonResponse
    {
        return response()->json($this->models->models($slug));
    }
}
