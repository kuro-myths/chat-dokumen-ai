<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Generate media via Gemini API (key AI Studio, format AIza... maupun AQ.Ab8...):
 * - POST /api/ai/generate/image  → gambar via gemini-2.5-flash-image
 *   (responseModalities TEXT+IMAGE, hasil base64 inline).
 * - POST /api/ai/generate/video  → video via Veo (predictLongRunning),
 *   lalu poll GET /api/ai/generate/video/status sampai URI video keluar.
 *   Catatan: Veo butuh API key berbayar (billing aktif) — key gratis
 *   AI Studio biasanya ditolak; pesan errornya diterjemahkan ke bahasa Indonesia.
 */
class GenerateController extends Controller
{
    protected const BASE = 'https://generativelanguage.googleapis.com/v1beta';

    /** POST /api/ai/generate/image */
    public function image(Request $request): JsonResponse
    {
        $prompt = trim((string) $request->input('prompt'));
        if ($prompt === '') {
            return response()->json(['ok' => false, 'error' => 'Deskripsi gambar masih kosong.'], 422);
        }

        $key = $this->geminiKey();
        if ($key === null) {
            return response()->json([
                'ok' => false,
                'error' => 'API key Gemini belum diisi. Buka menu API Key → "Sinkron Router & Mode AI", lalu simpan key kamu (AIza... / AQ.Ab8...).',
            ], 422);
        }

        $model = (string) ($request->input('model') ?: config('ai.gemini_image_model', 'gemini-2.5-flash-image'));

        try {
            $response = Http::timeout(120)
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => $key])
                ->post(self::BASE."/models/{$model}:generateContent", [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['responseModalities' => ['TEXT', 'IMAGE']],
                ]);

            if ($err = $this->mapError($response->status(), $response->json('error.message'))) {
                return response()->json(['ok' => false, 'error' => $err], $response->status());
            }

            $parts = $response->json('candidates.0.content.parts', []);
            $text = '';
            $image = null;

            foreach ($parts as $part) {
                if (! empty($part['inlineData']['data'])) {
                    $image = 'data:'.($part['inlineData']['mimeType'] ?? 'image/png').';base64,'.$part['inlineData']['data'];
                } elseif (! empty($part['text'])) {
                    $text .= $part['text'];
                }
            }

            if ($image === null) {
                $msg = trim($text) !== ''
                    ? 'Model tidak menghasilkan gambar. Jawaban model: "'.trim($text).'" — coba deskripsi lain.'
                    : 'Model tidak menghasilkan gambar — coba deskripsi lain.';
                return response()->json(['ok' => false, 'error' => $msg], 502);
            }

