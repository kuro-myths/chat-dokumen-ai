<?php

namespace App\Services\Ai;

use App\Models\AiSetting;
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
     * Daftar semua provider (config + database) TANPA memanggil API eksternal.
     *
     * @return list<array{slug: string, name: string, driver: string, base_url: ?string, model: ?string, configured: bool, source: string, is_default: bool}>
     */
    public function providers(): array
    {
        $providers = [];

        foreach (config('ai.providers', []) as $slug => $config) {
            $providers[$slug] = [
                'slug' => $slug,
                'name' => ucfirst($slug),
                'driver' => $config['driver'] ?? 'openai-compatible',
                'base_url' => $config['base_url'] ?? null,
                'model' => $config['model'] ?? null,
                'configured' => $this->isConfigured($config),
                'source' => 'config',
                'is_default' => false,
            ];
        }

        // Provider dari database (ditambah via beranda / /api/ai/providers) menimpa config.
        try {
            foreach (AiSetting::where('is_active', true)->get() as $setting) {
                $providers[$setting->slug] = [
                    'slug' => $setting->slug,
                    'name' => $setting->name,
                    'driver' => $setting->driver,
                    'base_url' => $setting->base_url,
                    'model' => $setting->model,
                    'configured' => $this->isConfigured($setting->config()),
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

        return $slug ?? config('ai.default');
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
            return ['ok' => false, 'models' => [], 'error' => $e->getMessage()];
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
