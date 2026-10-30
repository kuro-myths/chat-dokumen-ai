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
 * Mendukung API key format LAMA (AIza...) maupun format BARU (AQ.Ab8...).
 * Kunci dikirim via header x-goog-api-key ke endpoint native v1beta.
 *
 * FALLBACK MODEL OTOMATIS: bila model yang diminta pensiun / tidak tersedia
 * (HTTP 404, atau 400 "no longer available"), driver TIDAK berhenti —
 * langsung coba model cadangan berikutnya sampai ada yang hidup.
 * Review & chat jadi kebal model pensiun (2.5 mati untuk semua project
 * sejak Okt 2026). Model yang terbukti hidup diingat sepanjang request.
 */
class GeminiDriver implements AiProviderInterface
{
    protected const BASE = 'https://generativelanguage.googleapis.com/v1beta';

    /** Model cadangan bila model pilihan pensiun — urut dari yang terbaru. */
    protected const FALLBACK_MODELS = [
        'gemini-3.8-flash',
        'gemini-3.8-flash-lite',
        'gemini-3.5-flash-lite',
        'gemini-3.5-flash',
        'gemini-4-argon',
        'gemini-2.5-flash',
    ];

    /** Model terakhir yang terbukti hidup (cache selama request berjalan). */
    protected static ?string $lastGoodModel = null;

    public function __construct(
        protected string $name,
        protected array $config,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Model aktif: override per-request (dropdown model) -> model yang
     * terakhir berhasil -> config provider -> fallback terbaru.
     */
    protected function model(array $options = []): string
    {
        return $this->modelCandidates($options)[0];
    }

    /** Urutan kandidat model yang dicoba: pilihan user -> last good -> config -> fallback. */
    protected function modelCandidates(array $options = []): array
    {
        return array_values(array_filter(array_unique([
            trim((string) ($options['model'] ?? '')),
            static::$lastGoodModel,
            trim((string) ($this->config['model'] ?? '')),
            ...self::FALLBACK_MODELS,
        ])));
    }

    protected function url(string $method, array $options = [], ?string $model = null): string
    {
        return self::BASE.'/models/'.($model ?? $this->model($options)).':'.$method;
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        foreach ($this->modelCandidates($options) as $model) {
            $response = $this->postChat($messages, $options, $model);

            // Model pensiun / tidak tersedia -> LANGSUNG coba model berikutnya.
            if ($this->modelUnavailable($response)) {
                continue;
            }

            // Model tertentu (mis. keluarga Gemma) tidak mendukung systemInstruction
            // -> ulangi sekali dengan system digabung ke pesan user pertama.
            if ($response->status() === 400 && $this->systemNotSupported((string) $response->body())) {
                $response = $this->postChat($messages, $options + ['merge_system' => true], $model);

                if ($this->modelUnavailable($response)) {
                    continue;
                }
            }

            static::$lastGoodModel = $model;

            return $this->parseChat($response, $options, $model);
        }

        throw new RuntimeException(
            'Semua model Gemini tidak tersedia / sudah pensiun (dicoba: '
            .implode(', ', $this->modelCandidates($options))
            .') — cek API key atau pakai mode Manual (Max Router).'
        );
    }

    protected function postChat(array $messages, array $options, string $model): Response
    {
        return Http::timeout($options['timeout'] ?? 120)
            ->acceptJson()
            ->withHeaders(['x-goog-api-key' => (string) ($this->config['api_key'] ?? '')])
            ->post($this->url('generateContent', $options, $model), $this->payload($messages, $options));
    }

    protected function parseChat(Response $response, array $options, string $model): AiResponse
    {
        $this->assertOk($response, $model);

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

            // Model menjawab tetapi tidak ada teks — biasanya model non-teks
            // (TTS / audio / image) yang dipakai untuk chat.
            throw new RuntimeException('Model "'.$model.'" tidak menghasilkan teks — pilih model chat (bukan TTS/audio) di dropdown model.');
        }

        return new AiResponse(
            content: $content,
            raw: $data,
            usage: $data['usageMetadata'] ?? [],
            provider: $this->name,
            model: $model,
        );
    }

