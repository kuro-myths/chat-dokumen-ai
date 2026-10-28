# SkripsiAI — AI Reviewer Skripsi & Proposal (Laravel 13)

Aplikasi Laravel yang membantu mahasiswa menyusun skripsi/proposal: AI menganalisis dokumen DOCX, memberi **highlight warna** pada teks bermasalah, memberi **komentar + saran revisi**, dan menerima input suara otomatis (**auto voice input**). Provider AI bisa **apa pun** — OpenAI, Anthropic, Gemini, Groq, Mistral, Ollama lokal, atau API kustom.

> Dibangun untuk Laravel 13 (PHP 8.3+). Kompatibel dengan struktur Laravel 11/12 (routing dan service container sama).

## Fitur

| Fitur | Cara kerja |
| --- | --- |
| Integrasi API AI apa pun | Driver pattern (`AiManager`) + driver `openai-compatible` untuk endpoint kompatibel OpenAI (DeepSeek, OpenRouter, vLLM, dll.) + `Ai::extend()` untuk API berformat kustom + tabel `ai_settings` agar user menambah provider dari API tanpa deploy ulang |
| DOCX | Upload `.docx` → diparse per paragraf (deteksi BAB/sub-bab) → ekspor kembali `.docx` dengan warna highlight + lampiran Laporan Review AI (phpoffice/phpword) |
| Review skripsi/proposal | Paragraf di-batch → AI menganalisis bahasa, sitasi, metodologi, struktur BAB I–V → tiap temuan jadi `Highlight` + `Comment` |
| Tanda warna | merah = bahasa/typo/plagiarisme, kuning = tanpa sitasi, biru = metodologi, ungu = struktur, hijau = gaya akademik |
| Perbaikan revisi | `POST /highlights/{id}/accept` mengganti teks asli dengan `replacement`, snapshot versi otomatis, plus `accept-all` |
| Komentar | Komentar AI + manusia per paragraf, balasan (reply), tandai selesai (resolve) |
| Auto voice input | Rekam → berhenti otomatis saat diam → transkripsi Whisper-compatible → dirapikan ke bahasa akademik oleh LLM → teks otomatis masuk dokumen |

## Instalasi

### 0. Prasyarat

- PHP **8.2+** dengan ekstensi: `cli, xml, zip, gd, mbstring, curl, pdo_sqlite/pdo_mysql`
- **Composer**
- (Opsional) [Ollama](https://ollama.com) untuk AI lokal gratis

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

# Opsional: Redis queue
# composer require predis/predis
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

> Catatan: repo ini berisi file tambahan di atas kerangka Laravel standar. Jika kamu mulai dari `laravel new` (Cara A di atas), salin folder `app/`, `config/`, `database/migrations/`, `routes/`, dan `resources/js/` ke path yang sama, lalu jalankan `composer dump-autoload`.

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
cat env.example.additions >> .env   # tambah GEMINI_API_KEY, OPENAI_API_KEY, dll.
php artisan migrate
php artisan storage:link           # supaya file upload/DOCX bisa diakses
```

### 4. Jalankan

```bash
php artisan serve       # server API
php artisan queue:work  # WAJIB di terminal terpisah: review AI diproses via queue
```

Minimal isi satu API key di `.env` (mis. `GEMINI_API_KEY` atau `OPENAI_API_KEY`), atau gunakan Ollama lokal gratis (`OLLAMA_*`).

### 5. Troubleshooting instalasi

| Error | Solusi |
| --- | --- |
| `class "App\Providers\AiServiceProvider" not found` | `composer dump-autoload`, pastikan sudah didaftarkan di `bootstrap/providers.php` |
| `ext-zip missing` saat install phpword | `sudo apt install php8.3-zip` lalu ulangi `composer require` |
| `ext-gd missing` | `sudo apt install php8.3-gd` |
| `ext-dom missing` | `sudo apt install php8.3-xml` |
| Review tidak pernah selesai | queue tidak jalan — jalankan `php artisan queue:work` |
| `could not find driver` (database) | aktifkan `pdo_sqlite`/`pdo_mysql` sesuai koneksi di `.env` |

## Struktur file tambahan

```text
config/ai.php
app/Providers/AiServiceProvider.php
app/Services/Ai/
├── AiManager.php                  # pusat integrasi semua provider
├── AiResponse.php
├── Contracts/AiProviderInterface.php
├── Drivers/
│   ├── OpenAiCompatibleDriver.php  # OpenAI, Groq, Mistral, DeepSeek, dll.
│   ├── AnthropicDriver.php
│   ├── GeminiDriver.php
│   └── OllamaDriver.php
├── Facades/Ai.php
└── Prompts/ThesisReviewPrompts.php
app/Services/Docx/
├── DocxParserService.php          # .docx -> paragraf
└── DocxExportService.php          # paragraf + warna -> .docx
app/Services/Review/
├── ThesisReviewService.php        # orkestrasi review AI
└── RevisionService.php            # terima/tolak revisi
app/Services/Voice/VoiceTranscriptionService.php
app/Http/Controllers/Api/          # Document, Review, Highlight, Comment, Voice, Provider
app/Http/Requests/                # validasi upload
app/Jobs/RunThesisReview.php
app/Models/                       # Document, DocumentSection, DocumentVersion, Comment, Highlight, Transcription, AiSetting
database/migrations/2026_10_28_000001_create_skripsi_ai_tables.php
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

## Menambahkan API AI baru

Cara 1 — endpoint kompatibel OpenAI (DeepSeek, OpenRouter, vLLM, LiteLLM, dst.), tanpa kode:

```bash
curl -X POST http://localhost:8000/api/ai/providers \
  -H "Content-Type: application/json" \
  -d '{"slug":"deepseek","name":"DeepSeek","driver":"openai-compatible",
       "base_url":"https://api.deepseek.com/v1","model":"deepseek-chat",
       "api_key":"sk-...","is_default":true}'
```

Cara 2 — API dengan format khusus, satu closure di `AppServiceProvider::boot()`:

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

## Catatan

- API key di tabel `ai_settings` disimpan dengan **encrypted cast** Laravel.
- Untuk dokumen panjang, gunakan route review async + `queue:work` (timeout job 15 menit).
- Voice memakai endpoint kompatibel Whisper (`/audio/transcriptions`); bisa diarahkan ke Groq (whisper-large-v3) atau server lokal whisper.cpp via `VOICE_BASE_URL`.
