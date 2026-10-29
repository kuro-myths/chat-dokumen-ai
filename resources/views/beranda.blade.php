@extends('layouts.app')

@section('title', 'Beranda — Kuro AI')

@section('content')
<div class="wrap">
    {{-- ===== Topbar ===== --}}
    <div class="topbar">
        <div class="brand">
            <div class="logo" aria-hidden="true">
                <svg viewBox="0 0 64 64" width="24" height="24">
                    <path d="M12 18c0-4 3-6 6-6h20c4 0 6 3 6 6v13c0 4-3 6-7 6H27l-7 7v-7c-4 0-8-3-8-7z" fill="#0d1226"/>
                    <text x="28" y="31" font-family="Arial" font-size="16" font-weight="bold" fill="#0d1226" text-anchor="middle">K</text>
                </svg>
            </div>
            <div>Kuro AI <span class="sub">Voice · Model · Dokumen</span></div>
        </div>
        <div class="navbtns">
            <button class="btn ghost icon" id="btn-history" title="Riwayat percakapan tersimpan">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M13 3a9 9 0 0 0-9 9H1l3.9 3.9.1.1 4-4H6a7 7 0 1 1 2.1 5L6.7 18.4A9 9 0 1 0 13 3zm-1 5v5l4.3 2.5.7-1.2-3.5-2.1V8z"/></svg>
            </button>
            <button class="btn ghost icon" id="btn-save-chat" title="Simpan percakapan ini">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zM12 19a3 3 0 1 1 0-6 3 3 0 0 1 0 6zM6 6h9v4H6z"/></svg>
            </button>
            <form method="POST" action="/logout">
                @csrf
                <button type="submit" class="btn ghost">Keluar</button>
            </form>
        </div>
    </div>

    {{-- ===== Statistik ===== --}}
    <div class="stats">
        <div class="stat"><span class="muted">Dokumen</span><b>{{ $stats['documents'] ?? '—' }}</b></div>
        <div class="stat"><span class="muted">Temuan AI (pending)</span><b>{{ $stats['highlights'] ?? '—' }}</b></div>
        <div class="stat"><span class="muted">Komentar</span><b>{{ $stats['comments'] ?? '—' }}</b></div>
        <div class="stat"><span class="muted">Provider AI</span><b id="stat-providers">…</b></div>
    </div>

    {{-- ===== Fitur unggulan ===== --}}
    <div class="features">
        <div class="feature" data-goto="chat">
            <div class="glow" style="background:#6d5efc"></div>
            <span class="ficon">🎙️</span>
            <b>Voice Suara</b>
            <p>Dikte langsung dengan suara — hasil transkripsi otomatis masuk ke percakapan. Kuro mendengarkan untukmu.</p>
        </div>
        <div class="feature" data-goto="integrasi">
            <div class="glow" style="background:#22d3ee"></div>
            <span class="ficon">🧠</span>
            <b>Cek Model</b>
            <p>Lihat daftar model AI tiap provider — Gemini, 9Router online, atau API kompatibel OpenAI lain.</p>
        </div>
        <div class="feature" data-goto="dokumen">
            <div class="glow" style="background:#34d399"></div>
            <span class="ficon">📄</span>
            <b>Periksa Dokumen</b>
            <p>Unggah skripsi/proposal .docx — AI memberi highlight warna, komentar, dan saran revisi.</p>
        </div>
    </div>

    {{-- ===== Integrasi AI ===== --}}
    <div class="card" id="integrasi">
        <h2>🧠 Integrasi AI &amp; Cek Model</h2>
        <p class="muted">
            <b>Gemini</b> (driver <code>gemini</code>) dan <b>multi-API</b> seperti 9Router online
            (driver <code>openai-compatible</code>). Klik <b>Lihat Model</b> untuk menampilkan daftar model.
        </p>
        <div class="grid" id="providers"><p class="muted">Memuat provider…</p></div>
    </div>

    {{-- ===== Chat + Voice ===== --}}
    <div class="card" style="margin-top:22px" id="chat">
        <h2>💬 Percakapan &amp; Dikte Suara</h2>
        <p class="muted">Ngobrol dengan Kuro — tekan ikon mic untuk mendikte dengan suara (bahasa Indonesia).</p>
        <label for="chat-provider">Provider</label>
        <select id="chat-provider"><option value="">(memuat…)</option></select>
        <div class="chat-log" id="chat-log"></div>
        <textarea id="chat-input" rows="2" placeholder="Tulis pesan atau tekan mic, lalu Ctrl+Enter untuk kirim…"></textarea>
        <div class="chat-tools">
            <button class="btn" id="chat-send">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M2 21l21-9L2 3v7l15 2-15 2z"/></svg>
                Kirim
            </button>
            <button class="btn ghost icon" id="btn-mic" title="Dikte suara">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 1 0-6 0v5a3 3 0 0 0 3 3z"/><path d="M19 11a1 1 0 1 0-2 0 5 5 0 0 1-10 0 1 1 0 1 0-2 0 7 7 0 0 0 6 6.92V20H8a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2h-3v-2.08A7 7 0 0 0 19 11z"/></svg>
            </button>
            <button class="btn ghost small" id="btn-clear-chat">Kosongkan</button>
            <span class="muted" id="chat-status" style="font-size:.78rem"></span>
        </div>
    </div>

    {{-- ===== Periksa dokumen ===== --}}
    <div class="card" style="margin-top:22px" id="dokumen">
        <h2>📄 Periksa Dokumen</h2>
        <p class="muted">Unggah .docx skripsi/proposal → AI menganalisis bahasa, sitasi, metodologi &amp; struktur → unduh hasil dengan highlight warna.</p>
        <div class="dropzone" id="dropzone">
            <p style="font-size:1.6rem;margin:0">📥</p>
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
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M19 13v6H5v-6H3v8h18v-8zM11 5.8 8.4 8.4 7 7l5-5 5 5-1.4 1.4L13 5.8V16h-2z"/></svg>
                    Unggah
                </button>
            </div>
        </div>
        <p class="hint">Setelah unggah, tekan <b>Periksa AI</b> pada dokumen. Untuk dokumen panjang, jalankan <code>php artisan queue:work</code> di terminal kedua.</p>
        <div id="doc-list"></div>
        <div id="doc-findings"></div>
    </div>

    {{-- ===== Tambah provider ===== --}}
    <div class="card" style="margin-top:22px;margin-bottom:40px">
        <h2>➕ Tambah Provider</h2>
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
                <button type="submit" class="btn" style="width:100%;justify-content:center">Simpan</button>
            </div>
        </form>
        <p id="provider-msg" class="hint"></p>
    </div>
