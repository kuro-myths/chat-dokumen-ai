# 🐱 Kuro AI — AI Reviewer Skripsi & Proposal

**Kuro AI** adalah aplikasi web yang membantu mahasiswa menyusun **skripsi/proposal** dengan bantuan AI: unggah file `.docx`, AI menganalisis isinya (bahasa, sitasi, metodologi, struktur BAB), memberi **highlight warna + komentar + saran revisi**, lalu revisi bisa **diterapkan otomatis 1 klik** dan diunduh kembali sebagai .docx rapi. Tersedia pula **chat AI yang benar-benar membaca isi dokumen** — bisa tanya per BAB atau dokumen utuh.

Dibangun dengan **Laravel 12**, frontend **vanilla JavaScript** (satu file `kuro.js`), database **MySQL**, dan di-hosting di **Railway**.

---

## 🎯 Tujuan Pembuatan Kuro AI

| # | Tujuan | Penjelasan |
| --- | --- | --- |
| 1 | **Mempercepat perbaikan skripsi** | Umpan balik dosen biasanya datang setelah antrean lama. Kuro AI memberi umpan balik *instan* kapan pun dibutuhkan, sehingga mahasiswa datang ke bimbingan dengan draft yang sudah rapi. |
| 2 | **Review terstruktur & konsisten** | AI memeriksa dengan kategori tetap: bahasa/typo (merah), sitasi (kuning), metodologi (biru), struktur BAB (ungu), gaya akademik (hijau) — tidak tergantung mood atau kelelahan reviewer. |
| 3 | **Membantu belajar, bukan menggantikan dosen** | Setiap temuan disertai penjelasan *kenapa* bermasalah dan saran revisi, jadi mahasiswa paham pola kesalahannya. Keputusan akhir tetap di tangan mahasiswa & dosen. |
| 4 | **Biaya rendah & transparan** | Multi-provider AI (Gemini gratis + router multi-model dengan label gratis/berbayar), dan tiap temuan mencatat AI mana yang menemukannya. |

## 🌱 Tujuan Pengembangan Kuro AI

Aplikasi ini dikembangkan sebagai **proyek tugas akhir** dengan target pertumbuhan:

1. **Stabil & siap ujian** — alur unggah → review → revisi → unduh dijaga selalu berjalan penuh.
2. **Review per BAB yang cerdas** — chunking per heading level-1 dengan scope tersimpan, fondasi untuk dokumen lebih panjang (tesis/buku).
3. **Ekosistem AI terbuka** — arsitektur driver berbasis interface; provider baru cukup tambah driver atau endpoint kompatibel OpenAI tanpa mengubah logika inti.
4. **Roadmap selanjutnya** — filter temuan per kategori/BAB, deteksi plagiarisme lintas dokumen, ekspor laporan PDF, mode kolaborasi dosen–mahasiswa.

---

## 🧩 Penjelasan Komponen & Alasannya

### 1. Lapisan Controller (`app/Http/Controllers/`)

