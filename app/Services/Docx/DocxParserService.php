<?php

namespace App\Services\Docx;

use App\Models\Document;
use App\Models\DocumentSection;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Carbon;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Impor file .docx skripsi/proposal menjadi paragraf bernomor
 * yang siap dianalisis AI.
 *
 * Strategi baca dua lapis supaya dokumen hasil Word / Google Docs
 * tidak mudah gagal di server produksi (Railway):
 * 1) baca langsung word/document.xml via ZipArchive + DOMDocument
 *    (paling toleran terhadap struktur Word asli),
 * 2) bila gagal, pakai parser PhpWord sebagai cadangan.
 * Pesan error selalu menyertakan penyebab aslinya supaya mudah didiagnosis.
 */
class DocxParserService
{
    public function import(Document $document, string $path): int
    {
        if (!is_file($path)) {
            throw new RuntimeException("File unggahan tidak tersimpan di server ({$path}).");
        }

        $paragraphs = $this->extractParagraphs($path);

        $index = 0;
        $rows = [];

        foreach ($paragraphs as $text) {
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

        if ($rows === []) {
            throw new RuntimeException(
                'Tidak ada teks yang bisa dibaca — dokumen mungkin hanya berisi gambar, '
                .'atau file .docx tidak standar. Coba buka di Word lalu "Save as" ke .docx dan unggah ulang.'
            );
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DocumentSection::insert($chunk);
        }

        $document->update(['status' => 'imported']);

        return count($rows);
    }

    /**
     * Ambil seluruh paragraf teks dari file .docx.
     * Metode zip+XML dipakai dulu (paling toleran); PhpWord cadangan.
     *
     * @return array<int, string>
     */
    protected function extractParagraphs(string $path): array
    {
        $errors = [];

        try {
            return $this->extractFromZip($path);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }

        try {
            return $this->extractWithPhpWord($path);
        } catch (Throwable $e) {
            $errors[] = 'PhpWord: '.$e->getMessage();
        }

        throw new RuntimeException('Dokumen tidak bisa dibaca ['.implode(' | ', $errors).']');
    }

    /**
     * Baca langsung word/document.xml dari arsip .docx — tidak terganggu
     * header/footer/gambar/fitur Word lain yang sering membuat PhpWord gagal.
     *
     * @return array<int, string>
     */
    protected function extractFromZip(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ekstensi PHP "zip" tidak aktif di server');
        }

        $zip = new ZipArchive;
        $code = $zip->open($path, ZipArchive::RDONLY);

        if ($code !== true) {
            throw new RuntimeException(
                "file bukan arsip .docx yang valid (kode {$code}) — kemungkinan .doc lama, rusak, atau terproteksi password"
            );
        }

        try {
            $entry = null;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);

                if ($name === 'word/document.xml' || str_ends_with($name, '/word/document.xml')) {
                    $entry = $name;
                    break;
                }
            }

            if ($entry === null) {
                throw new RuntimeException('struktur .docx tidak dikenali (word/document.xml tidak ada)');
            }

            $xml = $zip->getFromName($entry);

            if ($xml === false || trim($xml) === '') {
                throw new RuntimeException('word/document.xml kosong / tidak terbaca');
            }

            if (!class_exists(DOMDocument::class)) {
                throw new RuntimeException('ekstensi PHP "dom" tidak aktif di server');
            }

            $dom = new DOMDocument;
            $prev = libxml_use_internal_errors(true);
            $ok = $dom->loadXML($xml);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            if (!$ok) {
                throw new RuntimeException('isi document.xml rusak / bukan XML valid');
            }

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $out = [];

            /** @var \DOMElement $p */
            foreach ($xpath->query('//w:body//w:p') as $p) {
                $text = '';

                /** @var \DOMElement $node */
                foreach ($xpath->query('.//w:t | .//w:tab | .//w:br', $p) as $node) {
                    if ($node->localName === 't') {
                        $text .= $node->textContent;
                    } elseif ($node->localName === 'tab') {
                        $text .= "\t";
                    } elseif ($node->localName === 'br') {
                        $text .= "\n";
                    }
                }

                $out[] = $text;
            }

            if ($out === []) {
                throw new RuntimeException('document.xml tidak berisi paragraf teks');
            }

            return $out;
        } finally {
            $zip->close();
        }
    }

    /**
     * Cadangan: parser resmi PhpWord (dipakai bila metode zip gagal).
     *
     * @return array<int, string>
     */
    protected function extractWithPhpWord(string $path): array
    {
        $phpWord = IOFactory::load($path);
        $out = [];

        foreach ($phpWord->getSections() as $wordSection) {
            foreach ($wordSection->getElements() as $element) {
                $out[] = $this->extractText($element);
            }
        }

        return $out;
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
