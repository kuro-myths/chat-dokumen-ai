@extends('layouts.app')

@section('title', 'Beranda — Kuro AI')

@section('content')
<div class="shell" id="shell" data-mode="landing">
    {{-- ===== Sidebar (bisa dibuka-tutup) ===== --}}
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <button class="side-toggle" id="btn-side-toggle" title="Buka / tutup sidebar"><i class="fa-solid fa-bars"></i></button>
            <div class="logo"><img src="{{ asset('img/kuro-logo.svg') }}" alt="Logo Kuro AI"></div>
            <div class="brand-txt">Kuro AI<small>Teman bincang AI</small></div>
        </div>

        <button class="new-chat" id="btn-new-chat" title="Percakapan baru">
            <i class="fa-solid fa-plus"></i><span class="lbl">Percakapan baru</span>
        </button>

        <nav class="side-nav">
            <button class="side-item" id="btn-history" title="Riwayat tersimpan">
                <span class="ic"><i class="fa-solid fa-clock-rotate-left"></i></span><span class="lbl">Riwayat tersimpan</span>
            </button>
            <button class="side-item" id="btn-docs" title="Dokumen">
                <span class="ic"><i class="fa-solid fa-file-lines"></i></span><span class="lbl">Dokumen</span><span class="cnt" id="cnt-docs">0</span>
            </button>

            {{-- Dropdown API Key (expand di bawah item) --}}
            <button class="side-item" id="btn-apikey" title="API Key &amp; Provider">
                <span class="ic"><i class="fa-solid fa-key"></i></span><span class="lbl">API Key</span>
                <span class="caret"><i class="fa-solid fa-chevron-down"></i></span>
            </button>
            <div class="api-drop" id="api-drop">
                <div class="api-drop-head">Provider &amp; API Key</div>
                <div class="api-drop-list" id="api-drop-list"><p class="muted">Memuat…</p></div>
                <button class="btn small api-add" id="btn-add-provider"><i class="fa-solid fa-circle-plus"></i> Tambah API Key</button>
            </div>
        </nav>

        <div class="side-foot">
            <form method="POST" action="/logout">
                @csrf
                <button type="submit" class="side-item" title="Keluar">
                    <span class="ic"><i class="fa-solid fa-right-from-bracket"></i></span><span class="lbl">Keluar</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- ===== Konten utama ===== --}}
    <main class="main">
        <button class="side-toggle mobile-only" id="btn-side-toggle-m" title="Menu"><i class="fa-solid fa-bars"></i></button>

        {{-- Landing minimal: sapaan + composer + kartu deteksi --}}
        <div class="landing" id="landing">
            <h1 class="greet">Sebaiknya kita mulai dari mana?</h1>

            {{-- Composer ala Gemini --}}
            <div class="composer" id="composer">
                <textarea id="chat-input" rows="1" placeholder="Tanya apa saja ke Kuro — atau tempel dokumen .docx lewat ikon klip"></textarea>
                <div class="composer-row">
                    <button class="cp-btn" id="btn-attach" title="Unggah dokumen .docx"><i class="fa-solid fa-paperclip"></i></button>
                    <div class="cp-prov">
                        <button class="cp-prov-btn" id="cp-prov-btn" title="Pilih provider AI">
                            <i class="fa-solid fa-robot"></i>
                            <span id="cp-prov-name">Gemini (default)</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div class="cp-prov-menu" id="cp-prov-menu"></div>
                    </div>
                    <span class="cp-status" id="chat-status"></span>
                    <button class="cp-btn" id="btn-save-chat" title="Simpan percakapan"><i class="fa-solid fa-floppy-disk"></i></button>
                    <button class="cp-btn" id="btn-mic" title="Dikte suara (bahasa Indonesia)"><i class="fa-solid fa-microphone"></i></button>
                    <button class="cp-send" id="chat-send" title="Kirim"><i class="fa-solid fa-arrow-up"></i></button>
                </div>
                <input type="file" id="attach-file" accept=".docx" style="display:none">
            </div>

            {{-- Kartu deteksi: dokumen, temuan AI, komentar, provider --}}
            <div class="stat-cards" id="stat-cards">
                <button class="st-card" id="st-docs" title="Buka daftar dokumen">
                    <span class="st-ic"><i class="fa-solid fa-file-lines"></i></span>
                    <b id="stat-documents">{{ $stats['documents'] ?? '—' }}</b>
                    <span class="st-lbl">Dokumen</span>
                </button>
                <button class="st-card" id="st-highlights" title="Lihat temuan AI">
                    <span class="st-ic"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <b id="stat-highlights">{{ $stats['highlights'] ?? '—' }}</b>
                    <span class="st-lbl">Temuan AI</span>
                </button>
                <button class="st-card" id="st-comments" title="Komentar dokumen">
                    <span class="st-ic"><i class="fa-solid fa-comments"></i></span>
                    <b id="stat-comments">{{ $stats['comments'] ?? '—' }}</b>
                    <span class="st-lbl">Komentar</span>
                </button>
                <button class="st-card" id="st-prov" title="Kelola API Key &amp; provider">
                    <span class="st-ic"><i class="fa-solid fa-robot"></i></span>
                    <b id="stat-providers">…</b>
                    <span class="st-lbl">Provider AI</span>
                </button>
            </div>
        </div>

        {{-- Mode percakapan / dokumen --}}
        <div class="work" id="work">
            <div class="chat-col" id="chat-col">
                <div class="chat-log" id="chat-log"></div>
            </div>

            {{-- Panel dokumen (split-screen: klik dokumen → tampil di sini) --}}
            <div class="doc-pane" id="doc-pane">
                <div class="doc-head">
                    <div class="doc-title-wrap">
                        <b id="dp-title">Dokumen</b>
                        <span class="badge" id="dp-status">uploaded</span>
                    </div>
                    <div class="doc-actions">
                        <button class="btn small" id="dp-review"><i class="fa-solid fa-wand-magic-sparkles"></i> Periksa AI</button>
                        <a class="btn small ghost" id="dp-export" href="#"><i class="fa-solid fa-download"></i> Unduh</a>
                        <button class="btn small ghost icon" id="dp-close" title="Tutup dokumen"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>
                <div class="doc-view" id="dp-view"></div>
                <div class="doc-find" id="dp-findings"></div>
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

