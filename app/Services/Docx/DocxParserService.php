<?php

namespace App\Services\Docx;

use App\Models\Document;
use App\Models\DocumentSection;
use Illuminate\Support\Carbon;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;

/**
 * Impor file .docx skripsi/proposal menjadi paragraf bernomor
 * yang siap dianalisis AI.
 */
class DocxParserService
{
    public function import(Document $document, string $path): int
    {
        $phpWord = IOFactory::load($path);

        $index = 0;
        $rows = [];

        foreach ($phpWord->getSections() as $wordSection) {
            foreach ($wordSection->getElements() as $element) {
                $text = $this->extractText($element);

                if (trim($text) === '') {
                    continue;
                }

                [$heading, $level] = $this->detectHeading($text);

                $rows[] = [
                    'document_id' => $document->id,
                    'index' => $index++,
                    'heading' => $heading,
                    'level' => $level,
                    'body' => $text,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DocumentSection::insert($chunk);
        }

        $document->update(['status' => 'imported']);

        return count($rows);
    }

    protected function extractText(mixed $element): string
    {
        if ($element instanceof TextRun) {
            return collect($element->getElements())
                ->map(fn ($e) => $e instanceof Text ? $e->getText() : '')
                ->implode('');
        }

        if ($element instanceof Text) {
            return (string) $element->getText();
        }

        return '';
    }

    /** Deteksi judul BAB / sub-bab / halaman khusus skripsi. */
    protected function detectHeading(string $text): array
    {
        $text = trim($text);

        if (preg_match('/^(BAB\s+[IVXLCDM]+|KATA PENGANTAR|DAFTAR (ISI|GAMBAR|TABEL|PUSTAKA)|ABSTRAK|ABSTRACT|LAMPIRAN)/iu', $text)) {
            return [$text, 1];
        }

        if (preg_match('/^\d+(\.\d+)*\s+\S/u', $text) && mb_strlen($text) < 120) {
            return [$text, 2];
        }

        return [null, null];
    }
}