    /**
     * Ubah error Google menjadi pesan Indonesia yang jelas — membedakan
     * key salah, model pensiun, atau kuota habis.
     */
    protected function assertOk(Response $response, ?string $model = null): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $data = $response->json();
        $message = (string) ($data['error']['message'] ?? $response->body());
        $model ??= $this->model();

        $hint = match (true) {
            $status === 400 && str_contains($message, 'API_KEY_INVALID') =>
                'API key Gemini tidak valid — salin ulang PENUH dari aistudio.google.com/api-keys (format lama AIza... maupun baru AQ.Ab8... keduanya didukung).',
            $status === 401 || $status === 403 =>
                'API key Gemini ditolak Google — pastikan kunci lengkap (tidak terpotong), belum dihapus, dan berasal dari AI Studio.',
            $status === 404 =>
                'Model "'.$model.'" tidak tersedia / sudah pensiun — model lain sudah otomatis dicoba; kalau masih gagal, ganti model di dropdown.',
            $status === 429 =>
                'Kuota Gemini habis atau terlalu banyak permintaan — tunggu sebentar, atau pakai mode Manual (Max Router).',
            default => 'Gemini error HTTP '.$status.'.',
        };

        throw new RuntimeException($hint.' Detail: '.mb_substr($message, 0, 300));
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        foreach ($this->modelCandidates($options) as $model) {
            $client = SymfonyClient::create(timeout: $options['timeout'] ?? 120);
            $response = $this->streamRequest($client, $messages, $options, $model);

            // Paksa header diterima supaya status HTTP bisa dicek sebelum streaming.
            try {
                $response->getHeaders();
            } catch (\Throwable $e) {
                throw new RuntimeException('Gagal menghubungi Gemini: '.$e->getMessage());
            }

            $code = (int) ($response->getInfo('http_code') ?? 200);
            $body = $code >= 400 ? $response->getContent(false) : '';

            // Model pensiun / tidak tersedia -> coba model berikutnya.
            if ($code === 404 || ($code === 400 && $this->bodyModelUnavailable($body))) {
                continue;
            }

            // Model tanpa systemInstruction (mis. Gemma) -> ulangi dengan
            // system digabung ke pesan user pertama (lihat chat()).
            if ($code === 400 && $this->systemNotSupported($body)) {
                $response = $this->streamRequest($client, $messages, $options + ['merge_system' => true], $model);

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

                    $json = json_decode(substr(trim($line), 5), true);
                    $delta = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

                    if ($delta !== '') {
                        yield $delta;
                    }
                }
            }

            return; // sukses — jangan lanjut ke kandidat berikutnya
        }

        throw new RuntimeException(
            'Semua model Gemini tidak tersedia / sudah pensiun saat streaming (dicoba: '
            .implode(', ', $this->modelCandidates($options))
            .') — cek API key atau pakai mode Manual (Max Router).'
        );
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
    protected function streamRequest(HttpClientInterface $client, array $messages, array $options, ?string $model = null)
    {
        return $client->request('POST', $this->url('streamGenerateContent', $options, $model ?? $this->model($options)).'?alt=sse', [
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

    /** True bila body error menandakan model pensiun / tidak tersedia. */
    protected function bodyModelUnavailable(string $body): bool
    {
        return (bool) preg_match(
            '/no longer available|not found|not supported|is deprecated|pensiun/i',
            $body
        );
    }

    /** True bila response gagal karena model pensiun -> layak coba model berikutnya. */
    protected function modelUnavailable(Response $response): bool
    {
        if ($response->status() === 404) {
            return true;
        }

        return $response->status() === 400 && $this->bodyModelUnavailable((string) $response->body());
    }
}
