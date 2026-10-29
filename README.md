# Kuro AI — AI Reviewer Skripsi & Proposal (Laravel 12+)

**Kuro AI** membantu mahasiswa menyusun skripsi/proposal: AI menganalisis dokumen DOCX, memberi **highlight warna** pada teks bermasalah, memberi **komentar + saran revisi**, dan menerima **input suara otomatis**. Beranda terang ala Gemini (sidebar ungu muda + konten putih) dilengkapi **maskot Kuro melayang** dengan animasi saat menganalisis.

Integrasi AI cukup **2 driver**:
- **`gemini`** — Google Gemini (cukup `GEMINI_API_KEY`).
- **`openai-compatible`** — **multi-API**: 9Router online, OpenRouter, DeepSeek, OpenAI, Groq, Mistral, vLLM, dll. — cukup `base_url` + `api_key` + `model`. API berformat lain tetap bisa lewat `Ai::extend()`.

## Fitur unggulan

| Fitur | Cara kerja |
| --- | --- |
| 🎙️ **Voice suara** | Dikte langsung ke percakapan lewat mic (Speech Recognition, `id-ID`) — plus auto voice input untuk dokumen: rekam → berhenti otomatis saat diam → transkripsi Whisper-compatible → dirapikan ke bahasa akademik oleh LLM → masuk paragraf |
| 🧠 **Cek model** | Tombol **Lihat Model** di beranda & `GET /api/ai/models` menampilkan daftar model tiap provider (Gemini `models/`, openai-compatible `/models`) |
| 📄 **Periksa dokumen** | Unggah `.docx` dari beranda (drag & drop) → AI menganalisis bahasa, sitasi, metodologi, struktur BAB I–V → highlight warna + komentar + unduh DOCX berwarna + laporan review |
| 💬 **Riwayat percakapan** | Otomatis tersimpan ke **DATABASE** (`conversations` + `messages`) tiap AI menjawab; daftar tampil di sidebar (klik = buka, 🗑 = hapus), modal **Riwayat tersimpan** pakai daftar yang sama; cache browser sebagai fallback |
| 🐾 **Maskot Kuro** | Kucing Kuro melayang (SVG eksternal + animasi) — berkedip & memunculkan cincin scan saat menganalisis ("Kuro sedang menganalisis…") |
| 🔐 **Halaman masuk + beranda** | Welcome default Laravel **diganti**: `/login` password default **123456789** (ubah via `APP_LOGIN_PASSWORD`), `/` beranda terang ala Gemini — sidebar ungu muda + konten putih (Plus Jakarta Sans & Font Awesome via CDN, favicon bubble-K di `public/img/`) |
| DOCX | Parse per paragraf (deteksi BAB/sub-bab) → ekspor `.docx` dengan warna highlight + lampiran Laporan Review AI (phpoffice/phpword) |
| Tanda warna | merah = bahasa/typo/plagiarisme, kuning = tanpa sitasi, biru = metodologi, ungu = struktur, hijau = gaya akademik |
| Perbaikan revisi | `POST /highlights/{id}/accept` mengganti teks asli dengan `replacement`, snapshot versi otomatis, plus `accept-all` |
| Komentar | Komentar AI + manusia per paragraf, balasan (reply), tandai selesai (resolve) |

## Halaman web (masuk + beranda)

Route `routes/web.php` menggantikan halaman welcome bawaan Laravel:

| Method | Path | Fungsi |
| --- | --- | --- |
| GET | `/login` | Halaman masuk (password default `123456789`) |
| POST | `/login` · `/logout` | Proses masuk / keluar (sesi) |
| GET | `/` | **Beranda** ala Gemini: sidebar (percakapan baru, riwayat, navigasi, statistik), kartu pintasan, percakapan + dikte suara + riwayat tersimpan, panel periksa dokumen (unggah → review → temuan → unduh), integrasi AI + Lihat Model, form tambah provider, maskot Kuro |
| GET | `/models` · `/models/{slug}` | (AJAX, butuh login) daftar provider / daftar model satu provider |
| POST | `/chat` | (AJAX, butuh login) percakapan ke provider mana pun |
| GET/POST/DELETE | `/convs` · `/convs/{id}` | (AJAX, butuh login) riwayat percakapan tersimpan di database |

