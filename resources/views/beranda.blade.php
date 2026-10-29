@extends('layouts.app')

@section('title', 'Beranda — SkripsiAI')

@section('content')
<div class="wrap">
    <div class="topbar">
        <div class="brand">
            <div class="logo">🎓</div>
            <div>SkripsiAI <span class="muted" style="font-weight:400">· Beranda</span></div>
        </div>
        <form method="POST" action="/logout">
            @csrf
            <button type="submit" class="btn ghost">Keluar</button>
        </form>
    </div>

    {{-- ===== Statistik ===== --}}
    <div class="stats">
        <div class="stat"><span class="muted">Dokumen</span><b>{{ $stats['documents'] ?? '—' }}</b></div>
        <div class="stat"><span class="muted">Temuan AI (pending)</span><b>{{ $stats['highlights'] ?? '—' }}</b></div>
        <div class="stat"><span class="muted">Komentar</span><b>{{ $stats['comments'] ?? '—' }}</b></div>
        <div class="stat"><span class="muted">Provider AI</span><b id="stat-providers">…</b></div>
    </div>

    {{-- ===== Integrasi AI + Lihat Model ===== --}}
    <div class="card">
        <h2>🧠 Integrasi AI</h2>
        <p class="muted">
            <b>Gemini</b> (driver <code>gemini</code>) dan <b>multi-API</b> seperti 9Router online
            (driver <code>openai-compatible</code>). Klik <b>Lihat Model</b> untuk menampilkan
            daftar model tiap provider.
        </p>
        <div class="grid" id="providers"><p class="muted">Memuat provider…</p></div>
    </div>

    {{-- ===== Uji coba chat ===== --}}
    <div class="card" style="margin-top:22px">
        <h2>💬 Uji Coba AI</h2>
        <p class="muted">Cek koneksi &amp; jawaban model dari provider mana pun sebelum dipakai mereview dokumen.</p>
        <label for="chat-provider">Provider</label>
        <select id="chat-provider"><option value="">(memuat…)</option></select>
        <div class="chat-log" id="chat-log">
            <div class="msg ai">Halo! Saya asisten SkripsiAI. Tulis pesan di bawah untuk menguji integrasi AI.</div>
        </div>
        <textarea id="chat-input" rows="2" placeholder="Tulis pesan, tekan Ctrl+Enter untuk kirim…"></textarea>
        <div style="margin-top:10px;display:flex;gap:10px;align-items:center">
            <button class="btn" id="chat-send">Kirim</button>
            <span class="muted" id="chat-status"></span>
        </div>
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

<script>
(function () {
    var CSRF = document.querySelector('meta[name="csrf-token"]').content;

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function driverLabel(p) {
        if (p.base_url) return p.driver + ' · ' + p.base_url;
        if (p.driver === 'gemini') return 'gemini · Google AI Studio API';
        return p.driver;
    }

    // ---------- Provider + model ----------
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
        if (data.default) {
            sel.value = data.default;
        }

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
        fetch('/models/' + encodeURIComponent(slug), {headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(function (data) {
                out.textContent = '';
                if (!data.ok) {
                    out.innerHTML = '<span class="chip err">' + esc(data.error || 'Gagal') + '</span>';
                    return;
                }
                if (!data.models.length) {
                    out.innerHTML = '<span class="chip err">Tidak ada model ditemukan.</span>';
                    return;
                }
                data.models.forEach(function (m) {
                    var s = document.createElement('span');
                    s.className = 'chip';
                    s.textContent = m.name ? m.id + ' — ' + m.name : m.id;
                    out.appendChild(s);
                });
            })
            .catch(function (e) { out.textContent = 'Gagal: ' + e.message; });
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

    // ---------- Chat ----------
    var history = [];
    var chatLog = document.getElementById('chat-log');

    function addMsg(role, text) {
        var div = document.createElement('div');
        div.className = 'msg ' + role;
        div.textContent = text;
        chatLog.appendChild(div);
        chatLog.scrollTop = chatLog.scrollHeight;
    }

    function sendChat() {
        var input = document.getElementById('chat-input');
        var text = input.value.trim();
        if (!text) return;
        var btn = document.getElementById('chat-send');
        var status = document.getElementById('chat-status');
        addMsg('user', text);
        input.value = '';
        btn.disabled = true;
        status.textContent = 'Menunggu jawaban AI…';

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
                history: history
            })
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok) {
                addMsg('ai', data.content);
                history.push({role: 'user', content: text});
                history.push({role: 'assistant', content: data.content});
                status.textContent = '✔ ' + data.provider + ' · ' + data.model;
            } else {
                addMsg('ai', '⚠ ' + (data.error || 'Gagal.'));
                status.textContent = '';
            }
        }).catch(function (e) {
            addMsg('ai', '⚠ ' + e.message);
            status.textContent = '';
        }).finally(function () { btn.disabled = false; });
    }

    document.getElementById('chat-send').addEventListener('click', sendChat);
    document.getElementById('chat-input').addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') sendChat();
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
            } else {
                r.json().then(function (j) {
                    msg.textContent = '✗ ' + (j.message || 'Gagal menyimpan provider.');
                }).catch(function () { msg.textContent = '✗ Gagal menyimpan provider.'; });
            }
        });
    });

    loadProviders();
})();
</script>
@endsection
