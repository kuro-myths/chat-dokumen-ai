<?php

namespace App\Services\Review;

use App\Models\Comment;
use App\Models\Document;
use App\Models\Highlight;
use App\Services\Ai\Facades\Ai;
use App\Services\Ai\Prompts\ThesisReviewPrompts;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Review dokumen skripsi/proposal oleh AI:
 * paragraf di-batch -> dianalisis -> temuan menjadi Highlight + Komentar.
 *
 * Diproses PER-BATCH (reviewChunk) supaya setiap request HTTP tetap pendek —
 * dokumen panjang tidak lagi membuat request menggantung berlama-lama.
 * Progres (cursor) tersimpan di kolom review_summary sehingga proses bisa
 * dilanjutkan dari batch terakhir bila terputus di tengah jalan.
 */
class ThesisReviewService
{
    /**
     * Review penuh (semua batch sekaligus) — untuk dokumen pendek / testing.
     *
     * @return array<string, mixed>
     */
    public function review(Document $document, ?string $provider = null, ?string $model = null): array
    {
        do {
            $progress = $this->reviewChunk($document, $provider, $model);
        } while ($progress['done'] < $progress['total']);

        return $document->fresh()->review_summary ?? [];
    }

    /**
     * Proses SATU batch per pemanggilan — request selalu pendek, progres
     * dilaporkan ke frontend, dan cursor aman dari kegagalan/timeout.
     *
     * $provider & $model berasal dari dropdown di composer (null = default
     * provider) — semua model di dropdown bisa dipakai untuk analisis ini.
     *
     * @return array{done:int, total:int, issues:int}
     */
    public function reviewChunk(Document $document, ?string $provider = null, ?string $model = null): array
    {
        $sections = $document->sections()->orderBy('index')->get()->values();

        if ($sections->isEmpty()) {
            throw new \RuntimeException('Dokumen belum diimpor: tidak ada paragraf.');
        }

        $config = config('ai.review');
        $chunks = $sections->chunk($config['batch_sections'])->values();
        $total = $chunks->count();

        $summary = $document->review_summary;

        // Mulai sesi baru bila belum ada cursor (dokumen baru / sesi lama macet).
        if (! is_array($summary) || ! array_key_exists('cursor', $summary)) {
            $document->highlights()->delete();
            $document->comments()->where('is_ai', true)->delete();
            $summary = ['cursor' => 0, 'issues' => 0, 'batches' => 0, 'score' => null, 'overall' => null];
        }

        $cursor = (int) ($summary['cursor'] ?? 0);
        $ai = Ai::provider($provider);

        if ($cursor < $total) {
            $batch = $chunks[$cursor];

            $response = $ai->chat(
                [
                    ['role' => 'system', 'content' => ThesisReviewPrompts::system($document->type)],
                    ['role' => 'user', 'content' => ThesisReviewPrompts::userPrompt($batch->values(), $document->type)],
                ],
                [
                    'json' => true,
                    'model' => $model, // model terpilih di dropdown (null = default provider)
                    'temperature' => $config['temperature'],
                    'timeout' => $config['timeout'],
                ],
            );

            $data = $response->json();
            $summary['batches'] = ($summary['batches'] ?? 0) + 1;

            $issues = array_slice($data['issues'] ?? [], 0, $config['max_issues_per_batch']);

            foreach ($issues as $issue) {
                $this->persistIssue($document, $batch->values(), $issue);
                $summary['issues'] = ($summary['issues'] ?? 0) + 1;
            }

            if (isset($data['score'])) {
                $summary['score'] = (int) $data['score'];
            }

            if (isset($data['overall'])) {
                $summary['overall'] = $data['overall'];
            }

            $summary['cursor'] = $cursor + 1;
        }

        $done = (int) $summary['cursor'];

        if ($done >= $total) {
            unset($summary['cursor']); // selesai — review berikutnya mulai sesi baru
            $document->update(['status' => 'reviewed', 'review_summary' => $summary]);
        } else {
            $document->update(['status' => 'reviewing', 'review_summary' => $summary]);
        }

        return ['done' => $done, 'total' => $total, 'issues' => (int) ($summary['issues'] ?? 0)];
    }

    /** @param Collection $batch */
    protected function persistIssue(Document $document, $batch, array $issue): void
    {
        $index = (int) ($issue['paragraph'] ?? $issue['index'] ?? -1);
        $section = $batch->firstWhere('index', $index);

        $quote = mb_substr(trim((string) ($issue['quote'] ?? '')), 0, 300);

        if ($section && $quote !== '' && ! Str::contains($section->body, $quote)) {
            $quote = $this->fuzzyQuote($section->body, $quote);
        }

        $type = in_array($issue['type'] ?? '', ['grammar', 'citation', 'methodology', 'structure', 'style'])
            ? $issue['type']
            : 'style';

        $highlight = Highlight::create([
            'document_id' => $document->id,
            'document_section_id' => $section?->id,
            'paragraph_index' => $section?->index,
            'quote' => $quote,
            'type' => $type,
            'severity' => in_array($issue['severity'] ?? '', ['high', 'medium', 'low']) ? $issue['severity'] : 'low',
            'color' => $this->normalizeColor($issue['color'] ?? '', $type),
            'comment' => $issue['comment'] ?? '',
            'suggestion' => $issue['suggestion'] ?? '',
            'replacement' => ($issue['replacement'] ?? null) ?: null,
            'status' => 'pending',
        ]);

        // Setiap temuan otomatis menjadi komentar AI di dokumen.
        Comment::create([
            'document_id' => $document->id,
            'document_section_id' => $section?->id,
            'highlight_id' => $highlight->id,
            'author_name' => 'AI Reviewer',
            'is_ai' => true,
            'body' => $issue['comment'] ?? '',
        ]);
    }

    protected function normalizeColor(string $color, string $type): string
    {
        $allowed = ['red', 'yellow', 'blue', 'purple', 'green'];

        if (in_array($color, $allowed, true)) {
            return $color;
        }

        return match ($type) {
            'grammar' => 'red',
            'citation' => 'yellow',
            'methodology' => 'blue',
            'structure' => 'purple',
            default => 'green',
        };
    }

    /** Fallback jika AI mengutip dengan sedikit perbedaan (mis. spasi). */
    protected function fuzzyQuote(string $body, string $quote): string
    {
        $words = preg_split('/\s+/u', $quote) ?: [];
        $needle = implode(' ', array_slice($words, 0, 6));
        $pos = mb_stripos($body, $needle);

        if ($pos === false) {
            return $quote;
        }

        return mb_substr($body, $pos, min(120, mb_strlen($body) - $pos));
    }
}
