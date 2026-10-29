<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Riwayat percakapan — tersimpan di DATABASE (tabel conversations +
 * messages). Dipakai sidebar "Percakapan" dan modal "Riwayat tersimpan".
 */
class ConvController extends Controller
{
    /** GET /convs — daftar percakapan terbaru. */
    public function index(): JsonResponse
    {
        if (! session('logged_in')) {
            return response()->json(['ok' => false, 'error' => 'Belum masuk.'], 401);
        }

        $convs = Conversation::query()
            ->orderByDesc('updated_at')
            ->limit(60)
            ->get();

        return response()->json([
            'ok' => true,
            'conversations' => $convs->map(fn (Conversation $c) => [
                'id' => $c->id,
                'title' => $c->title ?: 'Percakapan',
                'provider' => $c->provider,
                'msg_count' => (int) $c->msg_count,
                'time' => $c->updated_at?->format('d/m/Y H:i'),
            ])->values()->all(),
        ]);
    }

    /** POST /convs — simpan / perbarui satu percakapan (upsert). */
    public function store(Request $request): JsonResponse
    {
        if (! session('logged_in')) {
            return response()->json(['ok' => false, 'error' => 'Belum masuk.'], 401);
        }

        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:120'],
            'provider' => ['nullable', 'string', 'max:60'],
            'messages' => ['required', 'array', 'max:200'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['nullable', 'string', 'max:16000'],
            'messages.*.type' => ['nullable', 'string', 'max:16'],
            'messages.*.image' => ['nullable', 'string', 'max:60000'],
            'messages.*.video' => ['nullable', 'string', 'max:60000'],
        ]);

        $messages = array_values($data['messages']);

        // Judul otomatis: pesan user pertama (bila judul tidak dikirim).
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            foreach ($messages as $m) {
                if (($m['role'] ?? '') === 'user' && ! empty($m['content'])) {
                    $title = mb_substr(trim((string) preg_replace('/\s+/', ' ', $m['content'])), 0, 60);
                    break;
                }
            }
        }
        if ($title === '') {
            $title = 'Percakapan';
        }

        $conv = null;
        if (! empty($data['id'])) {
            $conv = Conversation::find((int) $data['id']);
        }
        $conv ??= new Conversation();

        $conv->title = $title;
        $conv->provider = $data['provider'] ?? $conv->provider;
        $conv->msg_count = count($messages);
        $conv->save();

        // Ganti seluruh isi percakapan (sederhana & idempoten).
        $conv->messages()->delete();
        foreach ($messages as $m) {
            $conv->messages()->create([
                'role' => $m['role'],
                'content' => mb_substr((string) ($m['content'] ?? ''), 0, 16000),
                'type' => ($m['type'] ?? '') ?: 'text',
                'media_url' => $m['image'] ?? $m['video'] ?? null,
            ]);
        }

        return response()->json(['ok' => true, 'id' => $conv->id]);
    }

    /** GET /convs/{id} — isi satu percakapan (daftar pesan). */
    public function show(int $id): JsonResponse
    {
        if (! session('logged_in')) {
            return response()->json(['ok' => false, 'error' => 'Belum masuk.'], 401);
        }

        $conv = Conversation::with('messages')->find($id);
        if (! $conv) {
            return response()->json(['ok' => false, 'error' => 'Percakapan tidak ditemukan.'], 404);
        }

        $messages = $conv->messages
            ->map(function (Message $m) {
                $item = ['role' => $m->role, 'content' => (string) $m->content];

                if ($m->type === 'image' && $m->media_url) {
                    $item['type'] = 'image';
                    $item['image'] = $m->media_url;
                } elseif ($m->type === 'video' && $m->media_url) {
                    $item['type'] = 'video';
                    $item['video'] = $m->media_url;
                }

                return $item;
            })
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'conversation' => [
                'id' => $conv->id,
                'title' => $conv->title,
                'provider' => $conv->provider,
                'messages' => $messages,
            ],
        ]);
    }

    /** DELETE /convs/{id} — hapus satu percakapan (pesan ikut terhapus). */
    public function destroy(int $id): JsonResponse
    {
        if (! session('logged_in')) {
            return response()->json(['ok' => false, 'error' => 'Belum masuk.'], 401);
        }

        Conversation::find($id)?->delete();

        return response()->json(['ok' => true]);
    }

    /** DELETE /convs — hapus semua percakapan. */
    public function clearAll(): JsonResponse
    {
        if (! session('logged_in')) {
            return response()->json(['ok' => false, 'error' => 'Belum masuk.'], 401);
        }

        Conversation::query()->delete(); // messages ikut terhapus via cascade FK

        return response()->json(['ok' => true]);
    }
}
