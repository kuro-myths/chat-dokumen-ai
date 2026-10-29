<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Services\Ai\Facades\Ai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    /** Daftar semua provider: dari config + dari database. */
    public function index(): JsonResponse
    {
        return response()->json([
            'default' => config('ai.default'),
            'config_providers' => Ai::providers(),
            'database_providers' => AiSetting::orderByDesc('is_default')->get([
                'id', 'slug', 'name', 'driver', 'base_url', 'model', 'is_active', 'is_default',
            ]),
        ]);
    }

    /**
     * Tambah provider AI baru dari API APA PUN.
     * Gemini -> driver "gemini". Endpoint kompatibel OpenAI (9Router,
     * OpenRouter, dll.) -> driver "openai-compatible" + base_url.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:ai_settings,slug'],
            'name' => ['required', 'string', 'max:120'],
            'driver' => ['required', 'in:openai-compatible,gemini,openai'],
            'base_url' => ['nullable', 'url', 'required_if:driver,openai-compatible'],
            'model' => ['nullable', 'string', 'max:120'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['is_default'])) {
            AiSetting::query()->update(['is_default' => false]);
        }

        return response()->json(
            AiSetting::create($data)->makeHidden('api_key'),
            201,
        );
    }

    public function setDefault(Request $request): JsonResponse
    {
        $data = $request->validate(['slug' => ['required', 'string', 'max:60']]);

        $setting = AiSetting::where('slug', $data['slug'])->firstOrFail();

        AiSetting::query()->update(['is_default' => false]);
        $setting->update(['is_default' => true, 'is_active' => true]);

        return response()->json(['message' => "Provider default sekarang: {$setting->slug}"]);
    }
}
