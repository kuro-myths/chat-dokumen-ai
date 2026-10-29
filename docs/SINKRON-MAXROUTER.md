# Sinkron Max Router & Mode AI (Gemini / Manual)

Kuro AI (chat-dokumen-ai) tersinkron dengan **Max Router (9Router V3)** —
integrasi AI berlaku **online** (router di Railway) maupun **offline/lokal**
(router di `http://localhost:3001`).

## Endpoint baru

| Endpoint | Fungsi |
|---|---|
| `GET /api/ai/setup` | Setup saat ini: mode aktif, status key Gemini & Manual |
| `POST /api/ai/setup` | Simpan mode (`gemini`/`manual`) + API key / base URL / model |
| `GET /api/ai/router/status` | Probe router **online** & **lokal** (`/api/health` + latensi) |
| `POST /api/ai/router/sync` | Sinkron: tarik `/v1/models`, daftar konsumen ke router, perbarui provider `manual` |

Router menyediakan pasangannya (repo max-router, folder `backend/src/routes/dokumen-ai/`):
`GET /api/dokumen-ai/status` dan `POST /api/dokumen-ai/sync`.

## Dua dropdown tanpa bentrok

- **Gemini** — API key gratis dari <https://aistudio.google.com/api-keys>.
- **Manual** — Max Router / API kompatibel OpenAI apa pun: pilih sumber
  **Online (Railway)** atau **Lokal (localhost:3001)**, isi base URL + API key + model.

Keduanya disimpan di tabel `ai_settings` (slug `gemini` dan `manual`).
Mode menentukan mana yang default — berganti mode tidak menghapus konfigurasi
dropdown satunya, jadi tidak pernah bentrok.

## Online ↔ offline otomatis

Saat **Sinkron Sekarang**:

1. Kuro AI mengecek `MAXROUTER_ONLINE_URL` (Railway) → kalau hidup dipakai;
2. kalau tidak terjangkau → otomatis fallback ke `MAXROUTER_BASE_URL` (lokal);
3. provider **manual** di database diarahkan ke base URL aktif tersebut,
   jadi chat & review dokumen tetap jalan saat offline.

## Environment (opsional — semua bisa diatur dari UI)

```env
AI_MODE=gemini                                # gemini | manual
MAXROUTER_ONLINE_URL=https://instance.up.railway.app/v1
MAXROUTER_BASE_URL=http://localhost:3001/v1
MAXROUTER_API_KEY=
MAXROUTER_MODEL=gpt-4o-mini
GEMINI_API_KEY=                               # dari aistudio.google.com/api-keys
```

## Jalan lokal (tanpa internet untuk Gemini tidak masalah — pakai router lokal)

```bash
# Terminal 1 — router
cd max-router && npm install && npm run backend   # :3001

# Terminal 2 — Kuro AI
cp .env.example .env
php artisan migrate
php artisan serve                                  # :8000
```

Lalu di beranda: **Sinkron Router** → mode **Manual** → sumber **Lokal** →
**Sinkron Sekarang** → **Simpan Mode & API Key**.
