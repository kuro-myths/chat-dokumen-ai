<?php

namespace App\Services\Ai;

use App\Models\AiSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sinkronisasi chat-dokumen-ai (Kuro AI) dengan Max Router (9Router V3).
 *
 * - ONLINE  : router yang di-deploy (Railway) — MAXROUTER_ONLINE_URL.
 * - OFFLINE : router lokal di komputer sendiri — MAXROUTER_BASE_URL
 *             (default http://localhost:3001/v1).
 *
 * probe()  : cek /api/health tiap kandidat (tanpa API key).
 * sync()   : pilih router aktif (online duluan, gagal → lokal),
 *            tarik /v1/models (sekalian label gratis/berbayar dari
 *            data harga router), daftarkan aplikasi ini ke router
 *            (POST /api/dokumen-ai/sync), lalu perbarui provider
 *            "manual" agar chat/review AI tetap jalan dengan base URL
 *            yang sedang aktif — otomatis, online maupun offline.
 */
class MaxRouterService
{
    /** Base URL lokal default (9Router V3 berjalan di PORT 3001). */
    public const LOCAL_DEFAULT = 'http://localhost:3001/v1';

    protected function timeout(): int
    {
        return max(2, (int) config('ai.router.timeout', 5));
    }

    /** Kandidat router: online dulu, lalu lokal. */
    public function candidates(): array
    {
        $candidates = [];

        $online = trim((string) config('ai.router.online_url'));
        if ($online !== '') {
            $candidates['online'] = $this->normalize($online);
        }

        $local = trim((string) config('ai.router.local_url'));
        $candidates['local'] = $this->normalize($local !== '' ? $local : self::LOCAL_DEFAULT);

        return $candidates;
    }

    /** Pastikan URL berbentuk base endpoint kompatibel OpenAI (.../v1). */
    protected function normalize(string $url): string
    {
        $url = rtrim(trim($url), '/');

        if (! preg_match('#/v\d+(beta)?$#', $url)) {
            $url .= '/v1';
        }

        return $url;
    }

    /** Akar URL (tanpa /v1) untuk /api/health dan /api/dokumen-ai/*. */
    protected function root(string $baseUrl): string
    {
        return rtrim((string) preg_replace('#/(v\d+(beta)?)$#', '', $baseUrl), '/');
    }

    /** Cek satu router: /api/health + latensi. */
    protected function check(string $baseUrl): array
    {
        $start = microtime(true);

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->get($this->root($baseUrl).'/api/health');

            return [
                'base_url' => $baseUrl,
                'reachable' => $response->ok(),
                'status' => $response->json('status'),
                'version' => $response->json('version'),
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        } catch (Throwable) {
            return [
                'base_url' => $baseUrl,
                'reachable' => false,
                'status' => null,
                'version' => null,
                'latency_ms' => null,
                'error' => 'Tidak dapat dijangkau.',
            ];
        }
    }

    /**
     * Probe semua kandidat router (online & lokal) sekaligus.
     *
     * @return array{online: ?array, local: ?array, active_scope: ?string, active_base_url: ?string, checked_at: string}
     */
    public function probe(): array
    {
        $result = [];

        foreach ($this->candidates() as $scope => $baseUrl) {
            $result[$scope] = $this->check($baseUrl);
        }

        $active = null;

        foreach ($result as $scope => $info) {
            if (($info['reachable'] ?? false) === true) {
                $active = $scope;
                break;
            }
        }

        return [
            'online' => $result['online'] ?? null,
            'local' => $result['local'] ?? null,
            'active_scope' => $active,
            'active_base_url' => $active !== null ? $result[$active]['base_url'] : null,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /** Base URL router aktif (online duluan, fallback lokal), atau null. */
    public function resolve(): ?string
    {
        return $this->probe()['active_base_url'];
    }

    /** Status untuk GET /api/ai/router/status. */
    public function status(): array
    {
        return [
            'ok' => true,
            'router' => $this->probe(),
            'scope' => $this->cacheGet('maxrouter.scope'),
            'synced_at' => $this->cacheGet('maxrouter.synced_at'),
            'models_cached' => $this->cacheGet('maxrouter.models', []),
        ];
    }

    /**
     * Sinkron penuh dengan Max Router (dipanggil POST /api/ai/router/sync):
     * 1. pilih scope (prefer user: online/local; auto = online duluan);
     * 2. tarik daftar model /v1/models + label gratis/berbayar;
     * 3. daftarkan aplikasi ini ke router (POST /api/dokumen-ai/sync);
     * 4. perbarui provider "manual" di tabel ai_settings.
     *
     * @return array{ok: bool, ...}
     */
    public function sync(string $prefer = 'auto'): array
    {
        $probe = $this->probe();

        $scope = null;

        if (in_array($prefer, ['online', 'local'], true)
            && ($probe[$prefer]['reachable'] ?? false) === true) {
            $scope = $prefer;
        }

        $scope ??= $probe['active_scope'];

        if ($scope === null) {
            return [
                'ok' => false,
                'error' => 'Max Router tidak dapat dijangkau. Cek MAXROUTER_ONLINE_URL di .env '
                    .'atau pastikan router lokal berjalan (default http://localhost:3001).',
                'router' => $probe,
            ];
        }

        $baseUrl = $probe[$scope]['base_url'];
        $apiKey = $this->apiKey();

        try {
            $request = Http::timeout(30)->acceptJson();

            if ($apiKey !== '') {
                $request = $request->withToken($apiKey);
            }

            $response = $request->get($baseUrl.'/models');
            $response->throw();

            // Label gratis/berbayar dari data harga router (GET /api/pricing).
            $pricingMap = app(ModelPricing::class)->routerPricing($baseUrl, $apiKey);

            $models = collect($response->json('data', []))
                ->map(fn ($m) => ['id' => $m['id'] ?? ($m['name'] ?? null)])
                ->filter(fn ($m) => filled($m['id']))
                ->map(function ($m) use ($pricingMap) {
                    $p = $pricingMap[strtolower((string) $m['id'])] ?? null;

                    if ($p === null) {
                        return $m + ['tier' => 'unknown'];
                    }

                    return $m + [
                        'tier' => $p['tier'],
                        'price' => ['input' => $p['input'], 'output' => $p['output']],
                    ];
                })
                ->values()
                ->all();
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => 'Gagal menarik model dari Max Router: '.$e->getMessage(),
                'router' => $probe,
                'scope' => $scope,
            ];
        }

        $this->cachePut('maxrouter.models', $models);
        $this->cachePut('maxrouter.synced_at', now()->toIso8601String());
        $this->cachePut('maxrouter.scope', $scope);

        // Daftarkan aplikasi ini sebagai konsumen router (sinkron dua arah).
        $registry = $this->registerOnRouter($baseUrl, $apiKey);

        // Perbarui provider "manual" — AI jalan via router aktif (online/offline otomatis).
        $this->applyToManual($baseUrl, $models, $apiKey);

        return [
            'ok' => true,
            'scope' => $scope,
            'base_url' => $baseUrl,
            'count' => count($models),
            'models' => array_slice($models, 0, 200),
            'registry' => $registry,
            'synced_at' => now()->toIso8601String(),
            'router' => $probe,
            'message' => 'Sinkron berhasil via '.($scope === 'online' ? 'router ONLINE (Railway)' : 'router LOKAL')
                .' — '.count($models).' model dari '.$baseUrl,
        ];
    }

    /** POST /api/dokumen-ai/sync di router — daftarkan konsumen ini. */
    protected function registerOnRouter(string $baseUrl, string $apiKey): ?array
    {
        try {
            $request = Http::timeout(10)->acceptJson();

            if ($apiKey !== '') {
                $request = $request->withToken($apiKey);
            }

            $response = $request->post($this->root($baseUrl).'/api/dokumen-ai/sync', [
                'app' => 'chat-dokumen-ai',
                'name' => 'Kuro AI',
                'base_url' => rtrim(config('app.url'), '/'),
                'mode' => $this->mode(),
                'model' => $this->manualModel(),
            ]);

            return $response->ok() ? $response->json() : null;
        } catch (Throwable) {
            // Router versi lama tanpa endpoint ini — biarkan sinkron model tetap jalan.
            return null;
        }
    }

    /** Perbarui baris provider "manual" di ai_settings. */
    protected function applyToManual(string $baseUrl, array $models, string $apiKey): void
    {
        try {
            $manual = AiSetting::where('slug', 'manual')->first();

            $payload = [
                'name' => 'Manual (Max Router)',
                'driver' => 'openai-compatible',
                'base_url' => $baseUrl,
                'is_active' => true,
            ];

            if ($manual !== null && $manual->model !== null) {
                $payload['model'] = $manual->model;
            } elseif (! empty($models)) {
                $payload['model'] = $models[0]['id'];
            } else {
                $payload['model'] = config('ai.router.model');
            }

            if ($apiKey !== '') {
                $payload['api_key'] = $apiKey;
            } elseif ($manual !== null && filled($manual->api_key)) {
                $payload['api_key'] = $manual->api_key;
            }

            AiSetting::updateOrCreate(['slug' => 'manual'], $payload);
        } catch (Throwable) {
            // tabel belum dimigrasi — abaikan
        }
    }

    /** API key router: dari baris "manual" dulu, lalu env MAXROUTER_API_KEY. */
    protected function apiKey(): string
    {
        try {
            $key = (string) AiSetting::where('slug', 'manual')->value('api_key');

            if (trim($key) !== '') {
                return trim($key);
            }
        } catch (Throwable) {
            // tabel belum dimigrasi
        }

        return trim((string) config('ai.router.api_key'));
    }

    protected function manualModel(): ?string
    {
        try {
            return AiSetting::where('slug', 'manual')->value('model');
        } catch (Throwable) {
            return null;
        }
    }

    /** Mode AI aktif: 'gemini' atau 'manual' (dua dropdown di beranda). */
    public function mode(): string
    {
        try {
            $slug = AiSetting::query()
                ->where('is_default', true)
                ->where('is_active', true)
                ->value('slug');

            if (in_array($slug, ['gemini', 'manual'], true)) {
                return $slug;
            }
        } catch (Throwable) {
            // tabel belum dimigrasi
        }

        $mode = (string) config('ai.mode', 'gemini');

        return in_array($mode, ['gemini', 'manual'], true) ? $mode : 'gemini';
    }

    // ---------- Cache ringan (aman walau tabel cache belum ada) ----------

    protected function cacheGet(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::get($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }

    protected function cachePut(string $key, mixed $value): void
    {
        try {
            Cache::put($key, $value, now()->addDay());
        } catch (Throwable) {
            // abaikan
        }
    }
}