| Komponen | Tugas | Kenapa dibuat begini |
| --- | --- | --- |
| `Web/AuthController` | Login berbasis password + sesi | Sederhana & cukup untuk aplikasi single-user, tanpa beban sistem registrasi penuh. |
| `Web/HomeController` | Render beranda + endpoint `/chat`: menyusun pesan sistem, **menempelkan isi dokumen sebagai konteks** (maks 30.000 karakter, bisa per BAB), mode ⚡ singkat, fallback `chatAuto` | Konteks dokumen dirangkai di server supaya prompt konsisten dan tidak bisa dimanipulasi dari browser. |
| `Web/ConvController` | Riwayat percakapan (database) | Riwayat harus bertahan antar sesi/perangkat → MySQL, bukan localStorage. |
| `Api/DocumentController` | Upload/lihat/hapus/ekspor .docx, impor ulang | Operasi file adalah resource → REST API terpisah dari halaman. |
| `Api/ReviewController` | Mulai review (async via queue / sync) & progress | Review panjang bisa >60 detik → dua mode: queue (UI tidak nunggu) dan sync (hasil langsung). |
| `Api/HighlightController` | Daftar temuan, accept/reject/accept-all/**destroy** | Temuan adalah entitas yang bisa diterima/ditolak/dihapus — pas dengan resource REST. |
| `Api/CommentController` | Komentar + balasan + resolve | Meniru pola Google Docs yang familier bagi mahasiswa. |
| `Api/Provider/Model/AiSetup/RouterSync` | Manajemen API key, daftar model, sinkron router | Konfigurasi AI bisa diubah dari UI tanpa redeploy. |
| `Api/GenerateController`, `Api/VoiceController` | Generate gambar/video, TTS | Fitur pelengkap interaksi (nilai tambah, bukan inti). |

### 2. Lapisan Service (`app/Services/`) — kenapa terpisah dari Controller?

Logika berat dipisah ke Service Layer supaya **Controller tetap tipis**, logika **bisa dipakai ulang** (API, queue, console), dan **mudah diuji**.

| Komponen | Tugas | Alasan desain |
| --- | --- | --- |
| `Ai/AiManager` + `Facades/Ai` | Pusat integrasi AI, `chatAuto` fallback otomatis antar provider/model | Kalau satu model mati/kuota habis, sistem pindah sendiri — reliabel tanpa campur tangan user. |
| `Ai/Drivers/GeminiDriver` | Google Gemini | API sendiri + tier gratis untuk mahasiswa. |
| `Ai/Drivers/OpenAiCompatibleDriver` | Endpoint kompatibel OpenAI (Max Router, OpenRouter, dll.) | Satu driver mencakup puluhan provider — hemat kode, banyak pilihan model. |
| `Ai/Contracts/AiProviderInterface` | Kontrak antarmuka driver | Pola *strategy* OOP: driver baru tinggal implement 1 interface. |
| `Ai/AiModelService` + `ModelPricing` | Daftar model + label gratis/berbayar | Transparansi biaya untuk pengguna kampus. |
| `Ai/Prompts/ThesisReviewPrompt` | System prompt review skripsi (output JSON terstruktur) | AI dipaksa menjawab terstruktur → hasil bisa diparse & disimpan ke database, bukan teks bebas. |
| `Docx/DocxParserService` | .docx → paragraf + deteksi heading BAB | Parsing sendiri memberi kendali penuh atas pembagian BAB untuk review per-bagian. |
| `Docx/DocxExportService` | Tulis ulang .docx hasil revisi (+lampiran laporan) | User akhirnya tetap butuh file Word — alur kerja tidak berubah. |
| `Review/ThesisReviewService` | `reviewChunk($document, $provider, $model, $from, $to)`: kirim isi (seluruh/per BAB) → parse JSON → simpan highlight + komentar, scope tersimpan agar tidak dobel | Dokumen panjang melampaui *context window* model; chunking per BAB menjaga kualitas & biaya token. |
| `Review/RevisionService` | Snapshot versi lama → terapkan revisi → bisa rollback | Keamanan data: dokumen asli tidak pernah hilang. |
| `Jobs/RunThesisReview` | Job queue review | Proses panjang jalan di background, UI tetap responsif (animasi 🐱 Kuro reading). |

### 3. Database (`database/migrations/`)

| Tabel | Peran | Alasan |
| --- | --- | --- |
| `documents` | Metadata dokumen + status review | Pusat relasi semua entitas. |
| `document_sections` | Paragraf per `index` + `heading` | Fondasi review per BAB & highlight presisi per paragraf. |
| `document_versions` | Snapshot tiap sebelum revisi | Jaminan bisa kembali ke versi lama (audit trail). |
| `highlights` | Temuan AI: teks, warna, kategori, revisi, rentang, `source_provider`/`source_model` | Jejak AI mana yang menemukan → transparansi & bisa dibandingkan antar model. |
| `comments` | Komentar + balasan + resolve | Feedback dua arah seperti Google Docs. |
| `conversations` + `messages` | Riwayat chat | Persisten lintas perangkat. |
| `ai_settings` | Provider + API key (encrypted cast) | Key terenkripsi di server, tidak pernah dikirim ke browser. |

### 4. Frontend (`public/js/kuro.js`, `public/css/kuro.css`, `resources/views/beranda.blade.php`)

| Komponen | Tugas | Alasan |
| --- | --- | --- |
| `kuro.js` (±2.600 baris, IIFE vanilla) | Sidebar, chat, split-screen dokumen, tab panel (Dokumen/Temuan AI/Komentar/**Tanya Dokumen**), composer ala Gemini, mode ⚡ Singkat & Jelas, chip 🧩 konteks dokumen | Tanpa framework & tanpa build step: ringan, satu file, cukup untuk kompleksitas ini. |
| `kuro.css` | Tema Kuro (sidebar ungu, panel kaca, tampilan Word: Times New Roman + A4) | Dokumen terlihat seperti hasil cetak → review lebih intuitif. |
| `beranda.blade.php` | Satu halaman SPA + cache-bust `?v=` | Satu halaman = state sederhana; cache-bust menjamin update JS selalu sampai ke user. |

### 5. Route & Keamanan

- `routes/web.php` — halaman + chat AJAX (dilindungi sesi login).
- `routes/api.php` — REST API (dokumen, review, highlights, komentar, AI setup).
- API key **tidak pernah** dikirim ke browser; validasi upload lewat `app/Http/Requests/`.

---

## 🚀 Kenapa Deploy di Railway?

| Alasan | Penjelasan |
| --- | --- |
| **Deploy otomatis dari GitHub** | Setiap `git push` ke `main` langsung di-build & dirilis — alur kerja push → cek live, tanpa setup server manual. |
| **MySQL terkelola di satu project** | Service database berjalan bareng aplikasi, koneksi via variabel lingkungan `DB_*` — tanpa maintenance server DB sendiri. |
| **Procfile anti-crash** | Server (`php -S 0.0.0.0:$PORT`) **langsung jalan dulu** sehingga health check lolos; `migrate --force` + `storage:link` dijalankan di *background*. (Pelajaran dari insiden deploy crash: migrasi yang menunggu MySQL membuat health check gagal.) |
| **HTTPS & domain gratis** | SSL otomatis + subdomain `.up.railway.app`, sudah `trustProxies` sehingga URL https benar. |
| **Cocok skala tugas akhir** | Paket hemat, log real-time, variabel lingkungan untuk API key — semua kebutuhan proyek TA tanpa biaya VPS. |

---

## 📥 Instalasi Lokal

```bash
git clone https://github.com/kuro-myths/chat-dokumen-ai.git kuro-ai
cd kuro-ai
composer install
cp -n .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve   # http://127.0.0.1:8000
```

Password login default: `123456789` (ubah lewat `APP_LOGIN_PASSWORD`). API key AI bisa diisi lewat `.env` (`GEMINI_API_KEY`) atau form **API Key** di beranda.

### Deploy ke Railway

1. Railway → **New Project** → **Deploy from GitHub repo** → repo ini, branch `main`.
2. Tambahkan service **MySQL** di project yang sama; Railway otomatis menyuntikkan `DB_*`.
3. `Procfile` sudah menangani start server + migrasi background.
4. (Opsional) Tab **Variables**: `GEMINI_API_KEY`, `APP_LOGIN_PASSWORD`.

---

## 📡 Endpoint Utama

| Method | Path | Fungsi |
| --- | --- | --- |
| POST | `/api/documents` | Unggah .docx |
| POST | `/api/documents/{id}/review` | Review AI per scope (seluruh / per BAB) — async |
| GET | `/api/documents/{id}/highlights` | Semua temuan |
| POST | `/api/highlights/{id}/accept` · `/reject` | Terima / tolak revisi |
| DELETE | `/api/highlights/{id}` | Hapus temuan |
| GET | `/api/documents/{id}/export` | Unduh .docx hasil revisi |
| GET/POST | `/api/documents/{id}/comments` | Komentar + balasan + resolve |
| POST | `/chat` | Chat AI (`message`, `provider`, `model`, `document_id`, `concise`) |
| GET | `/api/ai/models` | Daftar model per provider (label gratis/berbayar) |
| POST | `/api/ai/router/sync` | Sinkron model dari router |

---

## ✨ Fitur Unggulan

| Fitur | Cara kerja |
| --- | --- |
| 🔍 **Review per BAB** | Dropdown scope: seluruh dokumen atau per BAB — temuan tidak dobel, context window terkendali |
| 🤖 **Jejak AI per temuan** | Tiap temuan mencatat model + provider yang menemukannya |
| 🗑️ **Kartu temuan interaktif** | Chip lokasi 📍 (BAB/paragraf), tombol terima/tolak/hapus |
| 💬 **Tanya Dokumen** | Chat khusus yang membaca isi dokumen utuh (atau BAB terpilih) sebagai konteks |
| ⚡ **Mode Singkat & Jelas** | Jawaban AI dibatasi 3–5 kalimat/poin, langsung ke inti |
| 🔗 **Chip Terhubung** | Chat utama otomatis membawa konteks dokumen yang terbuka |
| 🐱 **Animasi Kuro** | Maskot kucing "Kuro reading" saat AI sedang menganalisis |
| 🔊 **Dikte + TTS** | Input suara (Web Speech API) dan jawaban AI yang bisa dibacakan |

## 📝 Catatan Teknis

- API key di `ai_settings` disimpan **terenkripsi** (Laravel encrypted cast).
- Frontend tanpa kode inline; class custom berprefiks (`kuro-`, `dp-`, `hl-`, `cp-`) agar tidak bentrok dengan CDN.
- `bootstrap/app.php` sudah `trustProxies(at: '*')` untuk reverse proxy Railway.
- Deployment via `Procfile`: server dulu, migrasi background — aman terhadap cold-start MySQL.
