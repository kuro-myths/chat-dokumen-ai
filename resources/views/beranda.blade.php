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

        {{-- Daftar percakapan — otomatis tersimpan & tampil langsung di sidebar --}}
        <div class="side-conv-box" id="side-conv-box">
            <div class="side-conv-head"><span class="lbl">Percakapan</span></div>
            <div class="side-convs" id="side-convs"><p class="side-conv-empty">Belum ada percakapan — mulai chat, otomatis muncul di sini.</p></div>
        </div>

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
                <textarea id="chat-input" rows="1" placeholder="Tanya apa saja ke Kuro — atau tekan ikon + untuk file, folder, gambar, video, dan mode berbicara"></textarea>
                <div class="composer-row">
                    <div class="cp-attach">
                        <button class="cp-btn" id="btn-attach" title="Tambah — file, folder, gambar, video, mode berbicara"><i class="fa-solid fa-plus"></i></button>
                        <div class="cp-attach-menu" id="cp-attach-menu">
                            <button type="button" class="am-item" id="am-file"><span class="ic"><i class="fa-solid fa-paperclip"></i></span> Lampirkan Dokumen (.docx)</button>
                            <button type="button" class="am-item" id="am-docs"><span class="ic"><i class="fa-solid fa-folder-open"></i></span> Folder Dokumen</button>
                            <button type="button" class="am-item" id="am-image"><span class="ic"><i class="fa-solid fa-image"></i></span> Generate Gambar</button>
                            <button type="button" class="am-item" id="am-video"><span class="ic"><i class="fa-solid fa-video"></i></span> Generate Video</button>
                            <button type="button" class="am-item" id="am-speak"><span class="ic"><i class="fa-solid fa-volume-high"></i></span> Mode Berbicara <b class="am-state" id="am-speak-state">Mati</b></button>
                        </div>
                    </div>
                    <div class="cp-prov">
                        <button class="cp-prov-btn" id="cp-prov-btn" title="Pilih provider AI">
                            <i class="fa-solid fa-robot"></i>
                            <span id="cp-prov-name">Gemini (default)</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div class="cp-prov-menu" id="cp-prov-menu"></div>
                    </div>
                    {{-- Dropdown model — sebelah dropdown provider, isinya mengikuti provider aktif --}}
                    <div class="cp-model">
                        <button class="cp-prov-btn" id="cp-model-btn" title="Pilih model AI">
                            <i class="fa-solid fa-microchip"></i>
                            <span id="cp-model-name">Model otomatis</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div class="cp-prov-menu cp-model-menu" id="cp-model-menu"></div>
                    </div>
                    <span class="gen-chip" id="gen-chip" hidden></span>
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

{{-- Maskot & bubble "Kuro siap membantu" dihapus sesuai permintaan --}}

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
                API key dari dashboard 9Router. Model dipilih otomatis — tidak perlu menulis manual.
                Untuk Gemini cukup isi nama, driver <code>gemini</code>, dan API key — slug dibuat otomatis dari nama.
            </p>
            <form id="provider-form">
                <label>Nama</label>
                <input name="name" placeholder="9Router Online / Gemini" required>
                <label>Driver</label>
                <select name="driver">
                    <option value="openai-compatible">openai-compatible (9Router, OpenRouter, dll.)</option>
                    <option value="gemini">gemini</option>
                </select>
                <div id="prov-base-wrap">
                    <label>Base URL (khusus openai-compatible)</label>
                    <input name="base_url" placeholder="https://instance-9router-kamu.up.railway.app/v1">
                </div>
                <label>API Key</label>
                <input name="api_key" placeholder="sk-... / AIza... / AQ.Ab8...">
                <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_default" style="width:auto"> Jadikan provider default</label>
                <button type="submit" class="btn" style="width:100%;justify-content:center;margin-top:10px"><i class="fa-solid fa-floppy-disk"></i> Simpan Provider</button>
                <p id="provider-msg" class="hint"></p>
            </form>
            <button class="btn ghost" id="btn-open-sync" type="button" style="width:100%;justify-content:center;margin-top:12px">
                <i class="fa-solid fa-rotate"></i> Sinkron Router &amp; Mode AI (online / lokal)
            </button>
        </div>
    </div>
</div>