</div>

{{-- ===== Maskot Kuro ===== --}}
<div class="kuro-wrap" id="kuro">
    <div class="kuro-bubble" id="kuro-bubble">Kuro siap membantu ✨</div>
    <svg class="kuro-svg" viewBox="0 0 120 112" width="110" aria-hidden="true">
        <g>
            <path d="M30 48 L20 16 L48 36 Z" fill="#151d38" stroke="#2b3a6b" stroke-width="2"/>
            <path d="M90 48 L100 16 L72 36 Z" fill="#151d38" stroke="#2b3a6b" stroke-width="2"/>
            <ellipse cx="60" cy="64" rx="42" ry="40" fill="#151d38" stroke="#2b3a6b" stroke-width="2"/>
            <ellipse class="kuro-eye" cx="46" cy="60" rx="7" ry="9" fill="#22d3ee"/>
            <ellipse class="kuro-eye" cx="74" cy="60" rx="7" ry="9" fill="#22d3ee"/>
            <path d="M52 79 Q60 87 68 79" stroke="#22d3ee" stroke-width="3" fill="none" stroke-linecap="round"/>
            <path d="M22 66 L36 68 M22 76 L36 75" stroke="#2b3a6b" stroke-width="2" stroke-linecap="round"/>
            <path d="M98 66 L84 68 M98 76 L84 75" stroke="#2b3a6b" stroke-width="2" stroke-linecap="round"/>
        </g>
    </svg>
    <div class="kuro-scan"></div>
