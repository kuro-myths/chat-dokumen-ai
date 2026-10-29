<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Katalog gratis/berbayar tiap model AI — dipakai UI untuk menampilkan
 * daftar model beserta labelnya.
 *
 * - Gemini (Google AI Studio): free tier diketahui dari katalog;
 *   model Pro & generasi 3.x berbayar.
 * - Max Router & provider kompatibel OpenAI: harga asli diambil dari
 *   endpoint /api/pricing router (input/output per 1 juta token;
 *   0-0 = gratis). Router tanpa data harga -> heuristik nama.
 */
class ModelPricing
{
    /** Model Gemini yang tersedia di FREE TIER AI Studio (batas RPM/RPD berlaku). */
    protected const GEMINI_FREE = [
        'gemini-2.5-flash',
        'gemini-2.5-flash-lite',
        'gemini-2.0-flash',
        'gemini-2.0-flash-lite',
        'gemini-1.5-flash',
        'gemini-1.5-flash-8b',
        'gemini-flash-latest',
        'gemini-flash-lite-latest',
    ];

    /**
     * Tier satu model Gemini.
     *
     * @return array{tier: string, note: string} tier: free|paid|unknown
     */
    public function gemini(string $modelId): array
    {
        $id = strtolower(trim((string) preg_replace('#^models/#', '', $modelId)));

        if (in_array($id, self::GEMINI_FREE, true)) {
            return ['tier' => 'free', 'note' => 'Free tier AI Studio — gratis dengan batas RPM/RPD.'];
        }

        if (str_contains($id, 'embedding') || str_contains($id, 'aqa') || str_starts_with($id, 'gemma')
            || str_contains($id, 'imagen') || str_contains($id, 'veo') || str_contains($id, 'tts')
            || str_contains($id, 'image')) {
            return ['tier' => 'free', 'note' => 'Model pendukung / open model — gratis di AI Studio.'];
        }

        if (str_contains($id, 'thinking') || str_contains($id, 'experimental') || str_contains($id, '-exp')) {
            return ['tier' => 'free', 'note' => 'Varian experimental — umumnya gratis di AI Studio.'];
        }

        if (str_starts_with($id, 'gemini-3')) {
            return ['tier' => 'paid', 'note' => 'Generasi Gemini 3.x (preview) — hanya tier berbayar.'];
        }

        if (str_contains($id, 'pro')) {
            return ['tier' => 'paid', 'note' => 'Model Pro — free tier sangat terbatas / butuh kuota berbayar.'];
        }

        return ['tier' => 'unknown', 'note' => 'Status kuota belum diketahui — cek aistudio.google.com.'];
    }

    /**
     * Harga asli dari Max Router: GET /api/pricing
     * Bentuk: {provider: {model: {input: number, output: number}}}
     * (per 1 juta token; 0-0 = gratis).
     *
     * @return array<string, array{tier: string, input: float, output: float>>|null
     */
    public function routerPricing(?string $baseUrl, ?string $apiKey): ?array
    {
        $base = trim((string) $baseUrl);
        $root = rtrim((string) preg_replace('#/(v\d+(beta)?)$#', '', $base), '/');

        if ($root === '' || ! str_starts_with($root, 'http')) {
            return null;
        }

        try {
            $request = Http::timeout(10)->acceptJson();

            if (filled($apiKey)) {
                $request = $request->withToken($apiKey);
            }

            $response = $request->get($root.'/api/pricing');

            if (! $response->ok()) {
                return null;
            }

            $map = [];

            foreach ($response->json() as $models) {
                if (! is_array($models)) {
                    continue;
                }

                foreach ($models as $model => $rates) {
                    if (! is_array($rates)) {
                        continue;
                    }

                    $input = (float) ($rates['input'] ?? 0);
                    $output = (float) ($rates['output'] ?? 0);

                    $map[strtolower((string) $model)] = [
                        'tier' => ($input <= 0 && $output <= 0) ? 'free' : 'paid',
                        'input' => $input,
                        'output' => $output,
                    ];
                }
            }

            return $map;
        } catch (Throwable) {
            return null; // router versi lama / offline — pakai heuristik
        }
    }

    /**
     * Heuristik untuk provider kompatibel OpenAI tanpa data harga.
     *
     * @return array{tier: string, note: string}
     */
    public function heuristic(string $modelId): array
    {
        $id = strtolower($modelId);

        if (str_contains($id, ':free')) {
            return ['tier' => 'free', 'note' => 'Varian gratis (suffix :free, mis. OpenRouter).'];
        }

        return ['tier' => 'unknown', 'note' => 'Gratis/berbayar tergantung kuota provider — cek dasbor router.'];
    }
}
