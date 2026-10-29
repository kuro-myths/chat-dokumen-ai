<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Services\Ai\MaxRouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Setup AI dengan DUA DROPDOWN yang tidak bentrok:
 *
 * 1. Dropdown "Gemini"   -> API key Google AI Studio (aistudio.google.com/api-keys),
 *                            disimpan sebagai provider slug "gemini".
 * 2. Dropdown "Manual"   -> Max Router / API kompatibel OpenAI lain
 *                            (online di Railway maupun lokal localhost:3001),
 *                            disimpan sebagai provider slug "manual".
 *
 * Field "mode" menentukan dropdown mana yang AKTIF (is_default).
 * Keduanya hidup berdampingan — berganti mode tidak menghapus konfigurasi
 * dropdown satunya.
 */
class AiSetupController extends Controller
{
    public function __construct(protected MaxRouterService $router) {}

    /** GET /api/ai/setup — kondisi setup saat ini. */
    public function show(): JsonResponse
    {
        return response()->json($this->payload());
    }

    /**
     * POST /api/ai/setup — simpan mode + API key/base URL.
     * Kirim gemini_api_key / manual_api_key hanya saat diisi;
     * dikosongkan = pertahankan yang lama.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:gemini,manual'],
            'gemini_api_key' => ['nullable', 'string', 'max:255'],
            'gemini_model' => ['nullable', 'string', 'max:120'],
            'manual_base_url' => ['nullable', 'url', 'max:255'],
            'manual_api_key' => ['nullable', 'string', 'max:255'],
            'manual_model' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            // --- Dropdown 1: Gemini (Google AI Studio) ---
            $gemini = AiSetting::where('slug', 'gemini')->first();

            $geminiPayload = [
                'name' => 'Gemini (AI Studio)',
                'driver' => 'gemini',
                'base_url' => null,
                'api_key' => filled($data['gemini_api_key'] ?? null)
                    ? $data['gemini_api_key']
                    : ($gemini->api_key ?? config('ai.providers.gemini.api_key')),
                'model' => filled($data['gemini_model'] ?? null)
                    ? $data['gemini_model']
                    : ($gemini->model ?? config('ai.providers.gemini.model', 'gemini-2.0-flash')),
                'is_active' => true,
            ];
            AiSetting::updateOrCreate(['slug' => 'gemini'], $geminiPayload);

            // --- Dropdown 2: Manual (Max Router — online / lokal) ---
            $manual = AiSetting::where('slug', 'manual')->first();

            $manualBaseUrl = filled($data['manual_base_url'] ?? null)
                ? $data['manual_base_url']
                : ($manual->base_url ?? $this->router->resolve() ?? config('ai.router.local_url', MaxRouterService::LOCAL_DEFAULT));

            $manualPayload = [
                'name' => 'Manual (Max Router)',
                'driver' => 'openai-compatible',
                'base_url' => $manualBaseUrl,
                'api_key' => filled($data['manual_api_key'] ?? null)
                    ? $data['manual_api_key']
                    : ($manual->api_key ?? config('ai.router.api_key')),
                'model' => filled($data['manual_model'] ?? null)
                    ? $data['manual_model']
                    : ($manual->model ?? config('ai.router.model')),
                'is_active' => true,
            ];
            AiSetting::updateOrCreate(['slug' => 'manual'], $manualPayload);

            // --- Mode: satu provider default, tidak bentrok ---
            AiSetting::query()->update(['is_default' => false]);
            AiSetting::where('slug', $data['mode'])->update([
                'is_default' => true,
                'is_active' => true,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal menyimpan setup: '.$e->getMessage(),
            ], 500);
        }

        $payload = $this->payload();

        return response()->json([
            'ok' => true,
            'message' => 'Setup tersimpan. Mode aktif: '.($payload['mode'] === 'gemini' ? 'Gemini' : 'Manual').'.',
            'mode' => $payload['mode'],
            'setup' => $payload,
        ]);
    }

    /** Bentuk respons setup (aman walau tabel belum dimigrasi). */
    protected function payload(): array
    {
        $gemini = null;
        $manual = null;

        try {
            $gemini = AiSetting::where('slug', 'gemini')->first();
            $manual = AiSetting::where('slug', 'manual')->first();
        } catch (Throwable) {
            // tabel belum dimigrasi — pakai config
        }

        $mode = $this->router->mode();

        return [
            'ok' => true,
            'mode' => $mode,
            'modes' => [
                ['value' => 'gemini', 'label' => 'Gemini — Google AI Studio'],
                ['value' => 'manual', 'label' => 'Manual — Max Router / API sendiri'],
            ],
            'gemini' => [
                'api_key_set' => filled($gemini->api_key ?? config('ai.providers.gemini.api_key')),
                'model' => $gemini->model ?? config('ai.providers.gemini.model', 'gemini-2.0-flash'),
                'is_default' => $mode === 'gemini',
                'keys_url' => 'https://aistudio.google.com/api-keys',
            ],
            'manual' => [
                'base_url' => $manual->base_url ?? config('ai.router.local_url', MaxRouterService::LOCAL_DEFAULT),
                'api_key_set' => filled($manual->api_key ?? config('ai.router.api_key')),
                'model' => $manual->model ?? config('ai.router.model'),
                'is_default' => $mode === 'manual',
            ],
        ];
    }
}
