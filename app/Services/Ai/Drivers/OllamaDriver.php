<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiResponse;
use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpClient\HttpClient as SymfonyClient;

/**
 * Ollama lokal (http://127.0.0.1:11434) — gratis, tanpa API key.
 */
class OllamaDriver implements AiProviderInterface
{
    public function __construct(
        protected string $name,
        protected array $config,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    protected function baseUrl(): string
    {
        return rtrim($this->config['base_url'] ?? 'http://127.0.0.1:11434', '/');
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        $model = $options['model'] ?? $this->config['model'] ?? 'llama3.1';

        $response = Http::timeout($options['timeout'] ?? 300)
            ->acceptJson()
            ->post($this->baseUrl().'/api/chat', [
                'model' => $model,
                'messages' => $messages,
                'stream' => false,
                'format' => ! empty($options['json']) ? 'json' : null,
                'options' => ['temperature' => $options['temperature'] ?? 0.2],
            ]);

        $response->throw();

        $data = $response->json();

        return new AiResponse(
            content: $data['message']['content'] ?? '',
            raw: $data,
            usage: ['eval_count' => $data['eval_count'] ?? null],
            provider: $this->name,
            model: $model,
        );
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        $client = SymfonyClient::create(timeout: $options['timeout'] ?? 300);
        $response = $client->request('POST', $this->baseUrl().'/api/chat', [
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
            'body' => json_encode([
                'model' => $options['model'] ?? $this->config['model'] ?? 'llama3.1',
                'messages' => $messages,
                'stream' => true,
                'options' => ['temperature' => $options['temperature'] ?? 0.2],
            ]),
        ]);

        $buffer = '';

        foreach ($client->stream($response) as $chunk) {
            $buffer .= $chunk->getContent();

            // Respons Ollama berformat NDJSON (satu objek JSON per baris).
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '') {
                    continue;
                }

                $json = json_decode($line, true);
                $delta = $json['message']['content'] ?? '';

                if ($delta !== '') {
                    yield $delta;
                }

                if (! empty($json['done'])) {
                    return;
                }
            }
        }
    }
}
