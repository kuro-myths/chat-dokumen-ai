@extends('layouts.app')

@section('title', 'Beranda — Kuro AI')

@section('content')
<div class="shell">
    {{-- ===== Sidebar ala Gemini ===== --}}
    <aside class="sidebar">
        <div class="brand">
            <div class="logo"><img src="{{ asset('img/kuro-logo.svg') }}" alt="Logo Kuro AI"></div>
            <div class="brand-txt">Kuro AI<small>Asisten skripsi &amp; proposal</small></div>
        </div>

        <button class="new-chat" id="btn-new-chat">
            <i class="fa-solid fa-plus"></i>
            Percakapan baru
        </button>

        <nav style="display:flex;flex-direction:column;gap:2px">
            <button class="side-item" data-goto="chat"><span class="ic"><i class="fa-solid fa-comments"></i></span> Percakapan &amp; Dikte</button>
            <button class="side-item" id="btn-history"><span class="ic"><i class="fa-solid fa-clock-rotate-left"></i></span> Riwayat tersimpan</button>
            <button class="side-item" data-goto="dokumen"><span class="ic"><i class="fa-solid fa-file-lines"></i></span> Periksa dokumen</button>
            <button class="side-item" data-goto="integrasi"><span class="ic"><i class="fa-solid fa-brain"></i></span> Integrasi &amp; Model</button>
            <button class="side-item" data-goto="tambah"><span class="ic"><i class="fa-solid fa-circle-plus"></i></span> Tambah provider</button>
        </nav>

        <div class="side-stats">
            <div class="sstat"><span>Dokumen</span><b>{{ $stats['documents'] ?? '—' }}</b></div>
            <div class="sstat"><span>Temuan AI (pending)</span><b>{{ $stats['highlights'] ?? '—' }}</b></div>
            <div class="sstat"><span>Komentar</span><b>{{ $stats['comments'] ?? '—' }}</b></div>
            <div class="sstat"><span>Provider AI</span><b id="stat-providers">…</b></div>
        </div>

        <div class="side-foot">
            <form method="POST" action="/logout">
                @csrf
                <button type="submit" class="side-item"><span class="ic"><i class="fa-solid fa-right-from-bracket"></i></span> Keluar</button>
            </form>
        </div>
    </aside>

    {{-- ===== Konten utama ===== --}}
    <main class="main">
        <div class="main-inner">
            <div class="topbar">
                <h1 class="greet">Sebaiknya kita mulai dari mana?</h1>
            </div>

            {{-- Kartu pintasan ala Gemini --}}
            <div class="quick">
                <button class="q-card" data-goto="dokumen">
                    <span class="q-ic"><i class="fa-solid fa-file-lines"></i></span>
                    <b>Periksa dokumen</b>
                    <p>Unggah .docx → AI memberi highlight &amp; saran revisi.</p>
                </button>
                <button class="q-card" data-act="mic">
                    <span class="q-ic"><i class="fa-solid fa-microphone"></i></span>
                    <b>Dikte suara</b>
                    <p>Bicara dalam bahasa Indonesia, Kuro menuliskannya.</p>
                </button>
                <button class="q-card" data-goto="integrasi">
                    <span class="q-ic"><i class="fa-solid fa-brain"></i></span>
                    <b>Cek model</b>
                    <p>Lihat model AI tiap provider sebelum dipakai.</p>
                </button>
                <button class="q-card" data-act="chat">
                    <span class="q-ic"><i class="fa-solid fa-comment-dots"></i></span>
                    <b>Tanya Kuro</b>
                    <p>Ngobrol soal skripsi, sitasi, atau metodologi.</p>
                </button>
            </div>

            {{-- ===== Percakapan + dikte suara ===== --}}
            <div class="card" id="chat" style="margin-top:26px">
                <h2><i class="fa-solid fa-comments"></i>Percakapan &amp; Dikte Suara</h2>
                <p class="muted">Ngobrol dengan Kuro — tekan ikon mic untuk mendikte dengan suara (bahasa Indonesia).</p>
                <label for="chat-provider">Provider</label>
                <select id="chat-provider"><option value="">(memuat…)</option></select>
                <div class="chat-log" id="chat-log"></div>
                <textarea id="chat-input" rows="2" placeholder="Tulis pesan atau tekan mic, lalu Ctrl+Enter untuk kirim…"></textarea>
                <div class="chat-tools">
                    <button class="btn" id="chat-send"><i class="fa-solid fa-paper-plane"></i> Kirim</button>
                    <button class="btn ghost icon" id="btn-mic" title="Dikte suara"><i class="fa-solid fa-microphone"></i></button>
                    <button class="btn ghost icon" id="btn-save-chat" title="Simpan percakapan ini"><i class="fa-solid fa-floppy-disk"></i></button>
                    <button class="btn ghost small" id="btn-clear-chat"><i class="fa-solid fa-trash"></i> Kosongkan</button>
                    <span class="muted" id="chat-status" style="font-size:.78rem"></span>
                </div>
            </div>

            {{-- ===== Periksa dokumen ===== --}}
            <div class="card" id="dokumen">
                <h2><i class="fa-solid fa-file-lines"></i>Periksa Dokumen</h2>
                <p class="muted">Unggah .docx skripsi/proposal → AI menganalisis bahasa, sitasi, metodologi &amp; struktur → unduh hasil dengan highlight warna.</p>
                <div class="dropzone" id="dropzone">
                    <div class="dz-ic"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                    <p><b>Klik atau tarik file .docx ke sini</b></p>
                    <p>Analisis dilakukan oleh provider AI terpilih</p>
                    <input type="file" id="doc-file" accept=".docx" style="display:none">
                </div>
                <div class="row" style="margin-top:14px">
                    <div style="flex:1;min-width:170px">
                        <label>Jenis</label>
                        <select id="doc-type"><option value="skripsi">Skripsi</option><option value="proposal">Proposal</option></select>
                    </div>
                    <div style="flex:2;min-width:220px">
                        <label>Judul (opsional)</label>
                        <input id="doc-title" placeholder="Otomatis dari nama file">
                    </div>
                    <div style="flex:1;min-width:170px;display:flex;align-items:flex-end">
                        <button class="btn" id="doc-upload" style="width:100%;justify-content:center">
                            <i class="fa-solid fa-arrow-up-from-bracket"></i> Unggah
                        </button>
                    </div>
                </div>
                <p class="hint">Setelah unggah, tekan <b>Periksa AI</b> pada dokumen. Untuk dokumen panjang, jalankan <code>php artisan queue:work</code> di terminal kedua.</p>
                <div id="doc-list"></div>
                <div id="doc-findings"></div>
            </div>

            {{-- ===== Integrasi AI & cek model ===== --}}
            <div class="card" id="integrasi">
                <h2><i class="fa-solid fa-brain"></i>Integrasi AI &amp; Cek Model</h2>
                <p class="muted">
                    <b>Gemini</b> (driver <code>gemini</code>) dan <b>multi-API</b> seperti 9Router online
                    (driver <code>openai-compatible</code>). Klik <b>Lihat Model</b> untuk menampilkan daftar model.
                </p>
                <div class="grid" id="providers"><p class="muted">Memuat provider…</p></div>
            </div>

            {{-- ===== Tambah provider ===== --}}
            <div class="card" id="tambah" style="margin-bottom:40px">
                <h2><i class="fa-solid fa-circle-plus"></i>Tambah Provider</h2>
                <p class="muted">
                    Contoh 9Router online: driver <b>openai-compatible</b>,
                    base URL <code>https://instance-9router-kamu.up.railway.app/v1</code>, API key dari dashboard 9Router,
                    model bebas (mis. <code>gpt-4o-mini</code>).
                </p>
                <form id="provider-form" class="row" style="margin-top:8px">
                    <div style="flex:1;min-width:150px">
                        <label>Slug</label>
                        <input name="slug" placeholder="9router" pattern="[A-Za-z0-9_-]+" required>
                    </div>
                    <div style="flex:1;min-width:150px">
                        <label>Nama</label>
                        <input name="name" placeholder="9Router Online" required>
                    </div>
                    <div style="flex:1;min-width:150px">
                        <label>Driver</label>
                        <select name="driver">
                            <option value="openai-compatible">openai-compatible (9Router, OpenRouter, dll.)</option>
                            <option value="gemini">gemini</option>
                        </select>
                    </div>
                    <div style="flex:2;min-width:220px">
                        <label>Base URL</label>
                        <input name="base_url" placeholder="https://instance-9router-kamu.up.railway.app/v1">
                    </div>
                    <div style="flex:1;min-width:150px">
                        <label>Model</label>
                        <input name="model" placeholder="gpt-4o-mini">
                    </div>
                    <div style="flex:2;min-width:220px">
                        <label>API Key</label>
                        <input name="api_key" placeholder="sk-...">
                    </div>
                    <div style="flex:1;min-width:150px;display:flex;align-items:flex-end">
                        <label style="margin:0 6px 0 0"><input type="checkbox" name="is_default" style="width:auto"> Jadikan default</label>
                    </div>
                    <div style="flex:1;min-width:150px;display:flex;align-items:flex-end">
                        <button type="submit" class="btn" style="width:100%;justify-content:center"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                    </div>
                </form>
                <p id="provider-msg" class="hint"></p>
            </div>
        </div>
    </main>
</div>

{{-- ===== Maskot Kuro (SVG eksternal + animasi SMIL) ===== --}}
<div class="kuro-wrap" id="kuro">
    <div class="kuro-bubble" id="kuro-bubble">Kuro siap membantu ✨</div>
    <img class="kuro-svg" src="{{ asset('img/kuro.svg') }}" alt="Maskot Kuro" width="110">
    <div class="kuro-scan"></div>
</div>

{{-- ===== Modal riwayat percakapan ===== --}}
<div class="modal" id="hist-modal">
    <div class="modal-panel">
        <div class="modal-head">
            <h3><i class="fa-solid fa-clock-rotate-left"></i>Riwayat Percakapan</h3>
            <div style="display:flex;gap:8px">
                <button class="btn ghost small" id="btn-hist-clear"><i class="fa-solid fa-trash-can"></i> Hapus Semua</button>
                <button class="btn ghost small" id="btn-hist-close"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <div class="modal-body" id="hist-list"><p class="empty">Belum ada percakapan tersimpan.</p></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/kuro.js') }}"></script>
@endpush
