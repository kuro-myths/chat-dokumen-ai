<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider AI default
    |--------------------------------------------------------------------------
    | Provider bisa berasal dari config ini, atau dari tabel ai_settings
    | (API apa pun yang ditambahkan user lewat endpoint /api/ai/providers).
    */

    'default' => env('AI_PROVIDER', 'openai'),

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

        'openai' => [
            'driver' => 'openai',
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
        ],

        'anthropic' => [
            'driver' => 'anthropic',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        ],

        'groq' => [
            'driver' => 'openai-compatible',
            'api_key' => env('GROQ_API_KEY'),
            'base_url' => 'https://api.groq.com/openai/v1',
            'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        ],

        'mistral' => [
            'driver' => 'openai-compatible',
            'api_key' => env('MISTRAL_API_KEY'),
            'base_url' => 'https://api.mistral.ai/v1',
            'model' => env('MISTRAL_MODEL', 'mistral-large-latest'),
        ],

        // 9Router (gateway AI kompatibel OpenAI — lokal atau online).
        // Isi NINEROUTER_BASE_URL dengan URL instance 9Router kamu,
        // mis. https://instance-9router.up.railway.app/v1
        '9router' => [
            'driver' => 'openai-compatible',
            'api_key' => env('NINEROUTER_API_KEY'),
            'base_url' => env('NINEROUTER_BASE_URL'),
            'model' => env('NINEROUTER_MODEL', 'gpt-4o-mini'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
            'model' => env('OLLAMA_MODEL', 'llama3.1'),
        ],

        // Driver serbaguna: provider mana pun dengan endpoint
        // kompatibel OpenAI (DeepSeek, Together, OpenRouter, vLLM, dsb.)
        'custom' => [
            'driver' => 'openai-compatible',
            'api_key' => env('CUSTOM_AI_API_KEY'),
            'base_url' => env('CUSTOM_AI_BASE_URL'),
            'model' => env('CUSTOM_AI_MODEL'),
        ],
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