Integrasi **Gemini** cukup isi `GEMINI_API_KEY` di `.env`. Integrasi **9Router online**: driver `openai-compatible` + base URL instance kamu (`NINEROUTER_BASE_URL`, mis. `https://instance-9router.up.railway.app/v1`) + API key dari dashboard 9Router — atau tambahkan langsung dari form "Tambah Provider" di beranda.

## Instalasi

Repo ini sudah berisi **kerangka Laravel 12 lengkap** (artisan, `public/index.php`, config bawaan, migrasi users/cache/jobs, dsb.) plus fitur Kuro AI — jadi cukup clone + `composer install`.

### 0. Prasyarat

- PHP **8.2+** dengan ekstensi: `cli, xml, zip, gd, mbstring, curl, pdo_sqlite/pdo_mysql`
- **Composer**

```bash
# Linux/macOS
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Windows: unduh Composer-Setup.exe dari https://getcomposer.org
```

### 1. Clone & install

```bash
git clone https://github.com/kuro-myths/chat-dokumen-ai.git kuro-ai
cd kuro-ai
composer install          # laravel/framework, phpword, guzzle, sanctum, dst.
composer dump-autoload    # regenerate autoload class di app/Services
```

### 2. Environment & migrasi

```bash
cp .env.example .env
php artisan key:generate
# Tambah API key AI (opsional): GEMINI_API_KEY / NINEROUTER_* — atau lewat form di beranda
php artisan migrate
php artisan storage:link
```

### 3. Jalankan

```bash
php artisan serve       # buka http://127.0.0.1:8000 -> halaman masuk (password default 123456789)
php artisan queue:work  # terminal terpisah: review AI via queue (opsional — QUEUE_CONNECTION=sync jalan tanpa ini)
```

### 4. Deploy ke Railway

Repo sudah siap deploy (ada `Procfile` + kerangka lengkap):

1. Railway → **New Project** → **Deploy from GitHub repo** → pilih repo ini (branch `main`).
2. Build otomatis via Nixpacks (`composer install`), lalu `Procfile` dijalankan:

   ```
   cp -n .env.example .env; php artisan key:generate --force; php artisan migrate --force; php artisan storage:link; php artisan serve --host 0.0.0.0 --port=$PORT
   ```

3. Buka domain `https://<service>.up.railway.app` → halaman masuk Kuro AI (password default `123456789`).
4. (Opsional) Tab **Variables**: isi `GEMINI_API_KEY` / `NINEROUTER_BASE_URL` + `NINEROUTER_API_KEY` / `APP_LOGIN_PASSWORD`.

Catatan Railway:
- **SQLite ephemeral** — database & file unggahan hilang tiap redeploy. Untuk persistensi: pasang **Volume** (mount ke `/app/database`) atau ganti ke Postgres/MySQL (ubah `DB_CONNECTION` + variabel `DB_*`).
- `QUEUE_CONNECTION=sync` (default `.env.example`) → review dokumen jalan langsung tanpa worker. Mau queue asli? Set `QUEUE_CONNECTION=database` lalu buat service kedua dengan start command `php artisan queue:work`.
- Log aplikasi tampil di deploy logs (`LOG_CHANNEL=stderr`).
- SSL/https ditangani Railway; `bootstrap/app.php` sudah `trustProxies(at: '*')` supaya URL `https://` benar.

### 5. Troubleshooting instalasi

