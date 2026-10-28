<?php

namespace App\Services\Ai;

class AiResponse
{
    public function __construct(
        public readonly string $content,
        public readonly array $raw = [],
        public readonly array $usage = [],
        public readonly string $provider = '',
        public readonly string $model = '',
    ) {}

    /**
     * Decode payload JSON dari LLM.
     * Tahan terhadap code fence, prefix, dan teks sisa di awal/akhir.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $text = trim($this->content);
        $text = preg_replace('/```(?:json)?/i', '', $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : [];
    }
}
