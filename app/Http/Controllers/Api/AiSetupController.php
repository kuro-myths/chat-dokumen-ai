<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Services\Ai\MaxRouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Setup AI dengan DUA DROPDOWN yang tidak bentrok:
 *
 * 1. Dropdown "Gemini"   -> API key Google AI Studio (aistudio.google.com/api-keys),
 *                            format lama AIza... maupun baru AQ.Ab8... keduanya valid.
 * 2. Dropdown "Manual"   -> Max Router / API kompatibel OpenAI lain
 *                            (online di Railway maupun lokal localhost:3001).
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
            'gemini_api_key' => ['nullable', 'string', 'max:1024'],
            'gemini_model' => ['nullable', 'string', 'max:120'],
            'manual_base_url' => ['nullable', 'url', 'max:255'],
            'manual_api_key' => ['nullable', 'string', 'max:1024'],
            'manual_model' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            // --- Dropdown 1: Gemini (Google AI Studio) ---
            $gemini = AiSetting::where('slug', 'gemini')->first();

            $geminiKey = trim((string) ($data['gemini_api_key'] ?? ''));

            AiSetting::updateOrCreate(['slug' => 'gemini'], [
                'name' => 'Gemini (AI Studio)',
                'driver' => 'gemini',
                'base_url' => null,
                'api_key' => $geminiKey !== ''
                    ? $geminiKey
                    : ($gemini->api_key ?? config('ai.providers.gemini.api_key')),
                'model' => filled($data['gemini_model'] ?? null)
                    ? trim((string) $data['gemini_model'])
                    : ($gemini->model ?? config('ai.providers.gemini.model', 'gemini-2.5-flash')),
                'is_active' => true,
            ]);

            // --- Dropdown 2: Manual (Max Router — online / lokal) ---
            $manual = AiSetting::where('slug', 'manual')->first();

            $manualBaseUrl = filled($data['manual_base_url'] ?? null)
                ? trim((string) $data['manual_base_url'])
                : ($manual->base_url ?? $this->router->resolve() ?? config('ai.router.local_url', MaxRouterService::LOCAL_DEFAULT));

            $manualKey = trim((string) ($data['manual_api_key'] ?? ''));

            AiSetting::updateOrCreate(['slug' => 'manual'], [
                'name' => 'Manual (Max Router)',
                'driver' => 'openai-compatible',
                'base_url' => $manualBaseUrl,
                'api_key' => $manualKey !== ''
                    ? $manualKey
                    : ($manual->api_key ?? config('ai.router.api_key')),
                'model' => filled($data['manual_model'] ?? null)
                    ? trim((string) $data['manual_model'])
                    : ($manual->model ?? config('ai.router.model')),
                'is_active' => true,
            ]);

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

    /**
     * POST /api/ai/setup/test-gemini — uji API key LANGSUNG ke Google
     * (endpoint native + header x-goog-api-key, jadi format AIza... dan
     * AQ.Ab8... keduanya didukung). Bila valid, key otomatis disimpan
     * supaya user tidak perlu menekan tombol simpan lagi.
     */
    public function testGemini(Request $request): JsonResponse
    {
        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'max:1024'],
        ]);

        $key = trim((string) ($data['api_key'] ?? ''));

        // Kolom kosong = uji key yang sudah tersimpan.
        if ($key === '') {
            try {
                $gemini = AiSetting::where('slug', 'gemini')->first();
                $key = trim((string) ($gemini->api_key ?? config('ai.providers.gemini.api_key')));
            } catch (Throwable) {
                $key = trim((string) config('ai.providers.gemini.api_key'));
            }
        }

        if ($key === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Key masih kosong — tempel key kamu (AIza... / AQ.Ab8...) di kolom API Key Gemini dulu.',
            ]);
        }

        // Heuristik panjang AIza (tepat 39 karakter) untuk menangkap key terpotong lebih awal.
        if (str_starts_with($key, 'AIza') && strlen($key) !== 39) {
            return response()->json([
                'ok' => false,
                'message' => 'Key AIza... biasanya tepat 39 karakter — yang kamu tempel '.strlen($key).' karakter. Kemungkinan terpotong saat menyalin; salin ulang penuh dari aistudio.google.com/api-keys.',
            ]);
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => $key])
                ->get('https://generativelanguage.googleapis.com/v1beta/models', ['pageSize' => 1]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal menghubungi Google: '.$e->getMessage(),
            ]);
        }

        if ($response->status() === 200) {
            try {
                $existing = AiSetting::where('slug', 'gemini')->first();
                AiSetting::updateOrCreate(['slug' => 'gemini'], [
                    'name' => 'Gemini (AI Studio)',
                    'driver' => 'gemini',
                    'base_url' => null,
                    'api_key' => $key,
                    'model' => $existing->model ?? config('ai.providers.gemini.model', 'gemini-2.5-flash'),
                    'is_active' => true,
                ]);
            } catch (Throwable) {
                // tabel belum dimigrasi — key teruji valid, simpan bisa menyusul
            }

            return response()->json([
                'ok' => true,
                'message' => 'Key VALID ('.strlen($key).' karakter) — tersimpan otomatis. Gemini siap dipakai! ✅',
            ]);
        }

        $googleMessage = (string) $response->json('error.message', '');

        $message = match (true) {
            $response->status() === 400 => 'Key DITOLAK Google (400 — API key invalid). Kemungkinan terpotong atau sudah dihapus. Salin ulang penuh dari aistudio.google.com/api-keys, lalu tekan tombol ini lagi.',
            $response->status() === 403 => 'Key ditolak (403). Pastikan key masih aktif dan Generative Language API tidak diblokir di akun Google kamu.',
            $response->status() === 429 => 'Key valid tapi sedang kena limit kuota (429). Tunggu sebentar, atau buat key baru di aistudio.google.com/api-keys.',
            default => 'Google menjawab error '.$response->status().($googleMessage !== '' ? ': '.$googleMessage : '.').' Coba lagi sebentar.',
        };

        return response()->json(['ok' => false, 'message' => $message]);
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
                'model' => $gemini->model ?? config('ai.providers.gemini.model', 'gemini-2.5-flash'),
                'is_default' => $mode === 'gemini',
                'keys_url' => 'https://aistudio.google.com/api-keys',
                'key_hint' => 'Format lama AIza... maupun baru AQ.Ab8... keduanya didukung — salin penuh.',
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
