<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiResponse;
use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpClient\HttpClient as SymfonyClient;

/**
 * Driver universal untuk semua API kompatibel OpenAI Chat Completions:
 * OpenAI, Groq, Mistral, DeepSeek, Together, OpenRouter, vLLM, LiteLLM, dsb.
 * Cukup set base_url + api_key + model.
 */
class OpenAiCompatibleDriver implements AiProviderInterface
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
        return rtrim($this->config['base_url'] ?? 'https://api.openai.com/v1', '/');
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        $payload = $this->payload($messages, $options, stream: false);

        $response = Http::withToken($this->config['api_key'] ?? '')
            ->timeout($options['timeout'] ?? 120)
            ->acceptJson()
            ->post($this->baseUrl().'/chat/completions', $payload);

        $response->throw();

        $data = $response->json();

        return new AiResponse(
            content: $data['choices'][0]['message']['content'] ?? '',
            raw: $data,
            usage: $data['usage'] ?? [],
            provider: $this->name,
            model: $payload['model'],
        );
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        $payload = $this->payload($messages, $options, stream: true);

        $client = SymfonyClient::create(timeout: $options['timeout'] ?? 120);
        $response = $client->request('POST', $this->baseUrl().'/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.($this->config['api_key'] ?? ''),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($payload),
        ]);

        $buffer = '';

        foreach ($client->stream($response) as $chunk) {
            $buffer .= $chunk->getContent();

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                if (! str_starts_with(trim($line), 'data:')) {
                    continue;
                }

                $data = trim(substr(trim($line), 5));

                if ($data === '[DONE]') {
                    return;
                }

                $json = json_decode($data, true);
                $delta = $json['choices'][0]['delta']['content'] ?? '';

                if ($delta !== '') {
                    yield $delta;
                }
            }
        }
    }

    protected function payload(array $messages, array $options, bool $stream): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->config['model'] ?? 'gpt-4o',
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.2,
        ];

        if (! empty($options['json'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = $options['max_tokens'];
        }

        if ($stream) {
            $payload['stream'] = true;
        }

        return $payload;
    }
}
