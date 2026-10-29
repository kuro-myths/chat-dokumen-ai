<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider AI default
    |--------------------------------------------------------------------------
    | Provider bisa berasal dari config ini, atau dari tabel ai_settings
    | (API apa pun yang ditambahkan user lewat beranda / /api/ai/providers).
    | Driver tersedia: "gemini" dan "openai-compatible" (multi-API).
    */

    'default' => env('AI_PROVIDER', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | Mode AI — dua dropdown di beranda (tidak bentrok)
    |--------------------------------------------------------------------------
    | "gemini" -> pakai API key Google AI Studio (aistudio.google.com/api-keys).
    | "manual" -> pakai Max Router / API kompatibel OpenAI (online / lokal).
    | Mode juga tersimpan di tabel ai_settings (is_default), jadi nilai env
    | ini hanya fallback awal sebelum user menyimpan setup pertama kali.
    */

    'mode' => env('AI_MODE', env('AI_PROVIDER', 'gemini')),

    /*
    |--------------------------------------------------------------------------
    | Halaman masuk (web)
    |--------------------------------------------------------------------------
    | Password untuk halaman /login. Default: 123456789.
    | Ubah lewat APP_LOGIN_PASSWORD di .env.
    */

    'login' => [
        'password' => env('APP_LOGIN_PASSWORD', '123456789'),
    ],

    'providers' => [

        // Google Gemini — driver khusus Gemini.
        'gemini' => [
            'driver' => 'gemini',
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        ],

        // 9Router (gateway AI multi-provider, kompatibel OpenAI — lokal atau online).
        // Isi NINEROUTER_BASE_URL dengan URL instance 9Router kamu,
        // mis. https://instance-9router.up.railway.app/v1
        '9router' => [
            'driver' => 'openai-compatible',
            'api_key' => env('NINEROUTER_API_KEY'),
            'base_url' => env('NINEROUTER_BASE_URL'),
            'model' => env('NINEROUTER_MODEL', 'gpt-4o-mini'),
        ],

        // MULTI-API: provider mana pun dengan endpoint kompatibel OpenAI
        // (OpenRouter, DeepSeek, OpenAI, Groq, Mistral, vLLM, dsb.) —
        // cukup base_url + api_key + model.
        'custom' => [
            'driver' => 'openai-compatible',
            'api_key' => env('CUSTOM_AI_API_KEY'),
            'base_url' => env('CUSTOM_AI_BASE_URL'),
            'model' => env('CUSTOM_AI_MODEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Max Router (9Router V3) — sinkron ONLINE + OFFLINE
    |--------------------------------------------------------------------------
    | online_url : instance router yang di-deploy (Railway) — dicek duluan.
    | local_url  : router lokal di komputer sendiri (fallback saat offline).
    | Saat sync, provider "manual" otomatis memakai base URL router aktif,
    | jadi chat/review AI tetap jalan baik online maupun lokal.
    */

    'router' => [
        'online_url' => env('MAXROUTER_ONLINE_URL'),
        'local_url' => env('MAXROUTER_BASE_URL', 'http://localhost:3001/v1'),
        'api_key' => env('MAXROUTER_API_KEY'),
        'model' => env('MAXROUTER_MODEL', 'gpt-4o-mini'),
        'timeout' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Review skripsi / proposal
    */
    'review' => [
        'batch_sections' => 8,          // jumlah paragraf per batch request
        'max_issues_per_batch' => 25,   // batas temuan per batch
        'temperature' => 0.2,
        'timeout' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto voice input (speech-to-text)
    | Endpoint kompatibel Whisper: OpenAI, Groq, lokal (whisper.cpp), dsb.
    */
    'voice' => [
        'base_url' => env('VOICE_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('VOICE_API_KEY', env('OPENAI_API_KEY')),
        'model' => env('VOICE_MODEL', 'whisper-1'),
        'language' => 'id',
        'auto_insert' => true,         // otomatis masukkan hasil ke dokumen
        'polish_with_ai' => true,      // rapikan ke bahasa akademik via LLM
    ],

    /*
    |--------------------------------------------------------------------------
    | Warna highlight per jenis temuan (format hex untuk PhpWord)
    */
    'highlight' => [
        'colors' => [
            'grammar' => 'FF0000',     // merah: bahasa / typo / plagiarisme
            'citation' => 'FFC000',    // kuning: klaim tanpa sitasi
            'methodology' => '0070C0',// biru: masalah metodologi
            'structure' => '7030A0',  // ungu: struktur tidak sesuai sistematika
            'style' => '00B050',      // hijau: gaya penulisan / kejelasan
        ],
    ],
];
