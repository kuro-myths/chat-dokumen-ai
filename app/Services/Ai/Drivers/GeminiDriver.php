<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiResponse;
use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpClient\HttpClient as SymfonyClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Driver Google Gemini (Google AI Studio).
 *
 * Mendukung API key format LAMA (AIza...) maupun format BARU (AQ.Ab8...)
 * yang sekarang jadi satu-satunya format yang diterbitkan AI Studio.
 * Kunci dikirim via header x-goog-api-key (cara standar Google) ke
 * endpoint NATIVE generativelanguage.googleapis.com/v1beta — key
 * format AQ. hanya diterima di jalur native, bukan OpenAI-compatible.
 */
class GeminiDriver implements AiProviderInterface
{
    protected const BASE = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(
        protected string $name,
        protected array $config,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Model aktif: override per-request ($options['model'] dari dropdown
     * model di composer) -> config provider -> default gemini-2.5-flash.
     * gemini-2.0-flash sudah pensiun — default kini gemini-2.5-flash.
     */
    protected function model(array $options = []): string
    {
        return trim((string) ($options['model'] ?? ''))
            ?: trim((string) ($this->config['model'] ?? ''))
            ?: 'gemini-2.5-flash';
    }

    protected function url(string $method, array $options = []): string
    {
        return self::BASE.'/models/'.$this->model($options).':'.$method;
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        $response = Http::timeout($options['timeout'] ?? 120)
            ->acceptJson()
            ->withHeaders(['x-goog-api-key' => (string) ($this->config['api_key'] ?? '')])
            ->post($this->url('generateContent', $options), $this->payload($messages, $options));

        // Model tertentu (mis. keluarga Gemma) tidak mendukung systemInstruction
        // -> HTTP 400 "Developer instruction is not enabled for this model".
        // Ulangi sekali dengan system digabung ke pesan user pertama.
        if ($response->status() === 400 && $this->systemNotSupported((string) $response->body())) {
            $response = Http::timeout($options['timeout'] ?? 120)
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => (string) ($this->config['api_key'] ?? '')])
                ->post($this->url('generateContent', $options), $this->payload($messages, $options + ['merge_system' => true]));
        }

        $this->assertOk($response);

        $data = $response->json();
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $content = collect($parts)->pluck('text')->implode('');

        if ($content === '') {
            $block = $data['promptFeedback']['blockReason'] ?? null;

            if ($block !== null) {
                throw new RuntimeException('Gemini menolak prompt (blockReason: '.$block.') — coba ubah/redaksi pesan.');
            }

            $finish = $data['candidates'][0]['finishReason'] ?? null;

            if ($finish !== null && $finish !== 'STOP') {
                throw new RuntimeException('Gemini berhenti sebelum menjawab (finishReason: '.$finish.') — coba ulangi atau ganti model.');
            }
        }

        return new AiResponse(
            content: $content,
            raw: $data,
            usage: $data['usageMetadata'] ?? [],
            provider: $this->name,
            model: $this->model($options),
        );
    }

    /**
     * Ubah error Google menjadi pesan Indonesia yang jelas, bukan
     * "server error" generik — membedakan key salah, model pensiun,
     * atau kuota habis.
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $data = $response->json();
        $message = (string) ($data['error']['message'] ?? $response->body());

        $hint = match (true) {
            $status === 400 && str_contains($message, 'API_KEY_INVALID') =>
                'API key Gemini tidak valid — salin ulang PENUH dari aistudio.google.com/api-keys (format lama AIza... maupun baru AQ.Ab8... keduanya didukung).',
            $status === 401 || $status === 403 =>
                'API key Gemini ditolak Google — pastikan kunci lengkap (tidak terpotong), belum dihapus, dan berasal dari AI Studio.',
            $status === 404 =>
                'Model "'.$this->model().'" tidak tersedia / sudah pensiun — ganti model (mis. gemini-2.5-flash).',
            $status === 429 =>
                'Kuota Gemini habis atau terlalu banyak permintaan — tunggu sebentar, atau pakai mode Manual (Max Router).',
            default => 'Gemini error HTTP '.$status.'.',
        };

        throw new RuntimeException($hint.' Detail: '.mb_substr($message, 0, 300));
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        $client = SymfonyClient::create(timeout: $options['timeout'] ?? 120);
        $response = $this->streamRequest($client, $messages, $options);

        // Paksa header diterima supaya status HTTP bisa dicek sebelum streaming.
        try {
            $response->getHeaders();
        } catch (\Throwable $e) {
            throw new RuntimeException('Gagal menghubungi Gemini: '.$e->getMessage());
        }

        $code = (int) ($response->getInfo('http_code') ?? 200);
        $body = $code >= 400 ? $response->getContent(false) : '';

        // Model tanpa systemInstruction (mis. Gemma) -> ulangi dengan
        // system digabung ke pesan user pertama (lihat chat()).
        if ($code === 400 && $this->systemNotSupported($body)) {
            $response = $this->streamRequest($client, $messages, $options + ['merge_system' => true]);

            try {
                $response->getHeaders();
            } catch (\Throwable $e) {
                throw new RuntimeException('Gagal menghubungi Gemini: '.$e->getMessage());
            }

            $code = (int) ($response->getInfo('http_code') ?? 200);
            $body = $code >= 400 ? $response->getContent(false) : '';
        }

        if ($code >= 400) {
            $message = (string) (json_decode($body, true)['error']['message'] ?? $body);

            throw new RuntimeException('Gemini streaming error HTTP '.$code.': '.mb_substr($message, 0, 300));
        }

        $buffer = '';

        foreach ($client->stream($response) as $chunk) {
            $buffer .= $chunk->getContent();

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                if (! str_starts_with(trim($line), 'data:')) {
                    continue;
                }

                $json = json_decode(substr(trim($line), 5), true);
                $delta = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

                if ($delta !== '') {
                    yield $delta;
                }
            }
        }
    }

    protected function payload(array $messages, array $options): array
    {
        $system = collect($messages)
            ->filter(fn ($m) => ($m['role'] ?? '') === 'system')
            ->map(fn ($m) => $m['content'])
            ->implode("\n");

        $contents = collect($messages)
            ->filter(fn ($m) => ($m['role'] ?? '') !== 'system')
            ->map(fn ($m) => [
                'role' => ($m['role'] ?? '') === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $m['content']]],
            ])
            ->values()
            ->all();

        // Fallback untuk model tanpa systemInstruction (mis. Gemma):
        // system digabung ke pesan user pertama (opsi 'merge_system').
        if ($system !== '' && ! empty($options['merge_system'])) {
            if (isset($contents[0]) && $contents[0]['role'] === 'user') {
                $contents[0]['parts'][0]['text'] = $system."\n\n".$contents[0]['parts'][0]['text'];
            } else {
                array_unshift($contents, ['role' => 'user', 'parts' => [['text' => $system]]]);
            }

            $system = '';
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => ['temperature' => $options['temperature'] ?? 0.2],
        ];

        if ($system !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        return $payload;
    }

    /** Request streaming SSE ke Gemini (dipakai juga untuk retry tanpa systemInstruction). */
    protected function streamRequest(HttpClientInterface $client, array $messages, array $options)
    {
        return $client->request('POST', $this->url('streamGenerateContent', $options).'?alt=sse', [
            'headers' => [
                'x-goog-api-key' => (string) ($this->config['api_key'] ?? ''),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($this->payload($messages, $options)),
        ]);
    }

    /** True bila error Gemini karena model tidak mendukung systemInstruction. */
    protected function systemNotSupported(string $body): bool
    {
        return str_contains($body, 'Developer instruction is not enabled');
    }
}
