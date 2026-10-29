<?php

namespace App\Services\Ai;

use App\Models\AiSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Daftar model AI yang tersedia di tiap provider — sekalian LABEL
 * GRATIS / BERBAYAR per model (lihat ModelPricing).
 * Driver gemini -> Google AI Studio; driver openai-compatible
 * (Max Router, OpenRouter, DeepSeek, dll.) -> endpoint /models.
 */
class AiModelService
{
    protected const GEMINI_BASE = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Daftar provider yang benar-benar siap dipakai (config yang sudah
     * dikonfigurasi + database) TANPA memanggil API eksternal.
     *
     * Provider tanpa API key / base URL (titik merah di UI) disembunyikan
     * dari daftar — otomatis tampil kembali begitu env / API key-nya diisi.
     *
     * @return list<array{slug: string, name: string, driver: string, base_url: ?string, model: ?string, configured: bool, source: string, is_default: bool}>
     */
    public function providers(): array
    {
        $providers = [];

        foreach (config('ai.providers', []) as $slug => $config) {
            // Provider config tanpa API key / base URL tidak ditampilkan
            // (muncul otomatis lagi setelah env-nya diisi di server).
            if (! $this->isConfigured($config)) {
                continue;
            }

            $providers[$slug] = [
                'slug' => $slug,
                'name' => ucfirst($slug),
                'driver' => $config['driver'] ?? 'openai-compatible',
                'base_url' => $config['base_url'] ?? null,
                'model' => $config['model'] ?? null,
                'configured' => true,
                'source' => 'config',
                'is_default' => false,
            ];
        }

        // Provider dari database (ditambah via beranda / /api/ai/providers) menimpa config.
        try {
            foreach (AiSetting::where('is_active', true)->get() as $setting) {
                // Baris database tanpa API key / base URL juga disembunyikan.
                if (! $this->isConfigured($setting->config())) {
                    continue;
                }

                $providers[$setting->slug] = [
                    'slug' => $setting->slug,
                    'name' => $setting->name,
                    'driver' => $setting->driver,
                    'base_url' => $setting->base_url,
                    'model' => $setting->model,
                    'configured' => true,
                    'source' => 'database',
                    'is_default' => $setting->is_default,
                ];
            }
        } catch (Throwable) {
            // tabel belum dimigrasi — abaikan
        }

        $default = $this->defaultSlug();
        if ($default !== null && isset($providers[$default])) {
            $providers[$default]['is_default'] = true;
        }

        return array_values($providers);
    }

    public function defaultSlug(): ?string
    {
        try {
            $slug = AiSetting::query()
                ->where('is_default', true)
                ->where('is_active', true)
                ->value('slug');
        } catch (Throwable) {
            $slug = null;
        }

        if (is_string($slug) && $slug !== '') {
            return $slug;
        }

        $usable = $this->usableSlugs();
        $fallback = config('ai.default');

        // Default dari config yang belum dikonfigurasi (titik merah) jangan
        // dikembalikan — pakai provider pertama yang benar-benar siap dipakai.
        if (is_string($fallback) && $fallback !== '' && isset($usable[$fallback])) {
            return $fallback;
        }

        return array_key_first($usable);
    }

    /**
     * Slug semua provider yang benar-benar siap dipakai: config yang sudah
     * dikonfigurasi + baris ai_settings yang sudah dikonfigurasi.
     *
     * @return array<string, true>
     */
    protected function usableSlugs(): array
    {
        $slugs = [];

        foreach (config('ai.providers', []) as $slug => $config) {
            if ($this->isConfigured($config)) {
                $slugs[$slug] = true;
            }
        }

        try {
            foreach (AiSetting::where('is_active', true)->get() as $setting) {
                if ($this->isConfigured($setting->config())) {
                    $slugs[$setting->slug] = true;
                }
            }
        } catch (Throwable) {
            // tabel belum dimigrasi — abaikan
        }

        return $slugs;
    }

