<?php

namespace App\Services\Ai;

use App\Models\AiSetting;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\Drivers\AnthropicDriver;
use App\Services\Ai\Drivers\GeminiDriver;
use App\Services\Ai\Drivers\OllamaDriver;
use App\Services\Ai\Drivers\OpenAiCompatibleDriver;
use Closure;
use InvalidArgumentException;

/**
 * Pusat integrasi AI. Semua provider — dari config maupun dari
 * database (tabel ai_settings) — dibangun lewat kelas ini.
 *
 * Integrasi API APA PUN:
 * 1. Endpoint kompatibel OpenAI  -> cukup isi driver "openai-compatible" + base_url.
 * 2. Endpoint dengan format lain -> daftarkan driver sendiri:
 *      Ai::extend('providerku', fn (string $name, array $config) => new DriverKu($name, $config));
 */
class AiManager
{
    /** @var array<string, Closure(string, array): AiProviderInterface> */
    protected array $extensions = [];

    public function __construct(protected array $config = []) {}

    public function provider(?string $name = null): AiProviderInterface
    {
        $name ??= $this->defaultName();

        // Prioritas 1: provider yang didaftarkan user via database.
        if ($setting = $this->findSetting($name)) {
            return $this->build($name, $setting->config());
        }

        // Prioritas 2: provider dari config/ai.php.
        if (! isset($this->config['providers'][$name])) {
            throw new InvalidArgumentException(
                "AI provider [{$name}] tidak terdaftar. Tersedia: ".implode(', ', $this->providers())
            );
        }

        return $this->build($name, $this->config['providers'][$name]);
    }

    /**
     * Daftarkan driver baru tanpa menyentuh core — cukup satu Closure.
     * Contoh di AppServiceProvider::boot():
     *   Ai::extend('deepseek', fn (string $name, array $cfg) => new OpenAiCompatibleDriver($name, $cfg));
     */
    public function extend(string $driver, Closure $creator): static
    {
        $this->extensions[$driver] = $creator;

        return $this;
    }

    /** @return list<string> */
    public function providers(): array
    {
        return array_keys($this->config['providers'] ?? []);
    }

    public function setDefault(string $name): void
    {
        $this->config['default'] = $name;
    }

    protected function build(string $name, array $config): AiProviderInterface
    {
        $driver = $config['driver'] ?? 'openai-compatible';

        if (isset($this->extensions[$driver])) {
            return ($this->extensions[$driver])($name, $config);
        }

        return match ($driver) {
            'openai', 'openai-compatible' => new OpenAiCompatibleDriver($name, $config),
            'anthropic' => new AnthropicDriver($name, $config),
            'gemini' => new GeminiDriver($name, $config),
            'ollama' => new OllamaDriver($name, $config),
            default => throw new InvalidArgumentException(
                "Driver [{$driver}] tidak dikenal. Gunakan Ai::extend() untuk API kustom."
            ),
        };
    }

    protected function defaultName(): string
    {
        try {
            $slug = AiSetting::query()
                ->where('is_default', true)
                ->where('is_active', true)
                ->value('slug');
        } catch (\Throwable) {
            $slug = null; // tabel belum dimigrasi
        }

        return $slug ?? $this->config['default'] ?? 'openai';
    }

    protected function findSetting(string $name): ?AiSetting
    {
        try {
            return AiSetting::query()
                ->where('slug', $name)
                ->where('is_active', true)
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }
}
