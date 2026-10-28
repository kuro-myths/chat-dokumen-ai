<?php

namespace App\Services\Docx;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Ekspor dokumen kembali ke .docx dengan:
 * - teks bermasalah diberi WARNA sesuai jenis temuan,
 * - lampiran "LAPORAN REVIEW AI" berisi semua komentar & saran.
 */
class DocxExportService
{
    public function export(Document $document): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $section->addText($document->title, ['bold' => true, 'size' => 16], ['alignment' => Jc::CENTER]);

        $highlights = $document->highlights()
            ->whereIn('status', ['pending', 'accepted'])
            ->get();

        foreach ($document->sections()->orderBy('index')->get() as $docSection) {
            if ($docSection->heading) {
                $section->addText($docSection->heading, [
                    'bold' => true,
                    'size' => $docSection->level === 1 ? 14 : 12,
                ]);

                if ($docSection->heading === $docSection->body) {
                    continue; // paragraf judul murni
                }
            }

            $this->addHighlightedParagraph(
                $section,
                $docSection,
                $highlights->where('document_section_id', $docSection->id)->values()
            );
        }

        $this->addReviewReport($section, $document);

        Storage::disk('local')->makeDirectory('exports');

        $filename = 'exports/'.Str::slug($document->title).'-reviewed-'.now()->format('Ymd-His').'.docx';
        $fullPath = Storage::disk('local')->path($filename);

        IOFactory::createWriter($phpWord, 'Word2007')->save($fullPath);

        return $fullPath;
    }

    protected function addHighlightedParagraph($section, $docSection, $highlights): void
    {
        $body = $docSection->body;

        $marks = $highlights
            ->filter(fn ($h) => $h->quote !== '' && Str::contains($body, $h->quote))
            ->map(fn ($h) => [
                'start' => mb_strpos($body, $h->quote),
                'len' => mb_strlen($h->quote),
                'h' => $h,
            ])
            ->sortBy('start')
            ->values();

        if ($marks->isEmpty()) {
            $section->addText($body);

            return;
        }

        $cursor = 0;

        foreach ($marks as $mark) {
            if ($mark['start'] < $cursor) {
                continue; // tumpang tindih, lewati
            }

            if ($mark['start'] > $cursor) {
                $section->addText(mb_substr($body, $cursor, $mark['start'] - $cursor));
            }

            $section->addText(
                mb_substr($body, $mark['start'], $mark['len']),
                $this->styleFor($mark['h'])
            );

            $cursor = $mark['start'] + $mark['len'];
        }

        if ($cursor < mb_strlen($body)) {
            $section->addText(mb_substr($body, $cursor));
        }
    }

    protected function styleFor($highlight): array
    {
        $style = [
            'color' => config("ai.highlight.colors.{$highlight->type}", 'FF0000'),
            'bold' => $highlight->severity === 'high',
        ];

        $background = [
            'red' => 'red',
            'yellow' => 'yellow',
            'green' => 'green',
            'blue' => 'cyan',
            'purple' => 'magenta',
        ][$highlight->color] ?? null;

        if ($background) {
            $style['highlight'] = $background;
        }

        return $style;
    }

    protected function addReviewReport($section, Document $document): void
    {
        $highlights = $document->highlights()->get();

        if ($highlights->isEmpty()) {
            return;
        }

        $section->addPageBreak();
        $section->addText('LAPORAN REVIEW AI', ['bold' => true, 'size' => 14]);

        $table = $section->addTable(['borderSize' => 6, 'cellMargin' => 80]);
        $headers = ['No', 'Paragraf', 'Jenis', 'Warna', 'Komentar AI', 'Saran Perbaikan'];

        $table->addRow();

        foreach ($headers as $header) {
            $table->addCell(1800)->addText($header, ['bold' => true]);
        }

        foreach ($highlights->values() as $i => $h) {
            $table->addRow();
            $table->addCell(600)->addText((string) ($i + 1));
            $table->addCell(900)->addText((string) ($h->paragraph_index ?? '-'));
            $table->addCell(1400)->addText($h->type);
            $table->addCell(900)->addText($h->color);
            $table->addCell(3600)->addText($h->comment);
            $table->addCell(3600)->addText($h->suggestion);
        }
    }
}
