<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVoiceNoteRequest;
use App\Models\AiSetting;
use App\Models\Document;
use App\Services\Voice\VoiceTranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class VoiceController extends Controller
{
    protected const BASE = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Auto Voice Input: unggah rekaman suara -> transkripsi otomatis
     * -> (opsional) rapikan bahasa -> langsung masuk dokumen.
     */
    public function store(StoreVoiceNoteRequest $request, VoiceTranscriptionService $voice): JsonResponse
    {
        $document = $request->filled('document_id')
            ? Document::findOrFail($request->input('document_id'))
            : null;

        $transcription = $voice->handle(
            $request->file('audio'),
            $document,
            $request->input('section_index'),
            [
                'duration' => $request->input('duration'),
                'mode' => $request->input('mode', 'append'),
            ],
        );

        return response()->json([
            'message' => 'Transkripsi selesai'.($document ? ' dan teks dimasukkan ke dokumen.' : '.'),
            'text' => $transcription->text,
            'document' => $document?->fresh(['sections']),
        ], 201);
    }

    /**
     * Suara Kuro (TTS): teks -> suara Indonesia ala karakter anime via Gemini.
     * POST /api/voice/speak {text, voice(preset)} -> {ok, audio(data URI wav), label}.
     * Gemini TTS mengembalikan PCM mentah (audio/L16) — dibungkus jadi WAV
     * supaya bisa langsung diputar browser. Bila API key belum ada, respons
     * need_key=true dan frontend memakai suara browser sebagai fallback.
     */
    public function speak(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:6000'],
            'voice' => ['nullable', 'string', 'max:40'],
        ]);

        $text = trim((string) $data['text']);
        $max = (int) config('ai.tts.max_chars', 4000);
        if (mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max).'…';
        }
        if ($text === '') {
            return response()->json(['ok' => false, 'error' => 'Teksnya masih kosong.'], 422);
        }

        $key = $this->geminiKey();
        if ($key === null) {
            return response()->json([
                'ok' => false,
                'need_key' => true,
                'error' => 'Suara AI ala anime butuh API key Gemini. Buka menu API Key, simpan key kamu (AIza... / AQ.Ab8...), lalu coba lagi.',
            ], 422);
        }

        $voices = (array) config('ai.tts.voices', []);
        $voiceId = (string) ($data['voice'] ?? config('ai.tts.default_voice', 'anime'));
        if (! isset($voices[$voiceId])) {
            $voiceId = 'anime';
        }
        $preset = $voices[$voiceId];

        $model = (string) config('ai.tts.model', 'gemini-2.5-flash-preview-tts');
        $voiceName = (string) ($preset['voice'] ?? 'Leda');
        $style = (string) ($preset['style'] ?? 'Read aloud in Indonesian, friendly and clear');

        $prompt = $style.': "'.$text.'"';

        try {
            $response = Http::timeout(90)
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => $key])
                ->post(self::BASE."/models/{$model}:generateContent", [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'responseModalities' => ['AUDIO'],
                        'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voiceName]]],
                    ],
                ]);

            if ($err = $this->mapError($response->status(), $response->json('error.message'))) {
                return response()->json(['ok' => false, 'error' => $err], $response->status());
            }

            $part = $response->json('candidates.0.content.parts.0');
            $inline = $part['inlineData'] ?? null;

            if (! is_array($inline) || empty($inline['data'])) {
                return response()->json(['ok' => false, 'error' => 'Model tidak menghasilkan audio — coba teks yang lebih pendek.'], 502);
            }

            $mime = (string) ($inline['mimeType'] ?? 'audio/L16;rate=24000');

            if (str_contains($mime, 'wav')) {
                $audio = 'data:audio/wav;base64,'.$inline['data'];
            } else {
                $rate = 24000;
                if (preg_match('/rate=(\d+)/', $mime, $m)) {
                    $rate = (int) $m[1];
                }
                $wav = $this->pcmToWav(base64_decode((string) $inline['data']), $rate);
                if ($wav === null) {
                    return response()->json(['ok' => false, 'error' => 'Audio dari AI kosong — coba lagi.'], 502);
                }
                $audio = 'data:audio/wav;base64,'.base64_encode($wav);
            }

            return response()->json([
                'ok' => true,
                'audio' => $audio,
                'voice' => $voiceId,
                'label' => (string) ($preset['label'] ?? $voiceName),
                'model' => $model,
            ]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Gagal menghubungi Gemini TTS: '.$e->getMessage()], 502);
        }
    }

    /** PCM 16-bit mono -> file WAV (header RIFF) supaya bisa diputar browser. */
    protected function pcmToWav(string $pcm, int $rate = 24000, int $channels = 1, int $bits = 16): ?string
    {
        if ($pcm === '') {
            return null;
        }

        $blockAlign = (int) ($channels * $bits / 8);
        $byteRate = $rate * $blockAlign;
        $len = strlen($pcm);

        return 'RIFF'.pack('V', 36 + $len).'WAVE'
            .'fmt '.pack('V', 16).pack('v', 1).pack('v', $channels)
            .pack('V', $rate).pack('V', $byteRate)
            .pack('v', $blockAlign).pack('v', $bits)
            .'data'.pack('V', $len)
            .$pcm;
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
            $status === 400 => 'Permintaan ditolak (400). '.($googleMessage ?: 'Cek teksnya — mungkin terlalu panjang atau menyalahi aturan.'),
            $status === 401 || $status === 403 => 'API key ditolak (403/401). Salin ulang key PENUH dari aistudio.google.com/api-keys — jangan terpotong.',
            $status === 404 => 'Model TTS tidak ditemukan (404) — nama model mungkin sudah berubah. '.($googleMessage ?: ''),
            $status === 429 => 'Kuota suara AI habis / kena limit (429). Tunggu sebentar lalu coba lagi.',
            default => 'Server Google bermasalah ('.$status.'). '.($googleMessage ?: 'Coba lagi sebentar lagi.'),
        };
    }
}
