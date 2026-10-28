<?php

namespace App\Services\Review;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Highlight;
use Illuminate\Support\Str;

/**
 * Terima / tolak revisi AI pada dokumen.
 * Menerima revisi = teks asli diganti replacement + snapshot versi baru.
 */
class RevisionService
{
    public function accept(Highlight $highlight): Highlight
    {
        abort_if($highlight->status !== 'pending', 422, 'Revisi ini sudah diproses.');

        $applied = false;

        if ($highlight->replacement && $highlight->section) {
            $body = $highlight->section->body;

            if (Str::contains($body, $highlight->quote)) {
                $highlight->section->update([
                    'body' => Str::replaceFirst($highlight->quote, $highlight->replacement, $body),
                ]);
                $applied = true;
            }
        }

        $highlight->update(['status' => 'accepted']);

        if ($applied) {
            $this->snapshot($highlight->document, 'Terima revisi AI: '.Str::limit($highlight->quote, 40));
        }

        return $highlight->fresh();
    }

    public function reject(Highlight $highlight): Highlight
    {
        $highlight->update(['status' => 'rejected']);

        return $highlight;
    }

    /** Terima semua revisi teks yang masih pending sekaligus. */
    public function acceptAll(Document $document): int
    {
        $count = 0;

        $document->highlights()
            ->where('status', 'pending')
            ->whereNotNull('replacement')
            ->each(function (Highlight $highlight) use (&$count) {
                $this->accept($highlight);
                $count++;
            });

        return $count;
    }

    protected function snapshot(Document $document, string $label): void
    {
        $document->versions()->create([
            'label' => $label,
            'sections' => $document->sections()
                ->orderBy('index')
                ->get(['index', 'heading', 'body'])
                ->toArray(),
        ]);
    }
}