    /**
     * Ambil daftar model satu provider dari API-nya, lengkap dengan
     * tier gratis/berbayar per model.
     *
     * @return array{ok: bool, models: list<array{id: string, name: ?string, tier: string, note?: string, price?: array{input: float, output: float}}>, error: ?string, pricing_source?: string}
     */
    public function models(string $slug): array
    {
        $config = $this->configFor($slug);

        if ($config === null) {
            return ['ok' => false, 'models' => [], 'error' => "Provider [{$slug}] tidak terdaftar."];
        }

        if (! $this->isConfigured($config)) {
            return [
                'ok' => false,
                'models' => [],
                'error' => 'Provider belum dikonfigurasi (API key / base URL masih kosong).',
            ];
        }

        $pricing = app(ModelPricing::class);

        try {
            $driver = $config['driver'] ?? 'openai-compatible';

            if ($driver === 'gemini') {
                $raw = $this->geminiModels($config['api_key'] ?? '');

                $models = collect($raw)
                    ->map(fn ($m) => $m + $pricing->gemini((string) $m['id']))
                    ->values()
                    ->all();

                return ['ok' => true, 'models' => $models, 'error' => null, 'pricing_source' => 'katalog AI Studio'];
            }

            $raw = $this->openAiModels(
                $config['base_url'] ?? 'https://api.openai.com/v1',
                $config['api_key'] ?? '',
            );

            // Harga asli dari router (Max Router punya GET /api/pricing).
            $routerPricing = $pricing->routerPricing($config['base_url'] ?? null, $config['api_key'] ?? null);

            $models = collect($raw)
                ->map(function ($m) use ($pricing, $routerPricing) {
                    $p = $routerPricing[strtolower((string) $m['id'])] ?? null;

                    if ($p !== null) {
                        return [
                            'id' => $m['id'],
                            'name' => $m['name'],
                            'tier' => $p['tier'],
                            'price' => ['input' => $p['input'], 'output' => $p['output']],
                            'note' => $p['tier'] === 'free'
                                ? 'Gratis menurut data harga router (input/output = 0).'
                                : 'Berbayar — $'.$p['input'].' input / $'.$p['output'].' output per 1 juta token.',
                        ];
                    }

                    return $m + $pricing->heuristic((string) $m['id']);
                })
                ->values()
                ->all();

            return [
                'ok' => true,
                'models' => $models,
                'error' => null,
                'pricing_source' => $routerPricing !== null ? 'data harga router' : 'heuristik nama model',
            ];
        } catch (Throwable $e) {
            // Live fetch gagal (router sibuk / ditolak sementara) — untuk provider
            // kompatibel OpenAI, pakai model dari cache sinkron router terakhir
            // supaya dropdown model di beranda tetap terisi datanya.
            if (($config['driver'] ?? 'openai-compatible') !== 'gemini') {
                $cached = $this->cachedRouterModels($config['base_url'] ?? null);

                if ($cached !== null) {
                    return [
                        'ok' => true,
                        'models' => $cached,
                        'error' => null,
                        'pricing_source' => 'cache sinkron router terakhir (live gagal: '
                            .mb_substr($e->getMessage(), 0, 120).')',
                    ];
                }
            }

            return ['ok' => false, 'models' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Model dari cache sinkron Max Router terakhir — dipakai bila GET /models
     * live sedang gagal. Hanya dikembalikan kalau provider ini mengarah ke
     * router yang sama dengan hasil sinkron terakhir.
     *
     * @return list<array<mixed>>|null
     */
    protected function cachedRouterModels(?string $baseUrl): ?array
    {
        try {
            $models = Cache::get('maxrouter.models');

            if (! is_array($models) || $models === []) {
                return null;
            }

            $cachedBase = trim((string) Cache::get('maxrouter.base_url', ''));

            if ($cachedBase === '' || ! is_string($baseUrl) || trim((string) $baseUrl) === '') {
                return $models; // cache lama tanpa base URL — tetap berikan
            }

            return rtrim(trim((string) $baseUrl), '/') === $cachedBase ? $models : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return ?array{driver: string, api_key: ?string, base_url: ?string, model: ?string} */
    protected function configFor(string $slug): ?array
    {
        try {
            $setting = AiSetting::where('slug', $slug)->where('is_active', true)->first();

            if ($setting !== null) {
                return $setting->config();
            }
        } catch (Throwable) {
            // tabel belum dimigrasi — lanjut ke config
        }

        $config = config("ai.providers.{$slug}");

        return is_array($config) ? $config : null;
    }

    protected function isConfigured(array $config): bool
    {
        $driver = $config['driver'] ?? 'openai-compatible';
        $key = trim((string) ($config['api_key'] ?? ''));

        return match ($driver) {
            'gemini' => $key !== '',
            default => $key !== '' && ! empty($config['base_url']),
        };
    }

    /** Gemini: GET /v1beta/models — key dikirim via header x-goog-api-key. */
    protected function geminiModels(string $apiKey): array
    {
        $response = Http::timeout(20)
            ->acceptJson()
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->get(self::GEMINI_BASE.'/models', ['pageSize' => 200]);

        $response->throw();

        return collect($response->json('models', []))
            ->filter(fn ($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true))
            ->map(fn ($m) => [
                'id' => str_replace('models/', '', (string) ($m['name'] ?? '')),
                'name' => $m['displayName'] ?? null,
            ])
            ->values()
            ->all();
    }

    /** Multi-API (Max Router, OpenRouter, DeepSeek, dll.): GET {base}/models */
    protected function openAiModels(string $baseUrl, string $apiKey): array
    {
        $response = Http::withToken($apiKey)
            ->timeout(20)
            ->acceptJson()
            ->get(rtrim($baseUrl, '/').'/models');

        $response->throw();

        return collect($response->json('data', []))
            ->map(fn ($m) => ['id' => $m['id'] ?? $m['name'] ?? '?', 'name' => null])
            ->sortBy('id')
            ->values()
            ->all();
    }
}
