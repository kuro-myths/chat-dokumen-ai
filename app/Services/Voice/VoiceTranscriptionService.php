<?php

namespace App\Services\Voice;

use App\Models\Document;
use App\Models\Transcription;
use App\Services\Ai\Facades\Ai;
use App\Services\Ai\Prompts\ThesisReviewPrompts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Auto Voice Input:
 * audio -> transkripsi (Whisper-compatible) -> rapikan bahasa akademik
 * -> otomatis dimasukkan ke paragraf dokumen.
 */
class VoiceTranscriptionService
{
    public function handle(
        UploadedFile $audio,
        ?Document $document = null,
        ?int $sectionIndex = null,
        array $options = [],
    ): Transcription {
        $config = config('ai.voice');

        $text = $this->transcribe($audio, $config);
        $text = $this->polish($text, $config);

        $transcription = Transcription::create([
            'document_id' => $document?->id,
            'audio_path' => $audio->store('voice-notes', 'local'),
            'text' => $text,
            'duration' => $options['duration'] ?? null,
        ]);

        // Fitur "auto voice masukan": hasil langsung masuk dokumen.
        if (! empty($config['auto_insert']) && $document) {
            $this->insert($document, $sectionIndex, $text, $options['mode'] ?? 'append');
        }

        return $transcription;
    }

    protected function transcribe(UploadedFile $audio, array $config): string
    {
        $response = Http::withToken($config['api_key'] ?? '')
            ->timeout(180)
            ->attach('file', $audio->getContent(), $audio->getClientOriginalName())
            ->post(rtrim($config['base_url'], '/').'/audio/transcriptions', [
                'model' => $config['model'],
                'language' => $config['language'],
                'response_format' => 'json',
                'temperature' => 0,
            ]);

        $response->throw();

        return trim((string) $response->json('text'));
    }

    protected function polish(string $text, array $config): string
    {
        if ($text === '' || empty($config['polish_with_ai'])) {
            return $text;
        }

        $response = Ai::provider()->chat(
            ThesisReviewPrompts::polishTranscript($text),
            ['temperature' => 0.3],
        );

        return trim($response->content) ?: $text;
    }

    protected function insert(Document $document, ?int $sectionIndex, string $text, string $mode): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        if ($mode === 'new-section' || $sectionIndex === null) {
            $last = $document->sections()->orderByDesc('index')->first();

            $document->sections()->create([
                'index' => ($last?->index ?? -1) + 1,
                'heading' => null,
                'level' => null,
                'body' => $text,
            ]);

            $document->update(['status' => 'drafting']);

            return;
        }

        $section = $document->sections()->where('index', $sectionIndex)->firstOrFail();
        $section->update(['body' => trim($section->body."\n".$text)]);
    }
}
