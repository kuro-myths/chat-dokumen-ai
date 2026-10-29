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
 * Halaman beranda: ringkasan dokumen, integrasi AI (Gemini, 9Router,
 * OpenAI, dll.), lihat model per provider, dan uji coba chat.
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
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:8000'],
        ]);

        $history = collect($data['history'] ?? [])
            ->map(fn ($m) => ['role' => $m['role'], 'content' => mb_substr($m['content'], 0, 8000)])
            ->values()
            ->all();

        $messages = array_merge(
            [[
                'role' => 'system',
                'content' => 'Kamu adalah SkripsiAI, asisten akademik berbahasa Indonesia. '
                    .'Jawab ringkas, jelas, dan membantu untuk penyusunan skripsi/proposal.',
            ]],
            $history,
            [['role' => 'user', 'content' => $data['message']]],
        );

        try {
            $provider = Ai::provider($data['provider'] ?? null);
            $response = $provider->chat($messages, ['timeout' => 60]);

            return response()->json([
                'ok' => true,
                'content' => $response->content,
                'provider' => $response->provider,
                'model' => $response->model,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Gagal memanggil AI: '.$e->getMessage(),
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
