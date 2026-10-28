<?php

namespace App\Services\Ai\Facades;

use App\Services\Ai\AiManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Services\Ai\Contracts\AiProviderInterface provider(?string $name = null)
 * @method static \App\Services\Ai\AiManager extend(string $driver, \Closure $creator)
 * @method static array providers()
 * @method static void setDefault(string $name)
 *
 * @see \App\Services\Ai\AiManager
 */
class Ai extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AiManager::class;
    }
}