{{-- ===== Popup: Tambah API Key / Provider ===== --}}
<div class="modal" id="prov-modal">
    <div class="modal-panel">
        <div class="modal-head">
            <h3><i class="fa-solid fa-key"></i>Tambah API Key / Provider</h3>
            <button class="btn ghost small icon" id="btn-prov-close" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <p class="muted">
                Contoh 9Router online: driver <b>openai-compatible</b>,
                base URL <code>https://instance-9router-kamu.up.railway.app/v1</code>,
                API key dari dashboard 9Router, model bebas (mis. <code>gpt-4o-mini</code>).
                Untuk Gemini cukup isi slug, nama, driver <code>gemini</code>, dan API key.
            </p>
            <form id="provider-form">
                <div class="prow">
                    <div><label>Slug</label><input name="slug" placeholder="gemini-2 / 9router" pattern="[A-Za-z0-9_-]+" required></div>
                    <div><label>Nama</label><input name="name" placeholder="9Router Online" required></div>
                </div>
                <div class="prow">
                    <div>
                        <label>Driver</label>
                        <select name="driver">
                            <option value="openai-compatible">openai-compatible (9Router, OpenRouter, dll.)</option>
                            <option value="gemini">gemini</option>
                        </select>
                    </div>
                    <div><label>Model</label><input name="model" placeholder="gpt-4o-mini"></div>
                </div>
                <label>Base URL (khusus openai-compatible)</label>
                <input name="base_url" placeholder="https://instance-9router-kamu.up.railway.app/v1">
                <label>API Key</label>
                <input name="api_key" placeholder="sk-... / AIza...">
                <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_default" style="width:auto"> Jadikan provider default</label>
                <button type="submit" class="btn" style="width:100%;justify-content:center;margin-top:10px"><i class="fa-solid fa-floppy-disk"></i> Simpan Provider</button>
                <p id="provider-msg" class="hint"></p>
            </form>
        </div>
    </div>
</div>

{{-- ===== Popup: daftar dokumen ===== --}}
<div class="modal" id="doc-modal">
    <div class="modal-panel" style="max-width:640px">
        <div class="modal-head">
            <h3><i class="fa-solid fa-file-lines"></i>Dokumen</h3>
            <div style="display:flex;gap:8px">
                <button class="btn small" id="btn-doc-upload"><i class="fa-solid fa-arrow-up-from-bracket"></i> Unggah .docx</button>
                <button class="btn ghost small icon" id="btn-doc-close" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <div class="modal-body" id="doc-list"><p class="empty">Memuat…</p></div>
    </div>
</div>

{{-- ===== Modal riwayat percakapan ===== --}}
<div class="modal" id="hist-modal">
    <div class="modal-panel">
        <div class="modal-head">
            <h3><i class="fa-solid fa-clock-rotate-left"></i>Riwayat Percakapan</h3>
            <div style="display:flex;gap:8px">
                <button class="btn ghost small" id="btn-hist-clear"><i class="fa-solid fa-trash-can"></i> Hapus Semua</button>
                <button class="btn ghost small icon" id="btn-hist-close" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <div class="modal-body" id="hist-list"><p class="empty">Belum ada percakapan tersimpan.</p></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/kuro.js') }}"></script>
@endpush
