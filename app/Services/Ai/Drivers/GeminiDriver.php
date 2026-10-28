<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiResponse;
use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpClient\HttpClient as SymfonyClient;

class GeminiDriver implements AiProviderInterface
{
    public function __construct(
        protected string $name,
        protected array $config,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    protected function url(string $method): string
    {
        $model = $this->config['model'] ?? 'gemini-2.0-flash';
        $key = $this->config['api_key'] ?? '';

        return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:{$method}?key={$key}";
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        $response = Http::timeout($options['timeout'] ?? 120)
            ->acceptJson()
            ->post($this->url('generateContent'), $this->payload($messages, $options));

        $response->throw();

        $data = $response->json();
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $content = collect($parts)->pluck('text')->implode('');

        return new AiResponse(
            content: $content,
            raw: $data,
            usage: $data['usageMetadata'] ?? [],
            provider: $this->name,
            model: $this->config['model'] ?? 'gemini-2.0-flash',
        );
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        $client = SymfonyClient::create(timeout: $options['timeout'] ?? 120);
        $response = $client->request('POST', $this->url('streamGenerateContent').'&alt=sse', [
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
            'body' => json_encode($this->payload($messages, $options)),
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

        $payload = [
            'contents' => $contents,
            'generationConfig' => ['temperature' => $options['temperature'] ?? 0.2],
        ];

        if ($system !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        return $payload;
    }
}
