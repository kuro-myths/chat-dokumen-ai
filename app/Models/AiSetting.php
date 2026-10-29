<?php

namespace App\Models;

use App\Casts\SafeEncrypted;
use Illuminate\Database\Eloquent\Model;

/**
 * Provider AI yang ditambahkan user lewat API — kunci integrasi
 * "API apa pun": cukup slug + driver + base_url + model + api_key.
 *
 * Slug dibuat OTOMATIS dari nama (field slug di UI sudah dihapus),
 * jadi user cukup isi nama + driver + API key.
 */
class AiSetting extends Model
{
    protected $fillable = [
        'slug', 'name', 'driver', 'base_url', 'model', 'api_key', 'is_active', 'is_default',
    ];

    protected $casts = [
        // Encrypted kalau APP_KEY ada; plain (bukan error 500) kalau belum.
        'api_key' => SafeEncrypted::class,
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /** @return array{driver: string, api_key: ?string, base_url: ?string, model: ?string} */
    public function config(): array
    {
        return [
            'driver' => $this->driver,
            'api_key' => $this->api_key,
            'base_url' => $this->base_url,
            'model' => $this->model,
        ];
    }
}
