<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Services\Ai\Facades\Ai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
     * Tambah provider AI baru dari API APA PUN — TANPA perlu isi slug
     * (field slug sudah dihapus dari UI; slug dibuat otomatis dari nama).
     *
     * Gemini -> driver "gemini" (key format AIza... maupun AQ.Ab8...).
     * Endpoint kompatibel OpenAI (Max Router, OpenRouter, dll.)
     * -> driver "openai-compatible" + base_url.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['nullable', 'string', 'max:60', 'alpha_dash'],
            'name' => ['required', 'string', 'max:120'],
            'driver' => ['required', 'in:openai-compatible,gemini,openai'],
            'base_url' => ['nullable', 'url', 'required_if:driver,openai-compatible'],
            'model' => ['nullable', 'string', 'max:120'],
            'api_key' => ['nullable', 'string', 'max:1024'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        // Slug otomatis dari nama kalau tidak dikirim.
        $slug = trim((string) ($data['slug'] ?? ''));
        $base = $slug !== '' ? $slug : (Str::slug($data['name']) ?: 'provider');

        $suffix = 1;
        $slug = $base;

        while (AiSetting::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        // Provider dengan slug sudah pernah ada -> perbarui, bukan error.
        $setting = AiSetting::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $data['name'],
                'driver' => $data['driver'],
                'base_url' => $data['base_url'] ?? null,
                'model' => $data['model'] ?? null,
                'is_active' => true,
            ],
        );

        // API key hanya ditimpa bila diisi (kosong = pertahankan yang lama).
        if (filled($data['api_key'] ?? null)) {
            $setting->update(['api_key' => $data['api_key']]);
        }

        if (! empty($data['is_default'])) {
            AiSetting::query()->update(['is_default' => false]);
            $setting->update(['is_default' => true]);
        }

        return response()->json($setting->refresh()->makeHidden('api_key'), 201);
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