            return response()->json([
                'ok' => true,
                'image' => $image,
                'text' => trim($text),
                'model' => $model,
            ]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Gagal menghubungi Gemini: '.$e->getMessage()], 502);
        }
    }

    /** POST /api/ai/generate/video — mulai operasi panjang Veo, kembalikan nama operasi. */
    public function video(Request $request): JsonResponse
    {
        $prompt = trim((string) $request->input('prompt'));
        if ($prompt === '') {
            return response()->json(['ok' => false, 'error' => 'Deskripsi video masih kosong.'], 422);
        }

        $key = $this->geminiKey();
        if ($key === null) {
            return response()->json([
                'ok' => false,
                'error' => 'API key Gemini belum diisi. Buka menu API Key → "Sinkron Router & Mode AI" dan simpan key kamu dulu.',
            ], 422);
        }

        $model = (string) ($request->input('model') ?: config('ai.veo_model', 'veo-3.0-generate-001'));
        $aspect = $request->input('aspect') === '9:16' ? '9:16' : '16:9';

        try {
            $response = Http::timeout(60)
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => $key])
                ->post(self::BASE."/models/{$model}:predictLongRunning", [
                    'instances' => [['prompt' => $prompt]],
                    'parameters' => ['aspectRatio' => $aspect],
                ]);

            if ($err = $this->mapError($response->status(), $response->json('error.message'))) {
                return response()->json(['ok' => false, 'error' => $err], $response->status());
            }

            $operation = $response->json('name');
            if (! is_string($operation) || $operation === '') {
                return response()->json(['ok' => false, 'error' => 'Router video tidak mengembalikan operasi — coba lagi.'], 502);
            }

            return response()->json(['ok' => true, 'operation' => $operation, 'model' => $model]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Gagal memulai pembuatan video: '.$e->getMessage()], 502);
        }
    }

    /** GET /api/ai/generate/video/status?operation=... — poll sampai video siap. */
    public function videoStatus(Request $request): JsonResponse
    {
        $operation = trim((string) $request->query('operation', ''));
        if (! preg_match('#^models/veo-[\w.-]+/operations/[\w-]+$#', $operation)) {
            return response()->json(['ok' => false, 'error' => 'Parameter operasi tidak valid.'], 422);
        }

        $key = $this->geminiKey();
        if ($key === null) {
            return response()->json(['ok' => false, 'error' => 'API key Gemini belum tersimpan.'], 422);
        }

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => $key])
                ->get(self::BASE.'/'.$operation);

            if ($err = $this->mapError($response->status(), $response->json('error.message'))) {
                return response()->json(['ok' => false, 'error' => $err], $response->status());
            }

            if (! $response->json('done')) {
                return response()->json(['ok' => true, 'done' => false]);
            }

            $uri = data_get($response->json(), 'response.generateVideoResponse.generatedSamples.0.video.uri');

            if (! is_string($uri) || $uri === '') {
                $rf = data_get($response->json(), 'response.generateVideoResponse.raiMediaFilteredReasons.0');
                return response()->json([
                    'ok' => false,
                    'error' => is_string($rf) && $rf !== ''
                        ? 'Video ditolak penyaring keamanan: '.$rf
                        : 'Video selesai tapi URI hasilnya tidak ditemukan.',
                ], 502);
            }

            // URI video butuh key agar bisa diputar di <video> — key milik user sendiri.
            $uri .= (str_contains($uri, '?') ? '&' : '?').'key='.$key;

            return response()->json(['ok' => true, 'done' => true, 'uri' => $uri]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Gagal mengecek status video: '.$e->getMessage()], 502);
        }
    }

    /**
     * API key Gemini aktif: dari ai_settings (driver gemini, is_default dulu),
     * fallback config('ai.providers.gemini.api_key').
     */
    protected function geminiKey(): ?string
    {
        try {
            $setting = AiSetting::query()
                ->where('is_active', true)
                ->where('driver', 'gemini')
                ->orderByDesc('is_default')
                ->first();

            if ($setting !== null) {
                $config = method_exists($setting, 'config') ? $setting->config() : [];
                $key = trim((string) ($config['api_key'] ?? ''));
                if ($key !== '') {
                    return $key;
                }
            }
        } catch (Throwable) {
            // tabel belum dimigrasi — lanjut ke config
        }

        $key = trim((string) config('ai.providers.gemini.api_key', ''));

        return $key !== '' ? $key : null;
    }

    /** Pesan error Google diterjemahkan ke bahasa Indonesia per status HTTP. */
    protected function mapError(int $status, ?string $googleMessage): ?string
    {
        if ($status >= 200 && $status < 300) {
            return null;
        }

        return match (true) {
            $status === 400 => 'Permintaan ditolak (400). '.($googleMessage ?: 'Cek prompt — mungkin terlalu panjang atau menyalahi aturan.'),
            $status === 401 || $status === 403 => in_array($status, [401, 403], true) && str_contains((string) $googleMessage, 'VEO')
                ? 'API key kamu belum punya akses Veo — generate video butuh key berbayar (billing aktif di Google AI Studio).'
                : 'API key ditolak (403/401). Salin ulang key PENUH dari aistudio.google.com/api-keys — jangan terpotong.',
            $status === 404 => 'Model tidak ditemukan (404) — nama model mungkin sudah berubah. '.($googleMessage ?: ''),
            $status === 429 => 'Kuota generate habis / kena limit (429). Tunggu sebentar atau pakai key berbayar.',
            default => 'Server Google bermasalah ('.$status.'). '.($googleMessage ?: 'Coba lagi sebentar lagi.'),
        };
    }
}
