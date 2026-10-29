# SkripsiAI — AI Reviewer Skripsi & Proposal (Laravel 12+)

Aplikasi Laravel yang membantu mahasiswa menyusun skripsi/proposal: AI menganalisis dokumen DOCX, memberi **highlight warna** pada teks bermasalah, memberi **komentar + saran revisi**, dan menerima input suara otomatis (**auto voice input**).

Integrasi AI cukup **2 driver**:
- **`gemini`** — Google Gemini (cukup `GEMINI_API_KEY`).
- **`openai-compatible`** — **multi-API**: 9Router online, OpenRouter, DeepSeek, OpenAI, Groq, Mistral, vLLM, dll. — cukup `base_url` + `api_key` + `model`. API berformat lain tetap bisa lewat `Ai::extend()`.

> Dibangun untuk Laravel 12+ (PHP 8.2+). Kompatibel dengan struktur Laravel 11/12/13 (routing dan service container sama).

## Fitur

| Fitur | Cara kerja |
| --- | --- |
| Halaman masuk + beranda | Halaman welcome default Laravel (`Let's get started`) **diganti**: `/` kini halaman beranda yang dilindungi halaman masuk `/login` — password default **123456789** (ubah via `APP_LOGIN_PASSWORD`), sesi berbasis cookie |
| Integrasi AI: Gemini + multi-API | Driver pattern (`AiManager`): driver `gemini` + driver `openai-compatible` untuk 9Router & gateway lain, `Ai::extend()` untuk format kustom, tabel `ai_settings` agar user menambah provider dari beranda tanpa deploy ulang |
| Lihat model AI | `GET /api/ai/models` & tombol **Lihat Model** di beranda menampilkan daftar model tiap provider (Gemini `models/`, openai-compatible `/models`) |
| DOCX | Upload `.docx` → diparse per paragraf (deteksi BAB/sub-bab) → ekspor kembali `.docx` dengan warna highlight + lampiran Laporan Review AI (phpoffice/phpword) |
| Review skripsi/proposal | Paragraf di-batch → AI menganalisis bahasa, sitasi, metodologi, struktur BAB I–V → tiap temuan jadi `Highlight` + `Comment` |
| Tanda warna | merah = bahasa/typo/plagiarisme, kuning = tanpa sitasi, biru = metodologi, ungu = struktur, hijau = gaya akademik |
| Perbaikan revisi | `POST /highlights/{id}/accept` mengganti teks asli dengan `replacement`, snapshot versi otomatis, plus `accept-all` |
| Komentar | Komentar AI + manusia per paragraf, balasan (reply), tandai selesai (resolve) |
| Auto voice input | Rekam → berhenti otomatis saat diam → transkripsi Whisper-compatible → dirapikan ke bahasa akademik oleh LLM → teks otomatis masuk dokumen |

## Halaman web (masuk + beranda)

Route `routes/web.php` menggantikan halaman welcome bawaan Laravel:

| Method | Path | Fungsi |
| --- | --- | --- |
| GET | `/login` | Halaman masuk (password default `123456789`) |
| POST | `/login` · `/logout` | Proses masuk / keluar (sesi) |
| GET | `/` | **Beranda**: statistik dokumen, kartu provider AI (Gemini, 9Router, dll.), tombol *Lihat Model*, uji coba chat, form tambah provider |
| GET | `/models` · `/models/{slug}` | (AJAX, butuh login) daftar provider / daftar model satu provider |
| POST | `/chat` | (AJAX, butuh login) uji coba chat ke provider mana pun |

Integrasi **Gemini** cukup isi `GEMINI_API_KEY` di `.env`. Integrasi **9Router online**: driver `openai-compatible` + base URL instance kamu (`NINEROUTER_BASE_URL`, mis. `https://instance-9router.up.railway.app/v1`) + API key dari dashboard 9Router — atau tambahkan langsung dari form "Tambah Provider" di beranda.

## Instalasi

### 0. Prasyarat

- PHP **8.2+** dengan ekstensi: `cli, xml, zip, gd, mbstring, curl, pdo_sqlite/pdo_mysql`
- **Composer**

Install Composer (kalau belum ada):

```bash
# Linux/macOS
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Windows: unduh Composer-Setup.exe dari https://getcomposer.org
```

Cek: `composer --version`

Ekstensi PHP yang dibutuhkan (Debian/Ubuntu):

```bash
sudo apt install php8.3-zip php8.3-gd php8.3-xml php8.3-curl php8.3-mbstring
```

### 1. Buat project + dependensi

