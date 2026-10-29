<?php

namespace App\Services\Ai;

use App\Models\AiSetting;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Daftar model AI yang tersedia di tiap provider.
 * Mendukung semua driver bawaan: Gemini, OpenAI/kompatibel (9Router,
 * OpenRouter, DeepSeek, Groq, Mistral, vLLM, dll.), Anthropic, dan Ollama.
 */
class AiModelService
{
    protected const GEMINI_BASE = 'https://generativelanguage.googleapis.com/v1beta';

    protected const ANTHROPIC_BASE = 'https://api.anthropic.com/v1';

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

        // Provider dari database (ditambah via /api/ai/providers) menimpa config.
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
     * Ambil daftar model satu provider dari API-nya.
     *
     * @return array{ok: bool, models: list<array{id: string, name: ?string}>, error: ?string}
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
                'error' => 'Provider belum dikonfigurasi (API key / base URL masih kosong di .env).',
            ];
        }

        try {
            $driver = $config['driver'] ?? 'openai-compatible';

            $models = match ($driver) {
                'gemini' => $this->geminiModels($config['api_key'] ?? ''),
                'anthropic' => $this->anthropicModels($config['api_key'] ?? ''),
                'ollama' => $this->ollamaModels($config['base_url'] ?? 'http://127.0.0.1:11434'),
                'openai', 'openai-compatible' => $this->openAiModels(
                    $config['base_url'] ?? 'https://api.openai.com/v1',
                    $config['api_key'] ?? '',
                ),
                default => throw new \InvalidArgumentException("Driver [{$driver}] tidak dikenal."),
            };
        } catch (Throwable $e) {
            return ['ok' => false, 'models' => [], 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'models' => $models, 'error' => null];
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
            'ollama' => true, // lokal, tanpa API key
            'gemini', 'anthropic' => $key !== '',
            'openai' => $key !== '',
            default => $key !== '' && ! empty($config['base_url']),
        };
    }

    /** Gemini: GET /v1beta/models?key=... */
    protected function geminiModels(string $apiKey): array
    {
        $response = Http::timeout(20)
            ->acceptJson()
            ->get(self::GEMINI_BASE.'/models', ['key' => $apiKey, 'pageSize' => 200]);

        $response->throw();

        $models = collect($response->json('models', []))
            ->filter(fn ($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true))
            ->map(fn ($m) => [
                'id' => str_replace('models/', '', (string) ($m['name'] ?? '')),
                'name' => $m['displayName'] ?? null,
            ])
            ->values()
            ->all();

        return $models;
    }

    /** OpenAI & semua endpoint kompatibel (9Router, OpenRouter, Groq, dll.): GET {base}/models */
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

    /** Anthropic: GET /v1/models */
    protected function anthropicModels(string $apiKey): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(20)
            ->acceptJson()
            ->get(self::ANTHROPIC_BASE.'/models', ['limit' => 100]);

        $response->throw();

        return collect($response->json('data', []))
            ->map(fn ($m) => ['id' => $m['id'] ?? '?', 'name' => $m['display_name'] ?? null])
            ->values()
            ->all();
    }

    /** Ollama lokal: GET /api/tags */
    protected function ollamaModels(string $baseUrl): array
    {
        $response = Http::timeout(10)
            ->acceptJson()
            ->get(rtrim($baseUrl, '/').'/api/tags');

        $response->throw();

        return collect($response->json('models', []))
            ->map(fn ($m) => ['id' => $m['name'] ?? $m['model'] ?? '?', 'name' => $m['details']['family'] ?? null])
            ->values()
            ->all();
    }
}