{{-- ===== Popup: Sinkron Router + Mode AI (2 dropdown: Gemini & Manual) ===== --}}
<div class="modal" id="sync-modal">
    <div class="modal-panel">
        <div class="modal-head">
            <h3><i class="fa-solid fa-rotate"></i>Sinkron Router &amp; Mode AI</h3>
            <button class="btn ghost small icon" id="btn-sync-close" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <p class="muted">
                Dua dropdown berdampingan tanpa bentrok: <b>Gemini</b> (Google AI Studio)
                dan <b>Manual</b> (Max Router / API kompatibel OpenAI — online di Railway
                maupun lokal <code>localhost:3001</code>). Mode menentukan yang aktif.
                <br><b>Mode Gemini tidak butuh router</b> — pesan error router bisa diabaikan
                kalau kamu memakai Gemini.
            </p>

            {{-- Dropdown 1: Mode AI --}}
            <label>Mode AI aktif</label>
            <select id="sync-mode">
                <option value="gemini">Gemini — Google AI Studio</option>
                <option value="manual">Manual — Max Router / API sendiri (online &amp; lokal)</option>
            </select>

            {{-- Panel Gemini --}}
            <div id="sync-gemini" class="sync-panel">
                <label>API Key Gemini</label>
                <input id="gemini-key" placeholder="AIza... / AQ.Ab8... — keduanya valid (kosongkan = pakai yang tersimpan)" autocomplete="off">
                <p class="hint" id="gemini-model-info">Model dipilih otomatis — tidak perlu menulis manual.</p>
                <p class="hint">
                    API key gratis di
                    <a href="https://aistudio.google.com/api-keys" target="_blank" rel="noopener">aistudio.google.com/api-keys</a>.
                    Format lama <code>AIza...</code> maupun format baru <code>AQ.Ab8...</code> keduanya valid — salin penuh, jangan terpotong.
                </p>
                <button class="btn ghost" id="btn-test-gemini" type="button" style="width:100%;justify-content:center;margin-top:8px">
                    <i class="fa-solid fa-plug-circle-check"></i> Tes &amp; Simpan Key Gemini
                </button>
                <p class="hint" id="test-gemini-msg"></p>
            </div>

            {{-- Panel Manual --}}
            <div id="sync-manual" class="sync-panel">
                <label>Sumber router (online / offline)</label>
                <select id="manual-scope">
                    <option value="online">Online — Max Router di Railway</option>
                    <option value="local">Lokal — Max Router di komputer ini (localhost:3001)</option>
                </select>
                <label>Base URL (endpoint kompatibel OpenAI)</label>
                <input id="manual-base" placeholder="https://instance-maxrouter-kamu.up.railway.app/v1">
                <label>API Key Router (opsional bila REQUIRE_API_KEY=false)</label>
                <input id="manual-key" placeholder="sk-... (kosongkan = pakai yang tersimpan / .env)" autocomplete="off">
                <p class="hint" id="manual-model-info">Model dipilih otomatis dari hasil sinkron router — tidak perlu menulis manual.</p>
            </div>

            <div class="prow" style="margin-top:14px">
                <button class="btn ghost" id="btn-router-status" type="button"><i class="fa-solid fa-signal"></i> Cek Status Router</button>
                <button class="btn ghost" id="btn-router-sync" type="button"><i class="fa-solid fa-rotate"></i> Sinkron Sekarang</button>
            </div>

            <p class="hint">
                <span class="sync-dot" id="dot-online"></span> Online (Railway): <b id="st-online">?</b><br>
                <span class="sync-dot" id="dot-local"></span> Lokal (localhost:3001): <b id="st-local">?</b>
            </p>
            <p class="hint" id="sync-msg"></p>

            <label style="margin-top:14px">Cari model yang tersedia — mana yang gratis / berbayar</label>
            <div class="model-drop" id="model-drop">
                <div class="model-field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input id="model-search" type="text" placeholder="Ketik nama model… (mis. gemini, flash, pro)" autocomplete="off">
                    <select id="model-tier" class="model-tier">
                        <option value="all">Semua</option>
                        <option value="free">Gratis</option>
                        <option value="paid">Berbayar</option>
                    </select>
                    <i class="fa-solid fa-chevron-down model-caret"></i>
                </div>
                <div class="model-menu" id="model-list"></div>
            </div>
            <button class="btn ghost" id="btn-model-list" type="button" style="width:100%;justify-content:center;margin-top:8px">
                <i class="fa-solid fa-rotate"></i> Muat / Perbarui Daftar Model
            </button>
            <p class="hint" id="model-count">Klik model pada dropdown untuk menyalin namanya.</p>

            <button class="btn" id="btn-setup-save" style="width:100%;justify-content:center;margin-top:10px" type="button">
                <i class="fa-solid fa-floppy-disk"></i> Simpan Mode &amp; API Key
            </button>
            <p class="hint" id="setup-msg"></p>
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
<script src="{{ asset('js/kuro.js') }}?v=20261030-1"></script>
@endpush


