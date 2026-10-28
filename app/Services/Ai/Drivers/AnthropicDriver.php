<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiResponse;
use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpClient\HttpClient as SymfonyClient;

class AnthropicDriver implements AiProviderInterface
{
    protected const BASE_URL = 'https://api.anthropic.com/v1';

    protected const API_VERSION = '2023-06-01';

    public function __construct(
        protected string $name,
        protected array $config,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        [$system, $chat] = $this->splitSystem($messages);

        $payload = [
            'model' => $options['model'] ?? $this->config['model'] ?? 'claude-sonnet-4-5',
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'temperature' => $options['temperature'] ?? 0.2,
            'messages' => $chat,
        ];

        if ($system !== null) {
            $payload['system'] = $system;
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->config['api_key'] ?? '',
            'anthropic-version' => self::API_VERSION,
        ])
            ->timeout($options['timeout'] ?? 120)
            ->acceptJson()
            ->post(self::BASE_URL.'/messages', $payload);

        $response->throw();

        $data = $response->json();
        $content = collect($data['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        return new AiResponse(
            content: $content,
            raw: $data,
            usage: $data['usage'] ?? [],
            provider: $this->name,
            model: $payload['model'],
        );
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        [$system, $chat] = $this->splitSystem($messages);

        $payload = [
            'model' => $options['model'] ?? $this->config['model'] ?? 'claude-sonnet-4-5',
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'temperature' => $options['temperature'] ?? 0.2,
            'messages' => $chat,
            'stream' => true,
        ];

        if ($system !== null) {
            $payload['system'] = $system;
        }

        $client = SymfonyClient::create(timeout: $options['timeout'] ?? 120);
        $response = $client->request('POST', self::BASE_URL.'/messages', [
            'headers' => [
                'x-api-key' => $this->config['api_key'] ?? '',
                'anthropic-version' => self::API_VERSION,
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

                $json = json_decode(substr(trim($line), 5), true);

                if (($json['type'] ?? null) === 'content_block_delta') {
                    $delta = $json['delta']['text'] ?? '';

                    if ($delta !== '') {
                        yield $delta;
                    }
                }
            }
        }
    }

    /** @return array{0: ?string, 1: list<array{role: string, content: string}>} */
    protected function splitSystem(array $messages): array
    {
        $system = null;
        $chat = [];

        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'system') {
                $system = $message['content'];
            } else {
                $chat[] = $message;
            }
        }

        return [$system, array_values($chat)];
    }
}