</div>

{{-- ===== Modal riwayat percakapan ===== --}}
<div class="modal" id="hist-modal">
    <div class="modal-panel">
        <div class="modal-head">
            <h3>🕘 Riwayat Percakapan</h3>
            <div style="display:flex;gap:8px">
                <button class="btn ghost small" id="btn-hist-clear">Hapus Semua</button>
                <button class="btn ghost small" id="btn-hist-close">✕</button>
            </div>
        </div>
        <div class="modal-body" id="hist-list"><p class="empty">Belum ada percakapan tersimpan.</p></div>
    </div>
</div>

<script>
(function () {
    var CSRF = document.querySelector('meta[name="csrf-token"]').content;
    var MIC_HTML = document.getElementById('btn-mic').innerHTML;
    var STOP_HTML = '<svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>';

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    // ---------- Maskot Kuro ----------
    var kuroEl = document.getElementById('kuro');
    var kuroBubble = document.getElementById('kuro-bubble');
    var thinkingCount = 0;

    function kuroThinking(on, text) {
        thinkingCount = Math.max(0, thinkingCount + (on ? 1 : -1));
        if (text) kuroBubble.textContent = text;
        if (thinkingCount > 0) {
            kuroEl.classList.add('thinking');
            if (!text) kuroBubble.textContent = 'Kuro sedang menganalisis… 🔍';
        } else {
            kuroEl.classList.remove('thinking');
            kuroBubble.textContent = 'Kuro siap membantu ✨';
        }
    }

    // ---------- Fitur card ----------
    document.querySelectorAll('.feature').forEach(function (f) {
        f.addEventListener('click', function () {
            var t = document.getElementById(f.getAttribute('data-goto'));
            if (t) t.scrollIntoView({behavior: 'smooth'});
            if (f.getAttribute('data-goto') === 'chat') document.getElementById('chat-input').focus();
        });
    });

    // ---------- Provider + model ----------
    function driverLabel(p) {
        if (p.base_url) return p.driver + ' · ' + p.base_url;
        if (p.driver === 'gemini') return 'gemini · Google AI Studio API';
        return p.driver;
    }

    function renderProviders(data) {
        var box = document.getElementById('providers');
        var sel = document.getElementById('chat-provider');
        box.innerHTML = '';
        sel.innerHTML = '';
        document.getElementById('stat-providers').textContent = data.providers.length;

        (data.providers || []).forEach(function (p) {
            var opt = document.createElement('option');
            opt.value = p.slug;
            opt.textContent = p.name + (p.is_default ? ' (default)' : '');
            sel.appendChild(opt);
        });
        if (data.default) sel.value = data.default;

        (data.providers || []).forEach(function (p) {
            var card = document.createElement('div');
            card.className = 'prov';
            card.innerHTML =
                '<div class="prov-head"><span class="prov-dot ' + (p.configured ? 'on' : 'off') + '"></span>' +
                '<b>' + esc(p.name) + '</b>' +
                (p.is_default ? '<span class="chip">default</span>' : '') +
                (p.source === 'database' ? '<span class="chip">database</span>' : '') +
                '</div>' +
                '<p class="muted" style="margin:2px 0 0">' + esc(driverLabel(p)) +
                (p.model ? ' · model aktif: <code>' + esc(p.model) + '</code>' : '') + '</p>' +
                '<div class="model-list muted">Klik "Lihat Model" untuk menampilkan model.</div>' +
                '<div class="prov-actions">' +
                '<button class="btn small show-models">Lihat Model</button>' +
                (p.is_default ? '' : '<button class="btn small ghost set-default">Jadikan Default</button>') +
                '</div>';

            card.querySelector('.show-models').addEventListener('click', function () {
                showModels(p.slug, card);
            });
            var defBtn = card.querySelector('.set-default');
            if (defBtn) defBtn.addEventListener('click', function () { setDefault(p.slug); });

            box.appendChild(card);
        });

        if (!data.providers.length) {
            box.innerHTML = '<p class="muted">Belum ada provider — tambahkan lewat form di bawah.</p>';
        }
    }

    function loadProviders() {
        fetch('/models', {headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok === false && data.error) {
                    document.getElementById('providers').innerHTML =
                        '<p class="muted">' + esc(data.error) + '</p>';
                    return;
                }
                renderProviders(data);
            })
            .catch(function (e) {
                document.getElementById('providers').innerHTML =
                    '<p class="muted">Gagal memuat: ' + esc(e.message) + '</p>';
            });
    }

    function showModels(slug, card) {
        var out = card.querySelector('.model-list');
        out.classList.remove('muted');
        out.textContent = 'Memuat model…';
        kuroThinking(true, 'Kuro mengecek model… 🧠');
        fetch('/models/' + encodeURIComponent(slug), {headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(function (data) {
                out.textContent = '';
                if (!data.ok) {
                    out.innerHTML = '<span class="chip err">' + esc(data.error || 'Gagal') + '</span>';
                    kuroThinking(false);
                    return;
                }
                if (!data.models.length) {
                    out.innerHTML = '<span class="chip err">Tidak ada model ditemukan.</span>';
                    kuroThinking(false);
                    return;
                }
                data.models.forEach(function (m) {
                    var s = document.createElement('span');
                    s.className = 'chip';
                    s.textContent = m.name ? m.id + ' — ' + m.name : m.id;
                    out.appendChild(s);
                });
                kuroThinking(false, 'Ketemu ' + data.models.length + ' model ✅');
            })
            .catch(function (e) { out.textContent = 'Gagal: ' + e.message; kuroThinking(false); });
    }

    function setDefault(slug) {
        fetch('/api/ai/providers/default', {
            method: 'PUT',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify({slug: slug})
        }).then(function (r) {
            if (r.ok) { loadProviders(); } else { r.json().then(function (j) { alert(j.message || 'Gagal.'); }); }
        });
    }

    // ---------- Chat + history ----------
    var history = [];
    var chatLog = document.getElementById('chat-log');

    function renderChat() {
        chatLog.innerHTML = '';
        history.forEach(function (m) {
            var div = document.createElement('div');
            div.className = 'msg ' + (m.role === 'user' ? 'user' : 'ai');
            div.textContent = m.content;
            chatLog.appendChild(div);
        });
        chatLog.scrollTop = chatLog.scrollHeight;
    }

    function saveCurrent() {
        try {
            localStorage.setItem('kuro_current', JSON.stringify({
                provider: document.getElementById('chat-provider').value,
                messages: history
            }));
        } catch (e) {}
    }

    function restoreCurrent() {
        try {
            var raw = localStorage.getItem('kuro_current');
            if (!raw) return;
            var data = JSON.parse(raw);
            if (data.messages && data.messages.length) {
                history = data.messages;
                renderChat();
            }
        } catch (e) {}
    }

    function sendChat() {
        var input = document.getElementById('chat-input');
        var text = input.value.trim();
        if (!text) return;
        var btn = document.getElementById('chat-send');
        var status = document.getElementById('chat-status');
        history.push({role: 'user', content: text});
        renderChat();
        input.value = '';
        btn.disabled = true;
        status.textContent = 'Menunggu jawaban AI…';
        kuroThinking(true, 'Kuro sedang menganalisis… 🔍');

        fetch('/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF
            },
            body: JSON.stringify({
                message: text,
                provider: document.getElementById('chat-provider').value || null,
                history: history.slice(0, -1).slice(-20)
            })
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok) {
                history.push({role: 'assistant', content: data.content});
                renderChat();
                status.textContent = '✔ ' + data.provider + ' · ' + data.model;
                kuroThinking(false, 'Selesai! Ada lagi? ✨');
                saveCurrent();
            } else {
                history.pop();
                renderChat();
                status.textContent = '';
                kuroThinking(false, 'Ada kendala 😿');
                alert(data.error || 'Gagal.');
            }
        }).catch(function (e) {
            history.pop();
            renderChat();
            status.textContent = '';
            kuroThinking(false, 'Ada kendala 😿');
            alert(e.message);
        }).finally(function () { btn.disabled = false; });
    }

    document.getElementById('chat-send').addEventListener('click', sendChat);
    document.getElementById('chat-input').addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') sendChat();
    });
    document.getElementById('btn-clear-chat').addEventListener('click', function () {
        history = [];
        renderChat();
        saveCurrent();
    });

    // ---------- Dikte suara ----------
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    var listening = false;
    var rec = null;

    document.getElementById('btn-mic').addEventListener('click', function () {
        var micBtn = document.getElementById('btn-mic');
        if (!SR) { alert('Browser belum mendukung input suara — coba Chrome/Edge terbaru.'); return; }
        if (listening) { rec.stop(); return; }
        rec = new SR();
        rec.lang = 'id-ID';
        rec.interimResults = true;
        rec.continuous = false;
        listening = true;
        micBtn.innerHTML = STOP_HTML;
        kuroThinking(true, 'Kuro mendengarkan… 🎙️');
        rec.onresult = function (e) {
            var t = '';
            for (var i = e.resultIndex; i < e.results.length; i++) t += e.results[i][0].transcript;
            document.getElementById('chat-input').value = t;
        };
        rec.onend = function () {
            listening = false;
            micBtn.innerHTML = MIC_HTML;
            kuroThinking(false, 'Dikte siap — tekan kirim ✨');
        };
        rec.onerror = function (e) {
            listening = false;
            micBtn.innerHTML = MIC_HTML;
            kuroThinking(false, 'Suara gagal 😿');
            alert('Error suara: ' + e.error);
        };
        rec.start();
    });

    // ---------- Riwayat tersimpan ----------
    function getSaved() {
        try { return JSON.parse(localStorage.getItem('kuro_saved')) || []; }
        catch (e) { return []; }
    }

    function setSaved(list) {
        try { localStorage.setItem('kuro_saved', JSON.stringify(list)); } catch (e) {}
    }

    document.getElementById('btn-save-chat').addEventListener('click', function () {
        if (!history.length) { alert('Belum ada percakapan untuk disimpan.'); return; }
        var firstUser = '';
        history.forEach(function (m) { if (!firstUser && m.role === 'user') firstUser = m.content; });
        var title = prompt('Nama percakapan:', (firstUser || 'Percakapan').slice(0, 40));
        if (title === null) return;
        var list = getSaved();
        list.unshift({
            title: title || 'Percakapan',
            time: new Date().toLocaleString('id-ID'),
            provider: document.getElementById('chat-provider').value,
            messages: history.slice()
        });
        setSaved(list);
        kuroThinking(false, 'Percakapan tersimpan 💾');
        alert('Percakapan disimpan (' + list.length + ' total).');
    });

    function renderHistory() {
        var box = document.getElementById('hist-list');
        var list = getSaved();
        box.innerHTML = '';
        if (!list.length) {
            box.innerHTML = '<p class="empty">Belum ada percakapan tersimpan.</p>';
            return;
        }
        list.forEach(function (item, idx) {
            var div = document.createElement('div');
            div.className = 'hist-item';
            div.innerHTML =
                '<div class="grow"><b>' + esc(item.title) + '</b>' +
                '<small>' + esc(item.time) + ' · ' + item.messages.length + ' pesan · ' + esc(item.provider || '-') + '</small></div>' +
                '<button class="btn small hist-load">Muat</button>' +
                '<button class="btn small ghost hist-del">Hapus</button>';
            div.querySelector('.hist-load').addEventListener('click', function () {
                history = item.messages.slice();
                renderChat();
                saveCurrent();
                document.getElementById('hist-modal').classList.remove('open');
                document.getElementById('chat').scrollIntoView({behavior: 'smooth'});
                kuroThinking(false, 'Percakapan dimuat kembali ✨');
            });
            div.querySelector('.hist-del').addEventListener('click', function () {
                var l = getSaved();
                l.splice(idx, 1);
                setSaved(l);
                renderHistory();
            });
            box.appendChild(div);
        });
    }

    document.getElementById('btn-history').addEventListener('click', function () {
        renderHistory();
        document.getElementById('hist-modal').classList.add('open');
    });
    document.getElementById('btn-hist-close').addEventListener('click', function () {
        document.getElementById('hist-modal').classList.remove('open');
    });
    document.getElementById('hist-modal').addEventListener('click', function (e) {
        if (e.target === this) this.classList.remove('open');
    });
    document.getElementById('btn-hist-clear').addEventListener('click', function () {
        if (confirm('Hapus semua percakapan tersimpan?')) {
            setSaved([]);
            renderHistory();
        }
    });

    // ---------- Periksa dokumen ----------
    var polls = {};

    function loadDocs() {
        fetch('/api/documents', {headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(renderDocs)
            .catch(function () {});
    }

    function docActions(id, doc) {
        return '<button class="btn small review-btn">Periksa AI</button>' +
            '<button class="btn small ghost hl-btn">Muat Temuan</button>' +
            '<a class="btn small ghost" href="/api/documents/' + id + '/export">' +
            '<svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M12 16 7 11l1.4-1.4L11 12.2V4h2v8.2l2.6-2.6L17 11zM5 18h14v2H5z"/></svg>Unduh</a>';
    }

    function renderDocs(docs) {
        var box = document.getElementById('doc-list');
        box.innerHTML = '';
        if (!docs.length) {
            box.innerHTML = '<p class="muted" style="margin-top:12px">Belum ada dokumen — unggah .docx pertamamu di atas.</p>';
            return;
        }
        docs.forEach(function (d) {
            var div = document.createElement('div');
            div.className = 'doc-item';
            div.innerHTML =
                '<div class="grow"><b>' + esc(d.title) + '</b> ' +
                '<span class="muted" style="font-size:.75rem">#' + d.id + ' · ' + esc(d.type) + '</span></div>' +
                '<span class="badge ' + esc(d.status) + '">' + esc(d.status) + '</span>' +
                '<div class="doc-actions">' + docActions(d.id, d) + '</div>';
            div.querySelector('.review-btn').addEventListener('click', function () {
                startReview(d.id);
            });
            div.querySelector('.hl-btn').addEventListener('click', function () {
                loadHighlights(d.id, d.title);
            });
            box.appendChild(div);
        });
    }

    function startReview(id) {
        var provider = document.getElementById('chat-provider').value || null;
        kuroThinking(true, 'Kuro sedang membaca dokumen… 📄');
        fetch('/api/documents/' + id + '/review', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF
            },
            body: JSON.stringify({provider: provider})
        }).then(function (r) { return r.json(); }).then(function () {
            loadDocs();
            pollReview(id);
        }).catch(function (e) { kuroThinking(false); alert(e.message); });
    }

    function pollReview(id) {
        if (polls[id]) return;
        var tries = 0;
        polls[id] = setInterval(function () {
            tries++;
            fetch('/api/documents/' + id + '/review', {headers: {'Accept': 'application/json'}})
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.status === 'reviewing') {
                        if (tries > 10) {
                            clearInterval(polls[id]);
                            polls[id] = null;
                            kuroThinking(false, 'Review masih diproses 😿');
                            alert('Review masih diproses. Pastikan "php artisan queue:work" jalan di terminal lain.');
                        }
                        return;
                    }
                    clearInterval(polls[id]);
                    polls[id] = null;
                    loadDocs();
                    if (data.status === 'reviewed') {
                        kuroThinking(false, 'Review selesai! ✅ (' + (data.highlights_count || 0) + ' temuan)');
                        loadHighlights(id);
                    } else {
                        kuroThinking(false, 'Review gagal 😿');
                        alert('Review gagal: ' + data.status);
                    }
                })
                .catch(function () {});
        }, 4000);
    }

    function loadHighlights(id, title) {
        kuroThinking(true, 'Kuro mengumpulkan temuan… 🔎');
        fetch('/api/documents/' + id + '/highlights', {headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(function (list) {
                var box = document.getElementById('doc-findings');
                box.innerHTML = '<h3 style="margin-top:22px">🔎 Temuan: ' + esc(title || ('Dokumen #' + id)) +
                    ' <span class="chip">' + list.length + ' temuan</span></h3>';
                if (!list.length) {
                    box.innerHTML += '<p class="muted">Belum ada temuan — jalankan "Periksa AI" dulu.</p>';
                    kuroThinking(false);
                    return;
                }
                list.slice(0, 30).forEach(function (h) {
                    var div = document.createElement('div');
                    div.className = 'hl-item c-' + esc(h.color || 'green');
                    div.innerHTML = '<div class="q">' + esc(h.quote) + '</div>' +
                        (h.comment ? '<div class="c">💬 ' + esc(h.comment) + '</div>' : '') +
                        (h.suggestion ? '<div class="c">✍️ ' + esc(h.suggestion) + '</div>' : '');
                    box.appendChild(div);
                });
                box.scrollIntoView({behavior: 'smooth'});
                kuroThinking(false, 'Ditemukan ' + list.length + ' temuan ✅');
            })
            .catch(function (e) { kuroThinking(false); alert(e.message); });
    }

    // Upload
    var dz = document.getElementById('dropzone');
    var fileInput = document.getElementById('doc-file');
    dz.addEventListener('click', function () { fileInput.click(); });
    dz.addEventListener('dragover', function (e) { e.preventDefault(); dz.classList.add('drag'); });
    dz.addEventListener('dragleave', function () { dz.classList.remove('drag'); });
    dz.addEventListener('drop', function (e) {
        e.preventDefault();
        dz.classList.remove('drag');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            alert('File dipilih: ' + e.dataTransfer.files[0].name + ' — tekan "Unggah".');
        }
    });

    document.getElementById('doc-upload').addEventListener('click', function () {
        var file = fileInput.files[0];
        if (!file) { alert('Pilih file .docx dulu (klik area unggah di atas).'); return; }
        if (!/\.docx$/i.test(file.name)) { alert('File harus berformat .docx'); return; }
        var btn = this;
        btn.disabled = true;
        kuroThinking(true, 'Kuro mengimpor dokumen… 📥');
        var fd = new FormData();
        fd.append('document', file);
        fd.append('type', document.getElementById('doc-type').value);
        var title = document.getElementById('doc-title').value.trim();
        if (title) fd.append('title', title);
        fetch('/api/documents', {method: 'POST', headers: {'Accept': 'application/json'}, body: fd})
            .then(function (r) {
                if (!r.ok) throw new Error('Unggah gagal (cek format .docx).');
                return r.json();
            })
            .then(function (data) {
                kuroThinking(false, 'Dokumen terimpor ✅ mulai review…');
                document.getElementById('doc-title').value = '';
                fileInput.value = '';
                loadDocs();
                var id = data.document ? data.document.id : null;
                if (id) startReview(id);
            })
            .catch(function (e) { kuroThinking(false); alert(e.message); })
            .finally(function () { btn.disabled = false; });
    });

    // ---------- Tambah provider ----------
    document.getElementById('provider-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var f = e.target;
        var msg = document.getElementById('provider-msg');
        var body = {
            slug: f.slug.value.trim(),
            name: f.name.value.trim(),
            driver: f.driver.value,
            base_url: f.base_url.value.trim() || null,
            model: f.model.value.trim() || null,
            api_key: f.api_key.value.trim() || null,
            is_default: f.is_default.checked
        };
        fetch('/api/ai/providers', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify(body)
        }).then(function (r) {
            if (r.ok) {
                msg.textContent = '✔ Provider tersimpan.';
                f.reset();
                loadProviders();
                kuroThinking(false, 'Provider baru ditambahkan ✨');
            } else {
                r.json().then(function (j) {
                    msg.textContent = '✗ ' + (j.message || 'Gagal menyimpan provider.');
                }).catch(function () { msg.textContent = '✗ Gagal menyimpan provider.'; });
            }
        });
    });

    // ---------- Init ----------
    loadProviders();
    loadDocs();
    restoreCurrent();
})();
</script>
@endsection