| Error | Solusi |
| --- | --- |
| `class "App\Providers\AiServiceProvider" not found` | `composer dump-autoload`, pastikan sudah didaftarkan di `bootstrap/providers.php` |
| `ext-zip missing` saat install phpword | `sudo apt install php8.3-zip` lalu ulangi `composer require` |
| `ext-gd missing` | `sudo apt install php8.3-gd` |
| `ext-dom missing` | `sudo apt install php8.3-xml` |
| Review tidak pernah selesai | queue tidak jalan — jalankan `php artisan queue:work` (atau pakai `QUEUE_CONNECTION=sync`) |
| `could not find driver` (database) | aktifkan `pdo_sqlite`/`pdo_mysql` sesuai koneksi di `.env` |
| Halaman `/` masih tampil welcome Laravel | pastikan `routes/web.php` dari repo ini yang terpasang (welcome route bawaan tertimpa) |
| Tombol "Lihat Model" error `Provider belum dikonfigurasi` | API key / base URL provider kosong — isi di `.env` atau tambahkan via form di beranda |
| Dikte suara tidak jalan | Gunakan Chrome/Edge terbaru (fitur Speech Recognition) dan izinkan akses mikrofon |
| Ikon tidak muncul | Pastikan koneksi internet aktif (Font Awesome & Google Fonts dimuat via CDN) |
| Railway 404 | Pastikan deploy dari branch `main` terbaru (repo lama belum berisi artisan/index.php), cek deploy logs |

## Struktur file tambahan

```text
config/ai.php
routes/web.php                          # halaman masuk + beranda Kuro AI (mengganti welcome default)
app/Providers/AiServiceProvider.php
app/Services/Ai/
├── AiManager.php                  # pusat integrasi: driver gemini + openai-compatible (multi-API)
├── AiModelService.php              # cek model AI tiap provider (Gemini / 9Router / dll.)
├── AiResponse.php
├── Contracts/AiProviderInterface.php
├── Drivers/
│   ├── GeminiDriver.php            # Google Gemini (GEMINI_API_KEY)
│   └── OpenAiCompatibleDriver.php  # MULTI-API: 9Router, OpenRouter, OpenAI, DeepSeek, dll.
├── Facades/Ai.php
└── Prompts/ThesisReviewPrompts.php
app/Services/Docx/
├── DocxParserService.php          # .docx -> paragraf
└── DocxExportService.php          # paragraf + warna -> .docx
app/Services/Review/
├── ThesisReviewService.php        # orkestrasi review AI
└── RevisionService.php            # terima/tolak revisi
app/Services/Voice/VoiceTranscriptionService.php
app/Http/Controllers/Api/          # Document, Review, Highlight, Comment, Voice, Provider, Model
app/Http/Controllers/Web/         # AuthController (login) + HomeController (beranda, model, chat) + ConvController (riwayat percakapan)
app/Http/Requests/                # validasi upload
app/Jobs/RunThesisReview.php
app/Models/                       # Document, DocumentSection, DocumentVersion, Comment, Highlight, Transcription, AiSetting, Conversation, Message
database/migrations/2026_10_28_000001_create_skripsi_ai_tables.php
database/migrations/2026_10_29_000001_create_conversations_tables.php # riwayat percakapan
resources/views/                   # layouts/app (CDN: Font Awesome + Google Fonts), login, beranda
public/css/kuro.css                # stylesheet Kuro AI (eksternal, tanpa style inline)
public/js/kuro.js                  # logika beranda (eksternal, tanpa script inline)
public/img/kuro.svg                # maskot Kuro (SVG + animasi SMIL)
public/img/kuro-logo.svg           # logo & favicon bubble-K
routes/api.php
resources/js/voice-input.js        # auto-stop silence detection + auto insert
```

## Contoh alur pemakaian (API)

```bash
# 1. Upload proposal
curl -F "document=@proposal.docx" -F "type=proposal" http://localhost:8000/api/documents

# 2. Jalankan review AI (async)
curl -X POST http://localhost:8000/api/documents/1/review

# 3. Cek hasil: highlight warna + komentar AI
curl http://localhost:8000/api/documents/1/review

# 4. Terima satu revisi (teks otomatis diperbaiki)
curl -X POST http://localhost:8000/api/highlights/3/accept

# 5. Terima semua revisi sekaligus
curl -X POST http://localhost:8000/api/documents/1/highlights/accept-all

# 6. Unduh .docx dengan highlight + laporan review
curl -OJ http://localhost:8000/api/documents/1/export

# 7. Dikte suara -> teks otomatis masuk paragraf 4
curl -F "audio=@catatan.webm" -F "document_id=1" -F "section_index=4" \
     http://localhost:8000/api/voice/transcribe
```

