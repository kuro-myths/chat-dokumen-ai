<?php

namespace App\Providers;

use App\Services\Ai\AiManager;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiManager::class, fn () => new AiManager(config('ai', [])));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/ai.php' => config_path('ai.php'),
        ], 'skripsi-ai-config');
    }
}