```bash
# Buat project Laravel baru
laravel new skripsi-ai
# atau: composer create-project laravel/laravel skripsi-ai
cd skripsi-ai

# Aktifkan route API + queue database
php artisan install:api
php artisan install:database  # atau konfigurasi .env + php artisan migrate

# Dependensi proyek
composer require phpoffice/phpword   # parse & export DOCX + highlight warna
composer require guzzlehttp/guzzle    # HTTP client untuk semua driver AI (default Laravel, pastikan ada)
```

> Facade `Http::` Laravel membutuhkan Guzzle (Laravel 11+ sudah include). Yang **wajib** tambahan hanya `phpoffice/phpword`.

### 2. Clone repo ini & install dependensi langsung

Repo ini sudah menyertakan `composer.json` lengkap, jadi cukup:

```bash
git clone https://github.com/kuro-myths/chat-dokumen-ai.git skripsi-ai
cd skripsi-ai
composer install          # generate vendor/ (laravel/framework, phpword, guzzle, sanctum, dst.)
composer dump-autoload    # regenerate autoload untuk class di app/Services
```

> Catatan: repo ini berisi file tambahan di atas kerangka Laravel standar. Jika kamu mulai dari `laravel new` (Cara A di atas), salin folder `app/`, `config/`, `database/migrations/`, `routes/`, `resources/` ke path yang sama, lalu jalankan `composer dump-autoload`. `routes/web.php` repo ini menimpa route welcome bawaan Laravel.

Daftarkan provider di `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AiServiceProvider::class,
];
```

### 3. Environment & migrasi

```bash
cp .env.example .env
php artisan key:generate
cat env.example.additions >> .env   # tambah GEMINI_API_KEY, NINEROUTER_*, APP_LOGIN_PASSWORD, dll.
php artisan migrate
php artisan storage:link           # supaya file upload/DOCX bisa diakses
```

### 4. Jalankan

```bash
php artisan serve       # buka http://127.0.0.1:8000 -> halaman masuk (password default 123456789)
php artisan queue:work  # WAJIB di terminal terpisah: review AI diproses via queue
```

Minimal isi `GEMINI_API_KEY` di `.env`, atau isi `NINEROUTER_BASE_URL` + `NINEROUTER_API_KEY` untuk 9Router online, atau tambahkan provider kompatibel OpenAI lain dari form di beranda.

### 5. Troubleshooting instalasi

| Error | Solusi |
| --- | --- |
| `class "App\Providers\AiServiceProvider" not found` | `composer dump-autoload`, pastikan sudah didaftarkan di `bootstrap/providers.php` |
| `ext-zip missing` saat install phpword | `sudo apt install php8.3-zip` lalu ulangi `composer require` |
| `ext-gd missing` | `sudo apt install php8.3-gd` |
| `ext-dom missing` | `sudo apt install php8.3-xml` |
| Review tidak pernah selesai | queue tidak jalan — jalankan `php artisan queue:work` |
| `could not find driver` (database) | aktifkan `pdo_sqlite`/`pdo_mysql` sesuai koneksi di `.env` |
| Halaman `/` masih tampil welcome Laravel | pastikan `routes/web.php` dari repo ini yang terpasang (welcome route bawaan tertimpa) |
| Tombol "Lihat Model" error `Provider belum dikonfigurasi` | API key / base URL provider kosong — isi di `.env` atau tambahkan via form di beranda |

## Struktur file tambahan

```text
config/ai.php
routes/web.php                          # halaman masuk + beranda (mengganti welcome default)
app/Providers/AiServiceProvider.php
app/Services/Ai/
├── AiManager.php                  # pusat integrasi: driver gemini + openai-compatible (multi-API)
├── AiModelService.php              # lihat model AI tiap provider (Gemini / 9Router / dll.)
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
app/Http/Controllers/Web/          # AuthController (login) + HomeController (beranda, model, chat)
app/Http/Requests/                # validasi upload
app/Jobs/RunThesisReview.php
app/Models/                       # Document, DocumentSection, DocumentVersion, Comment, Highlight, Transcription, AiSetting
database/migrations/2026_10_28_000001_create_skripsi_ai_tables.php
resources/views/                  # layouts/app, login, beranda
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
- Voice memakai endpoint kompatibel Whisper (`/audio/transcriptions`); bisa diarahkan ke Groq (whisper-large-v3) atau server lokal whisper.cpp via `VOICE_BASE_URL`.
- Halaman web memakai sesi Laravel — pastikan `SESSION_DRIVER` aktif (default `database`/`file` sudah cukup).
