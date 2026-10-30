<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Highlight;
use App\Services\Ai\AiModelService;
use App\Services\Ai\Facades\Ai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Halaman beranda Kuro AI: ringkasan dokumen, integrasi AI (Gemini,
 * 9Router, dll.), lihat model, percakapan + dikte suara, dan
 * pemeriksaan dokumen.
 */
class HomeController extends Controller
{
    public function index()
    {
        if (! session('logged_in')) {
            return redirect()->route('login');
        }

        return view('beranda', ['stats' => $this->stats()]);
    }

    /** GET /models — JSON daftar semua provider (config + database). */
    public function models(): JsonResponse
    {
        if (! session('logged_in')) {
            return response()->json(['ok' => false, 'error' => 'Belum masuk.'], 401);
        }

        $providers = app(AiModelService::class)->providers();

        return response()->json([
            'ok' => true,
            'default' => $this->defaultSlug(),
            'providers' => $providers,
        ]);
    }

    /** GET /models/{slug} — JSON daftar model satu provider (dipanggil dari UI). */
    public function showModels(string $slug): JsonResponse
    {
        if (! session('logged_in')) {
            return response()->json(['ok' => false, 'error' => 'Belum masuk.'], 401);
        }

        return response()->json(app(AiModelService::class)->models($slug));
    }

    /** POST /chat — uji coba integrasi AI langsung dari beranda. */
    public function chat(Request $request): JsonResponse
    {
        if (! session('logged_in')) {
            return response()->json(['ok' => false, 'error' => 'Belum masuk.'], 401);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
            'provider' => ['nullable', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:120'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:8000'],
            'document_id' => ['nullable', 'integer'],   // sertakan isi dokumen ini
            'document_from' => ['nullable', 'integer'],  // opsional: hanya BAB terpilih
            'document_to' => ['nullable', 'integer'],
            'concise' => ['nullable', 'boolean'], // mode ⚡ Singkat & Jelas
        ]);

        // Konteks dokumen: bila document_id dikirim, ISI DOKUMEN (atau hanya
        // rentang BAB terpilih) ikut disertakan ke AI — chat pun "melihat"
        // dokumen secara utuh dan bisa menjawab pertanyaan tentang isinya.
        $context = '';
        if (! empty($data['document_id'])) {
            $docCtx = Document::with('sections')->find((int) $data['document_id']);
            if ($docCtx) {
                $secs = $docCtx->sections->sortBy('index')->values();
                if (! empty($data['document_from']) || ! empty($data['document_to'])) {
                    $f = (int) ($data['document_from'] ?? PHP_INT_MIN);
                    $t = (int) ($data['document_to'] ?? PHP_INT_MAX);
                    $secs = $secs->filter(fn ($s) => $s->index >= $f && $s->index <= $t)->values();
                }
                $docText = $secs
                    ->map(fn ($s) => ($s->heading ? '['.$s->heading.'] ' : '').$s->body)
                    ->implode("\n\n");
                if (mb_strlen($docText) > 30000) {
                    $docText = mb_substr($docText, 0, 30000).' …(teks dokumen dipotong karena panjang)';
                }
                $context = "\n\n=== ISI DOKUMEN \"".$docCtx->title."\" ===\n".$docText;
            }
        }

        // Mode ⚡ "Singkat & Jelas": user minta jawaban super ringkas lewat toggle.
        $style = ! empty($data['concise'])
            ? "\n\nGAYA JAWABAN (WAJIB DIPATUHI): SINGKAT dan JELAS. Maksimal 3-5 "
                ."kalimat atau 3-5 poin saja. Langsung ke inti, tanpa pembuka/penutup "
                ."basa-basi, tanpa penjelasan panjang. Pakai poin singkat bila membantu."
            : '';

        $history = collect($data['history'] ?? [])
            ->map(fn ($m) => ['role' => $m['role'], 'content' => mb_substr($m['content'], 0, 8000)])
            ->values()
            ->all();

        $messages = array_merge(
            [[
                'role' => 'system',
                // Chat santai — JANGAN bahasa akademik/skripsi. Prompt akademik
                // khusus untuk review dokumen ada di ThesisReviewPrompts.
                'content' => 'Kamu adalah Kuro AI, teman ngobrol berbahasa Indonesia yang ramah dan santai. '
                    .'Jawab ringkas, jelas, dan santai seperti chat dengan teman — pakai bahasa '
                    .'sehari-hari (boleh sapaan kamu dan aku, sedikit emoji), HINDARI bahasa '
                    .'formal akademik atau istilah skripsi kecuali user memintanya. Tetap akurat '
                    .'dan membantu untuk topik apa pun yang ditanya user.'
                    .($context !== ''
                        ? ' PENTING: di bawah disertakan ISI DOKUMEN yang sedang dibuka user. '
                        .'Untuk pertanyaan tentang dokumen itu, jawab BERDASARKAN isinya '
                        .'(sebut bab/bagian dan halaman bila relevan) — jangan mengaku tidak '
                        .'bisa membacanya.'
                        : '')
                    .$style
                    .$context,
            ]],
            $history,
            [['role' => 'user', 'content' => $data['message']]],
        );

        try {
            // Model bisa dipilih per pesan lewat dropdown model di composer.
            // Null / kosong -> pakai model default provider (config / ai_settings).
            $options = ['timeout' => 60];
            if (! empty($data['model'])) {
                $options['model'] = $data['model'];
            }

            // chatAuto: bila provider/model pilihan gagal (503, pensiun, kuota,
            // dsb.) otomatis dicoba model & provider lain sampai ada yang hidup.
            $response = Ai::chatAuto($messages, $options, $data['provider'] ?? null);

            return response()->json([
                'ok' => true,
                'content' => $response->content,
                'provider' => $response->provider,
                'model' => $response->model,
            ]);
        } catch (Throwable $e) {
            $message = 'Gagal memanggil AI: '.$e->getMessage();

            // Model menabrak provider tanpa kredensial aktif di router — arahkan
            // user memilih model lain lewat dropdown model (bukan default gpt-4o).
            if (preg_match('/no active credentials|model_not_found|model_not_exists/i', $message)) {
                $message .= ' — Model ini tidak aktif di router. Buka dropdown model di samping '
                    .'dropdown provider, lalu pilih model lain yang tersedia (atau tekan ikon '
                    .'muat ulang di dropdown untuk memperbarui daftar).';
            }

            return response()->json([
                'ok' => false,
                'error' => $message,
            ], 502);
        }
    }

    /** Statistik ringkas untuk kartu di beranda (aman walau tabel belum dimigrasi). */
    protected function stats(): array
    {
        try {
            return [
                'documents' => Document::count(),
                'highlights' => Highlight::where('status', 'pending')->count(),
                'comments' => Comment::count(),
            ];
        } catch (Throwable) {
            return ['documents' => null, 'highlights' => null, 'comments' => null];
        }
    }

    protected function defaultSlug(): ?string
    {
        try {
            return app(AiModelService::class)->defaultSlug();
        } catch (Throwable) {
            return config('ai.default');
        }
    }
}
