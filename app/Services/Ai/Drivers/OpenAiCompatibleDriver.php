<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiResponse;
use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpClient\HttpClient as SymfonyClient;

/**
 * Driver universal untuk semua API kompatibel OpenAI Chat Completions:
 * OpenAI, Groq, Mistral, DeepSeek, Together, OpenRouter, vLLM, LiteLLM,
 * Max Router / 9Router, dsb. Cukup set base_url + api_key + model.
 *
 * FALLBACK MODEL OTOMATIS: bila model pilihan gagal (503 sibuk, 404
 * pensiun, 429 kuota, 5xx server, dsb.), driver LANGSUNG mencoba model
 * berikutnya — config, lalu daftar live dari GET /models — sampai ada
 * yang hidup. Proses tidak berhenti di error pertama.
 */
class OpenAiCompatibleDriver implements AiProviderInterface
{
    /** Model terakhir yang terbukti hidup (cache selama request berjalan). */
    protected static ?string $lastGoodModel = null;

    /** Batas model cadangan yang diambil dari GET /models. */
    protected const MAX_DYNAMIC = 8;

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

    /**
     * Urutan kandidat model: pilihan user -> model terakhir yang hidup ->
     * config -> daftar live dari GET /models (khusus model chat).
     *
     * @return list<string>
     */
    protected function modelCandidates(array $options): array
    {
        $candidates = array_values(array_filter(array_unique([
            trim((string) ($options['model'] ?? '')),
            static::$lastGoodModel,
            trim((string) ($this->config['model'] ?? '')),
        ])));

        foreach ($this->liveModels() as $id) {
            if (count($candidates) >= self::MAX_DYNAMIC) {
                break;
            }

            $candidates[] = $id;
        }

        return array_values(array_unique($candidates));
    }

    /** Daftar model live dari GET /models — khusus model chat (tanpa TTS/embed/gambar). */
    protected function liveModels(): array
    {
        try {
            $response = Http::withToken($this->config['api_key'] ?? '')
                ->timeout(10)
                ->acceptJson()
                ->get($this->baseUrl().'/models');

            return collect($response->json('data') ?? [])
                ->pluck('id')
                ->map(fn ($id) => trim((string) $id))
                ->filter()
                ->filter(fn ($id) => ! preg_match('/tts|embed|imagen|image|veo|aqa|audio|live|whisper|moderation/i', $id))
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        $errors = [];

        foreach ($this->modelCandidates($options) as $model) {
            try {
                $response = Http::withToken($this->config['api_key'] ?? '')
                    ->timeout($options['timeout'] ?? 120)
                    ->acceptJson()
                    ->post($this->baseUrl().'/chat/completions', $this->payload($messages, $options, stream: false, model: $model));
            } catch (\Throwable $e) {
                $errors[] = $model.': gangguan koneksi';
                continue;
            }

            if ($this->shouldSwitchModel($response)) {
                $errors[] = $model.': HTTP '.$response->status().' '.mb_substr((string) ($response->json('error.message') ?? $response->body()), 0, 100);
                continue;
            }

            if ($response->failed()) {
                $message = (string) ($response->json('error.message') ?? $response->body());

                throw new RuntimeException('AI "'.$this->name.'" error HTTP '.$response->status().': '.mb_substr($message, 0, 300));
            }

            $data = $response->json();
            static::$lastGoodModel = $model;

            return new AiResponse(
                content: $data['choices'][0]['message']['content'] ?? '',
                raw: $data,
                usage: $data['usage'] ?? [],
                provider: $this->name,
                model: $model,
            );
        }

        throw new RuntimeException(
            'Semua model di "'.$this->name.'" gagal / sibuk — sudah otomatis dicoba berganti model. Detail: '
            .implode('; ', $errors ?: ['tidak ada model tersedia'])
        );
    }

    /**
     * True bila response layak DILEWATI ke model berikutnya: server sibuk
     * (503 dst.), model pensiun / tidak ada (404 / 400), atau kuota habis (429).
     */
    protected function shouldSwitchModel(Response $response): bool
    {
        $status = $response->status();

        if ($status === 429 || $status >= 500 || $status === 404) {
            return true;
        }

        if ($status === 400) {
            return (bool) preg_match(
                '/no longer available|not found|does not exist|not supported|deprecated|pensiun|model_not_found|model_not_exists|invalid model/i',
                (string) $response->body()
            );
        }

        return false;
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        foreach ($this->modelCandidates($options) as $model) {
            $client = SymfonyClient::create(timeout: $options['timeout'] ?? 120);
            $response = $client->request('POST', $this->baseUrl().'/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer '.($this->config['api_key'] ?? ''),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($this->payload($messages, $options, stream: true, model: $model)),
            ]);

            try {
                $response->getHeaders();
            } catch (\Throwable) {
                continue; // gangguan koneksi -> coba model berikutnya
            }

            $code = (int) ($response->getInfo('http_code') ?? 200);

            // Model sibuk (503) / pensiun (404) / kuota (429) -> model berikutnya.
            if ($code === 429 || $code >= 500 || $code === 404) {
                continue;
            }

            if ($code >= 400) {
                $body = $response->getContent(false);
                $message = (string) (json_decode($body, true)['error']['message'] ?? $body);

                throw new RuntimeException('AI "'.$this->name.'" streaming error HTTP '.$code.': '.mb_substr($message, 0, 300));
            }

            static::$lastGoodModel = $model;
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

            return; // sukses — jangan lanjut ke kandidat berikutnya
        }

        throw new RuntimeException('Semua model di "'.$this->name.'" gagal / sibuk saat streaming — sudah otomatis dicoba berganti model. Coba lagi sebentar.');
    }

    protected function payload(array $messages, array $options, bool $stream, ?string $model = null): array
    {
        $payload = [
            'model' => $model ?? $options['model'] ?? $this->config['model'] ?? 'gpt-4o',
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
