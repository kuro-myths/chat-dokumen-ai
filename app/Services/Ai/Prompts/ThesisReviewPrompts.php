<?php

namespace App\Services\Ai\Prompts;

use App\Models\DocumentSection;

class ThesisReviewPrompts
{
    /**
     * System prompt reviewer skripsi/proposal dengan skema JSON ketat,
     * sehingga hasil review bisa langsung menjadi highlight + komentar.
     */
    public static function system(string $mode = 'skripsi'): string
    {
        $doc = $mode === 'proposal' ? 'proposal penelitian' : 'skripsi (BAB I–V)';

        return <<<TXT
        Kamu adalah dosen pembimbing sekaligus reviewer {$doc} dan editor bahasa
        Indonesia baku. Tugasmu menganalisis paragraf yang diberi nomor dan
        menemukan masalah secara presisi.

        ATURAN OUTPUT — WAJIB:
        - Balas HANYA dengan satu objek JSON valid, tanpa teks lain.
        - quote HARUS kutipan PERSIS (copy-paste) dari paragraf, maksimal 15 kata.
        - paragraph HARUS nomor paragraf dari input, dalam bentuk angka.
        - replacement hanya diisi jika perbaikan bersifat ganti teks (typo, EYD,
          kalimat rancu). Jika hanya saran, isi null.

        SKEMA JSON:
        {
          "issues": [
            {
              "paragraph": 0,
              "quote": "...",
              "type": "grammar|citation|methodology|structure|style",
              "severity": "high|medium|low",
              "color": "red|yellow|blue|purple|green",
              "comment": "penjelasan singkat dalam Bahasa Indonesia",
              "suggestion": "saran perbaikan konkret",
              "replacement": "teks pengganti atau null"
            }
          ],
          "overall": "ringkasan kualitas dokumen 2-3 kalimat",
          "score": 0
        }

        MAKNA WARNA (digunakan untuk highlight otomatis di dokumen):
        - red    : kesalahan bahasa fatal, typo, atau indikasi plagiarisme
        - yellow : klaim/data tanpa sitasi sumber
        - blue   : masalah metodologi (populasi, sampel, instrumen, analisis data)
        - purple : struktur tidak sesuai sistematika {$doc}
        - green  : gaya penulisan kurang akademik atau kurang jelas

        Fokus review {$mode}: rumusan masalah, tujuan, metodologi, sitasi,
        konsistensi istilah, bahasa baku PUEBI/EYD, dan kelayakan logika.
        Maksimal 20 temuan per analisis, pilih yang paling penting.
        TXT;
    }

    /** Bangun user prompt dari batch paragraf. */
    public static function userPrompt(iterable $sections, string $mode = 'skripsi'): string
    {
        $lines = ["MODE DOKUMEN: {$mode}", ''];

        /** @var DocumentSection $section */
        foreach ($sections as $section) {
            $lines[] = "[PARAGRAF {$section->index}]"
                .($section->heading ? " (JUDUL BAGIAN: {$section->heading})" : '');
            $lines[] = $section->body;
            $lines[] = '';
        }

        $lines[] = 'Analisis paragraf di atas dan balas HANYA dengan JSON sesuai skema.';

        return implode("\n", $lines);
    }

    /** Prompt perapian hasil transkripsi voice menjadi kalimat akademik. */
    public static function polishTranscript(string $raw): array
    {
        return [
            [
                'role' => 'system',
                'content' => 'Ubah transkrip suara mentah menjadi kalimat akademik formal '
                    .'dalam Bahasa Indonesia: perbaiki tanda baca dan kapitalisasi, hapus '
                    .'pengisi seperti "ehm/oke/anu" dan kata sapaan, rapikan struktur kalimat. '
                    .'JANGAN mengubah makna, '
                    .'jangan menambah isi baru. Balas HANYA teks hasilnya.',
            ],
            ['role' => 'user', 'content' => $raw],
        ];
    }
}
