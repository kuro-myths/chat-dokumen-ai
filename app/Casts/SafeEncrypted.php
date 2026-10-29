<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Cast "encrypted" yang TIDAK PERNAH bikin server error 500:
 *
 * - APP_KEY tersedia  -> nilai disimpan terenkripsi (keamanan penuh).
 * - APP_KEY kosong    -> nilai disimpan apa adanya, supaya menyimpan
 *   API key tetap berhasil (mis. jalan lokal cepat tanpa key:generate).
 * - Membaca nilai lama yang belum terenkripsi / APP_KEY berganti ->
 *   kembalikan nilai mentahnya, bukan exception.
 *
 * Ini memperbaiki "error server" saat simpan API key Gemini/router
 * ketika APP_KEY belum di-set.
 */
class SafeEncrypted implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value; // nilai plain (belum terenkripsi) — tetap terbaca
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::encryptString($value);
        } catch (Throwable) {
            return $value; // APP_KEY belum diset — simpan plain agar tidak 500
        }
    }
}