## Menambahkan provider AI

Semua provider dengan **endpoint kompatibel OpenAI** (9Router, OpenRouter, DeepSeek, vLLM, LiteLLM, dst.) tidak perlu kode — tambahkan dari beranda (form "Tambah Provider") atau via API:

```bash
# Contoh: 9Router online
curl -X POST http://localhost:8000/api/ai/providers \
  -H "Content-Type: application/json" \
  -d '{"slug":"9router","name":"9Router Online","driver":"openai-compatible",
       "base_url":"https://instance-9router.up.railway.app/v1","model":"gpt-4o-mini",
       "api_key":"sk-...","is_default":true}'
```

API dengan format lain, satu closure di `AppServiceProvider::boot()`:

```php
use App\Services\Ai\Facades\Ai;

Ai::extend('providerku', function (string $name, array $config) {
    return new class($name, $config) implements AiProviderInterface {
        // implementasikan chat() dan stream()
    };
});
```

## Endpoint

| Method | Path | Fungsi |
| --- | --- | --- |
| GET/POST | `/api/documents` | Daftar / upload dokumen DOCX |
| GET | `/api/documents/{id}` | Detail + paragraf + highlight + komentar |
| POST | `/api/documents/{id}/sections` | Tambah paragraf manual |
| GET | `/api/documents/{id}/export` | Unduh DOCX berwarna + laporan |
| POST | `/api/documents/{id}/review` | Review AI via queue |
| POST | `/api/documents/{id}/review/sync` | Review AI sinkron |
| GET | `/api/documents/{id}/review` | Status review + ringkasan |
| GET | `/api/documents/{id}/highlights` | Semua temuan AI |
| POST | `/api/highlights/{id}/accept`·`/reject` | Terima / tolak revisi |
| POST | `/api/documents/{id}/highlights/accept-all` | Terima semua |
| GET/POST | `/api/documents/{id}/comments` | Komentar AI & manusia |
| POST/PATCH | `/api/comments/{id}/reply`·`/resolve` | Balas / selesaikan |
| POST | `/api/voice/transcribe` | Auto voice → transkripsi → insert |
| GET/POST/PUT | `/api/ai/providers` | Kelola provider AI |
| GET | `/api/ai/models` (`?fetch=1`) | Daftar provider + modelnya |
| GET | `/api/ai/models/{slug}` | Daftar model satu provider |

## Catatan

- API key di tabel `ai_settings` disimpan dengan **encrypted cast** Laravel.
- Untuk dokumen panjang, gunakan route review async + `queue:work` (timeout job 15 menit).
- Voice memakai endpoint kompatibel Whisper (`/audio/transcriptions`); bisa diarahkan ke Groq (whisper-large-v3) atau server lokal whisper.cpp via `VOICE_BASE_URL`. Dikte di beranda memakai Speech Recognition bawaan browser (Chrome/Edge).
- Riwayat percakapan tersimpan di **database** (migration `2026_10_29_000001_create_conversations_tables`, model `Conversation`/`Message`); localStorage hanya cache fallback.
- Halaman web memakai sesi Laravel — pastikan `SESSION_DRIVER` aktif (default `database`/`file` sudah cukup).
- Front-end **tanpa kode inline**: CSS di `public/css/kuro.css`, JS di `public/js/kuro.js`, ikon via **Font Awesome CDN**, font via **Google Fonts CDN**. Class custom memakai prefiks sendiri (`kuro-`, `side-`, `q-`, `prov-`, `doc-`, `hl-`, `hist-`, `chat-`, `login-`) sehingga tidak bentrok dengan library CDN (`fa-*`).
- Favicon berupa file `public/img/kuro-logo.svg` (bubble-K) — bukan lagi SVG inline.
- `bootstrap/app.php` memuat route API + `trustProxies(at: '*')` (diperlukan di balik reverse proxy seperti Railway/nginx).
