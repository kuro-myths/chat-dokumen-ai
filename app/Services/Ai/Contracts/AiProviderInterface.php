<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\AiResponse;

interface AiProviderInterface
{
    /**
     * Kirim percakapan dan kembalikan respons lengkap.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array{model?: string, temperature?: float, json?: bool, max_tokens?: int, timeout?: int} $options
     */
    public function chat(array $messages, array $options = []): AiResponse;

    /**
     * Streaming token demi token (untuk UI real-time).
     *
     * @return \Generator<string>
     */
    public function stream(array $messages, array $options = []): \Generator;

    /** Nama unik provider (mis. "openai", "groq", "kampus-llm"). */
    public function name(): string;
}
