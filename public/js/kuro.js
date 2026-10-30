
/* ============================================================
   Kuro AI — logika beranda (public/js/kuro.js)
   Dimuat di beranda saja (satu sumber, tidak ada script inline).
   Fitur: sidebar buka-tutup, mode landing/chat/dokumen,
   composer ala Gemini, dropdown API Key + popup provider,
   split-screen preview dokumen, dikte suara, riwayat.
   ============================================================ */
(function () {
    var CSRF = document.querySelector('meta[name="csrf-token"]').content;
    var MIC_HTML = '<i class="fa-solid fa-microphone"></i>';
    var STOP_HTML = '<i class="fa-solid fa-stop"></i>';

    var shell = document.getElementById('shell');
    var composer = document.getElementById('composer');
    var landing = document.getElementById('landing');
    var statCards = document.getElementById('stat-cards');
    var chatCol = document.getElementById('chat-col');
    var chatLog = document.getElementById('chat-log');
    var chatInput = document.getElementById('chat-input');
    var docLinkOn = true; // saat dokumen terbuka, chat otomatis membawa konteks dokumen
    var conciseOn = false; // mode ⚡ Singkat & Jelas — jawaban super ringkas

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function isMobile() { return window.innerWidth <= 960; }

    // ---------- Popup modern Kuro (mengganti alert/prompt bawaan browser) ----------
    var kalertEl = null;   // popup notifikasi
    var kpromptEl = null;  // popup input (mengganti window.prompt)
    var ktoastWrap = null; // wadah toast kecil

    var KALERT_IC = {
        error:   'fa-solid fa-triangle-exclamation',
        success: 'fa-solid fa-circle-check',
        info:    'fa-solid fa-circle-info'
    };
    var KALERT_TITLE = { error: 'Ada kendala', success: 'Berhasil', info: 'Pemberitahuan' };

    function kuroAlert(msg, type) {
        type = KALERT_IC[type] ? type : 'error';
        if (!kalertEl) {
            kalertEl = document.createElement('div');
            kalertEl.className = 'kalert';
            kalertEl.innerHTML =
                '<div class="kalert-panel" role="dialog" aria-modal="true">' +
                '<div class="kalert-ic"></div>' +
                '<h4 class="kalert-title"></h4>' +
                '<p class="kalert-msg"></p>' +
                '<button type="button" class="btn kalert-ok">Oke, mengerti</button>' +
                '</div>';
            document.body.appendChild(kalertEl);
            kalertEl.addEventListener('click', function (e) { if (e.target === kalertEl) closeKuroAlert(); });
            kalertEl.querySelector('.kalert-ok').addEventListener('click', closeKuroAlert);
        }
        var ic = kalertEl.querySelector('.kalert-ic');
        ic.className = 'kalert-ic ' + type;
        ic.innerHTML = '<i class="' + KALERT_IC[type] + '"></i>';
        kalertEl.querySelector('.kalert-title').textContent = KALERT_TITLE[type];
        kalertEl.querySelector('.kalert-msg').textContent = msg;
        kalertEl.classList.add('open');
    }

    function closeKuroAlert() { if (kalertEl) kalertEl.classList.remove('open'); }

    // Prompt modern (mengganti window.prompt) — cb dipanggil saat "Simpan"
    function kuroPrompt(title, value, cb) {
        if (!kpromptEl) {
            kpromptEl = document.createElement('div');
            kpromptEl.className = 'kalert';
            kpromptEl.innerHTML =
                '<div class="kalert-panel" role="dialog" aria-modal="true">' +
                '<div class="kalert-ic info"><i class="fa-solid fa-floppy-disk"></i></div>' +
                '<h4 class="kalert-title"></h4>' +
                '<input class="kprompt-input" type="text" maxlength="60" placeholder="Nama percakapan">' +
                '<div class="kprompt-act">' +
                '<button type="button" class="btn ghost kprompt-cancel">Batal</button>' +
                '<button type="button" class="btn kprompt-ok">Simpan</button>' +
                '</div></div>';
            document.body.appendChild(kpromptEl);
            kpromptEl.addEventListener('click', function (e) { if (e.target === kpromptEl) closeKuroPrompt(); });
            kpromptEl.querySelector('.kprompt-cancel').addEventListener('click', closeKuroPrompt);
            kpromptEl.querySelector('.kprompt-ok').addEventListener('click', function () {
                var v = kpromptEl.querySelector('.kprompt-input').value.trim();
                closeKuroPrompt();
                cb(v);
            });
            kpromptEl.querySelector('.kprompt-input').addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); kpromptEl.querySelector('.kprompt-ok').click(); }
            });
        }
        kpromptEl.querySelector('.kalert-title').textContent = title;
        var input = kpromptEl.querySelector('.kprompt-input');
        input.value = value || '';
        kpromptEl.classList.add('open');
        setTimeout(function () { input.focus(); input.select(); }, 60);
    }

    function closeKuroPrompt() { if (kpromptEl) kpromptEl.classList.remove('open'); }

    // Toast kecil untuk notifikasi singkat (mis. model disalin)
    function kuroToast(msg, type) {
        if (!ktoastWrap) {
            ktoastWrap = document.createElement('div');
            ktoastWrap.className = 'ktoast-wrap';
            document.body.appendChild(ktoastWrap);
        }
        var t = document.createElement('div');
        t.className = 'ktoast' + (type ? ' ' + type : '');
        t.textContent = msg;
        ktoastWrap.appendChild(t);
        setTimeout(function () {
            t.classList.add('out');
            setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 220);
        }, 2600);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeKuroAlert(); closeKuroPrompt(); }
    });

    // ---------- Maskot Kuro: animasi "sedang membaca / memeriksa" ----------
    var kthinkEl = null;

    function kuroThinking(show, msg) {
        if (show) {
            if (!kthinkEl) {
                kthinkEl = document.createElement('div');
                kthinkEl.className = 'kuro-read';
                kthinkEl.innerHTML =
                    '<div class="kuro-read-panel">' +
                    '<div class="kuro-read-cat"><span class="kr-cat">🐱</span><span class="kr-book">📖</span><span class="kr-scan"></span></div>' +
                    '<p class="kuro-read-msg"></p>' +
                    '<div class="kuro-read-dots"><i></i><i></i><i></i></div>' +
                    '</div>';
                document.body.appendChild(kthinkEl);
            }
            kthinkEl.querySelector('.kuro-read-msg').textContent = msg || 'Kuro sedang berpikir…';
            kthinkEl.classList.add('open');
        } else {
            if (kthinkEl) kthinkEl.classList.remove('open');
            if (msg) kuroToast(msg, /gagal|😿/.test(msg) ? 'err' : 'ok');
        }
    }

    // ---------- Sidebar buka/tutup ----------
    var sbClosed = false;
    try { sbClosed = localStorage.getItem('kuro_sb') === 'closed'; } catch (e) {}

    function applySidebar() {
        if (isMobile()) shell.classList.remove('sb-closed');
        else shell.classList.toggle('sb-closed', sbClosed);
    }
    applySidebar();

    function toggleSidebar() {
        if (isMobile()) {
            shell.classList.toggle('sb-open-m');
        } else {
            sbClosed = !sbClosed;
            try { localStorage.setItem('kuro_sb', sbClosed ? 'closed' : 'open'); } catch (e) {}
            applySidebar();
            closeApiDrop();
        }
    }
    document.getElementById('btn-side-toggle').addEventListener('click', toggleSidebar);
    document.getElementById('btn-side-toggle-m').addEventListener('click', toggleSidebar);

    // ---------- Mode: landing / chat / dokumen ----------
    var MODE = 'landing';

    function setMode(m) {
        MODE = m;
        shell.dataset.mode = m;
        if (m === 'landing') {
            landing.insertBefore(composer, statCards);
        } else {
            chatCol.appendChild(composer);
        }
    }

    // ---------- Provider & API Key ----------
    var providers = [];
    var currentProvider = null; // slug
    var currentModel = null; // model aktif (null = pakai default provider)

    function driverLabel(p) {
        if (p.base_url) return p.driver + ' · ' + p.base_url;
        if (p.driver === 'gemini') return 'gemini · Google AI Studio';
        return p.driver;
    }

    function renderProviders(data) {
        providers = data.providers || [];
        if (data.default && !currentProvider) currentProvider = data.default;

        // Statistik provider
        document.getElementById('stat-providers').textContent = providers.length;

        // Dropdown API Key di sidebar
        var drop = document.getElementById('api-drop-list');
        drop.innerHTML = '';
        if (!providers.length) {
            drop.innerHTML = '<p class="muted" style="font-size:.78rem;margin:4px 6px">Belum ada provider — tambahkan API Key.</p>';
        }
        providers.forEach(function (p) {
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'api-row';
            row.title = 'Klik untuk jadikan provider ini aktif/default';
            row.innerHTML =
                '<span class="prov-dot ' + (p.configured ? 'on' : 'off') + '"></span>' +
                '<span class="grow"><b>' + esc(p.name) + (p.is_default ? ' · default' : '') + '</b>' +
                '<small>' + esc(driverLabel(p)) + '</small></span>';
            row.addEventListener('click', function () {
                currentProvider = p.slug;
                updateProvName();
                setDefault(p.slug);
            });
            drop.appendChild(row);
        });

        // Menu provider di composer + daftar model provider aktif
        renderProvMenu();
        updateProvName();
        loadProviderModels(currentProvider);
    }

    function renderProvMenu() {
        var menu = document.getElementById('cp-prov-menu');
        menu.innerHTML = '';
        providers.forEach(function (p) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'cp-prov-item';
            item.innerHTML =
                '<i class="fa-solid fa-check chk" style="visibility:' + (p.slug === currentProvider ? 'visible' : 'hidden') + '"></i>' +
                '<span class="grow">' + esc(p.name) + '<small>' + esc(driverLabel(p)) + '</small></span>';
            item.addEventListener('click', function () {
                currentProvider = p.slug;
                currentModel = null; // ganti provider -> reset pilihan model
                saveModelChoice();
                menu.classList.remove('open');
                renderProvMenu();
                updateProvName();
                loadProviderModels(p.slug);
                kuroThinking(false, 'Provider aktif: ' + p.name + ' ✨');
            });
            menu.appendChild(item);
        });
        if (!providers.length) {
            menu.innerHTML = '<p class="muted" style="font-size:.8rem;padding:8px 12px">Belum ada provider — tambah lewat sidebar "API Key".</p>';
        }
    }

    function updateProvName() {
        var name = 'Pilih provider';
        providers.forEach(function (p) { if (p.slug === currentProvider) name = p.name; });
        document.getElementById('cp-prov-name').textContent = name;
    }

    function loadProviders() {
        fetch('/models', {headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok === false && data.error) return;
                renderProviders(data);
            })
            .catch(function () {});
    }

    function setDefault(slug) {
        fetch('/api/ai/providers/default', {
            method: 'PUT',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify({slug: slug})
        }).then(function (r) { if (r.ok) loadProviders(); });
    }

    // Dropdown API Key (expand di sidebar)
    var apiDrop = document.getElementById('api-drop');
    var apiBtn = document.getElementById('btn-apikey');

    function closeApiDrop() {
        apiDrop.classList.remove('open');
        apiBtn.querySelector('.caret').classList.remove('rot');
    }
    function toggleApiDrop() {
        var open = apiDrop.classList.toggle('open');
        apiBtn.querySelector('.caret').classList.toggle('rot', open);
    }
    apiBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        toggleApiDrop();
    });
    document.addEventListener('click', function (e) {
        if (!apiDrop.contains(e.target) && !apiBtn.contains(e.target)) closeApiDrop();
    });

    // Popup tambah provider
    var provModal = document.getElementById('prov-modal');
    document.getElementById('btn-add-provider').addEventListener('click', function () {
        closeApiDrop();
        if (isMobile()) shell.classList.remove('sb-open-m');
        provModal.classList.add('open');
    });
    document.getElementById('btn-prov-close').addEventListener('click', function () { provModal.classList.remove('open'); });
    provModal.addEventListener('click', function (e) { if (e.target === this) provModal.classList.remove('open'); });

    // Driver gemini tidak butuh Base URL — sembunyikan & kosongkan otomatis.
    var provDriver = document.querySelector('#provider-form select[name="driver"]');
    function applyProvDriver() {
        var wrap = document.getElementById('prov-base-wrap');
        var isGemini = provDriver.value === 'gemini';
        wrap.style.display = isGemini ? 'none' : '';
        if (isGemini) wrap.querySelector('input[name="base_url"]').value = '';
    }
    provDriver.addEventListener('change', applyProvDriver);
    applyProvDriver();

    document.getElementById('provider-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var f = e.target;
        var msg = document.getElementById('provider-msg');
        // PENTING: akses field lewat elements.namedItem() — akses langsung
        // "f.name" bertabrakan dengan properti bawaan HTMLFormElement (form.name,
        // selalu string kosong) sehingga f.name.value = undefined -> TypeError
        // -> tombol "Simpan Provider" tidak pernah mengirim apa pun.
        var el = function (n) { return f.elements.namedItem(n); };
        var val = function (n) { var x = el(n); return x && x.value != null ? String(x.value).trim() : ''; };
        var body = {
            name: val('name'),
            driver: el('driver') ? el('driver').value : 'openai-compatible',
            base_url: val('base_url') || null,
            api_key: val('api_key') || null,
            is_default: !!(el('is_default') && el('is_default').checked)
        };
        if (!body.name) { msg.textContent = '✗ Nama provider wajib diisi.'; return; }
        if (body.driver === 'openai-compatible' && !body.base_url) {
            msg.textContent = '✗ Base URL wajib diisi untuk driver openai-compatible (mis. https://instance-router.up.railway.app/v1).';
            return;
        }
        fetch('/api/ai/providers', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify(body)
        }).then(function (r) {
            if (r.ok) {
                msg.textContent = '✔ Provider & API Key tersimpan.';
                f.reset();
                applyProvDriver();
                loadProviders();
                setTimeout(function () { provModal.classList.remove('open'); msg.textContent = ''; }, 900);
                kuroThinking(false, 'Provider baru ditambahkan ✨');
            } else {
                r.json().then(function (j) {
                    var m = j.message || 'Gagal menyimpan provider.';
                    if (m === 'Server Error') {
                        m = 'Server error — kemungkinan tabel AI belum dibuat. Jalankan "php artisan migrate --force" di Railway (Command Shell), lalu simpan lagi.';
                    }
                    msg.textContent = '✗ ' + m;
                }).catch(function () { msg.textContent = '✗ Gagal menyimpan provider.'; });
            }
        }).catch(function (err) {
            msg.textContent = '✗ Gagal menghubungi server: ' + (err && err.message ? err.message : 'periksa koneksi lalu coba lagi.');
        });
    });

    // Menu provider di composer
    var provMenu = document.getElementById('cp-prov-menu');
    document.getElementById('cp-prov-btn').addEventListener('click', function (e) {
        e.stopPropagation();
        provMenu.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
        if (!provMenu.contains(e.target) && !document.getElementById('cp-prov-btn').contains(e.target)) {
            provMenu.classList.remove('open');
        }
    });

    // ---------- Dropdown model (sebelah dropdown provider) ----------
    var modelMenu = document.getElementById('cp-model-menu');
    var modelBtn = document.getElementById('cp-model-btn');
    var modelCache = {}; // slug provider -> {ok, models, error}

    // Pilihan model tersimpan per browser — dipakai ulang bila masih ada di provider aktif.
    try { currentModel = localStorage.getItem('kuro_model') || null; } catch (e) {}

    function saveModelChoice() {
        try { localStorage.setItem('kuro_model', currentModel || ''); } catch (e) {}
    }

    function shortModel(id) {
        return String(id || '').replace(/^models\//, '');
    }

    function updateModelName() {
        var el = document.getElementById('cp-model-name');
        if (!el) return;
        el.textContent = currentModel ? shortModel(currentModel) : 'Otomatis (semua AI)';
        el.title = currentModel ? currentModel : 'Kalau model error / 503 / pensiun, otomatis ganti model & AI lain sampai jalan';
    }

    // ---------- Katalog model: akses, spesifikasi & kemampuan (ala katalog) ----------
    // Kemampuan diambil dari heuristik nama model (katalog lokal di browser);
    // label gratis/berbayar + harga dari data harga router (server).
    var modelDetailId = null; // id model yang sedang dibuka detailnya (null = daftar)

    function modelMeta(id) {
        var s = String(id || '').toLowerCase();
        function has() {
            for (var i = 0; i < arguments.length; i++) {
                if (s.indexOf(String(arguments[i]).toLowerCase()) !== -1) return true;
            }
            return false;
        }
        var meta = {
            family: null,
            desc: 'Model chat serbaguna yang tersedia lewat Max Router.',
            context: null,
            maxOut: null,
            input: 'Teks',
            output: 'Teks',
            caps: ['Streaming', 'Tool calling', 'JSON mode'],
            params: ['max_tokens', 'temperature', 'top_p', 'stop', 'frequency_penalty', 'presence_penalty', 'stream', 'tools', 'tool_choice']
        };
        if (has('gemini')) {
            meta.family = 'Google Gemini';
            meta.desc = 'Model multimodal Google — memahami teks, gambar, audio & video; cepat dan serbaguna.';
            meta.context = has('1.5') ? '2M' : '1M';
            meta.maxOut = '65K';
            meta.input = 'Teks, Gambar, Audio, Video';
            meta.caps = ['Vision', 'Video input', 'Tool calling', 'JSON mode', 'Streaming', 'Long context'];
            if (has('2.5', '3')) meta.caps = meta.caps.concat(['Reasoning']);
        } else if (has('gpt-4o', 'gpt-4.1', 'gpt-5', 'chatgpt')) {
            meta.family = 'OpenAI';
            meta.desc = 'Model unggulan OpenAI — cerdas, cepat, dan mendukung input gambar.';
            meta.context = '128K';
            meta.maxOut = has('gpt-5') ? '128K' : '16K';
            meta.input = 'Teks, Gambar';
            meta.caps = ['Vision', 'Tool calling', 'JSON mode', 'Streaming'];
            if (has('gpt-5')) meta.caps = meta.caps.concat(['Reasoning']);
        } else if (has('o1', 'o3', 'o4-mini')) {
            meta.family = 'OpenAI';
            meta.desc = 'Model reasoning OpenAI — berpikir langkah demi langkah sebelum menjawab.';
            meta.context = '128K';
            meta.maxOut = '100K';
            meta.input = 'Teks, Gambar';
            meta.caps = ['Reasoning', 'Streaming', 'JSON mode', 'Tool calling'];
        } else if (has('claude')) {
            meta.family = 'Anthropic';
            meta.desc = 'Model Claude — kuat untuk analisis, penulisan panjang, dan penggunaan tools.';
            meta.context = '200K';
            meta.maxOut = '64K';
            meta.input = 'Teks, Gambar';
            meta.caps = ['Vision', 'Tool calling', 'JSON mode', 'Streaming', 'Long context'];
        } else if (has('qwen')) {
            meta.family = 'Alibaba Qwen';
            meta.desc = 'Model Qwen dari Alibaba — multibahasa (termasuk Indonesia) dengan dukungan berpikir.';
            meta.context = has('omni', '3.8', 'long') ? '1M' : '128K';
            meta.maxOut = '65K';
            if (has('vl', 'vision', 'omni')) {
                meta.input = 'Teks, Gambar' + (has('omni') ? ', Video' : '');
                meta.caps = meta.caps.concat(['Vision']);
            }
            if (has('thinking', 'think', 'r1', 'omni', '3.8')) meta.caps = meta.caps.concat(['Reasoning']);
            meta.caps = meta.caps.concat(['Long context']);
        } else if (has('deepseek')) {
            meta.family = 'DeepSeek';
            meta.desc = 'Model DeepSeek — efisien dan kuat untuk coding serta penalaran.';
            meta.context = '128K';
            meta.maxOut = '8K';
            if (has('r1', 'think', 'reason')) meta.caps = meta.caps.concat(['Reasoning']);
        } else if (has('llama')) {
            meta.family = 'Meta Llama';
            meta.desc = 'Model open-weight Meta Llama — cepat dan hemat untuk tugas sehari-hari.';
            meta.context = '128K';
            meta.maxOut = '8K';
        } else if (has('mistral', 'mixtral', 'codestral')) {
            meta.family = 'Mistral';
            meta.desc = 'Model Mistral — ringan, cepat, dan efisien.';
            meta.context = has('large') ? '128K' : '32K';
            meta.maxOut = '8K';
        } else if (has('grok')) {
            meta.family = 'xAI Grok';
            meta.desc = 'Model Grok dari xAI — tajam untuk penalaran dan topik hangat.';
            meta.context = '128K';
            meta.maxOut = '32K';
            meta.caps = meta.caps.concat(['Vision', 'Reasoning']);
        } else if (has('kimi', 'moonshot', 'glm', 'ernie')) {
            meta.family = has('kimi', 'moonshot') ? 'Moonshot Kimi' : (has('glm') ? 'Zhipu GLM' : 'Baidu Ernie');
            meta.desc = 'Model dengan konteks sangat panjang — cocok untuk dokumen besar.';
            meta.context = '128K+';
            meta.maxOut = '16K';
            meta.caps = meta.caps.concat(['Long context', 'Reasoning']);
        }
        return meta;
    }

    function tierBadge(t) {
        var cls = t === 'free' ? 'badge-free' : (t === 'paid' ? 'badge-paid' : 'badge-unknown');
        var lbl = t === 'free' ? 'GRATIS' : (t === 'paid' ? 'BERBAYAR' : '?');
        return '<span class="badge ' + cls + '">' + lbl + '</span>';
    }

    // "Aksesmu" ala katalog: gratis berbagi jatah token harian / berbayar per 1jt token.
    function accessLine(m) {
        if (m.tier === 'free') {
            return 'Gratis — tanpa biaya. Model gratis dipakai banyak orang sekaligus, jadi kecepatan & stabilitas bisa berubah di jam sibuk.';
        }
        if (m.price) {
            return 'Berbayar — $' + m.price.input + ' input / $' + m.price.output + ' output per 1 juta token.';
        }
        return 'Belum diketahui — buka "Sinkron Router & Mode AI" lalu tekan "Sinkron Sekarang" untuk memperbarui label gratis/berbayar.';
    }

    function renderModelDetail(m) {
        var meta = modelMeta(m.id);
        var wrap = document.createElement('div');
        wrap.className = 'mm-detail';
        var capsHtml = '';
        meta.caps.forEach(function (c) { capsHtml += '<span class="mm-chip">' + esc(c) + '</span>'; });
        var paramsHtml = '';
        meta.params.forEach(function (p) { paramsHtml += '<code>' + esc(p) + '</code>'; });
        wrap.innerHTML =
            '<button type="button" class="mm-back"><i class="fa-solid fa-arrow-left"></i> Kembali ke daftar model</button>' +
            '<div class="mm-name">' + esc(shortModel(m.id)) + ' ' + tierBadge(m.tier || 'unknown') + '</div>' +
            (meta.family ? '<div class="mm-family"><i class="fa-solid fa-microchip"></i> ' + esc(meta.family) + '</div>' : '') +
            '<p class="mm-desc">' + esc(meta.desc) + '</p>' +
            '<div class="mm-sec"><h5>Aksesmu</h5><p class="mm-access t-' + esc(m.tier || 'unknown') + '">' + esc(accessLine(m)) + '</p></div>' +
            '<div class="mm-sec"><h5>Spesifikasi</h5><div class="mm-spec">' +
            '<div><small>Context window</small><b>' + esc(meta.context || '—') + '</b></div>' +
            '<div><small>Max output</small><b>' + esc(meta.maxOut || '—') + '</b></div>' +
            '<div><small>Input</small><b>' + esc(meta.input) + '</b></div>' +
            '<div><small>Output</small><b>' + esc(meta.output) + '</b></div>' +
            '</div></div>' +
            '<div class="mm-sec"><h5>Kemampuan</h5><div class="mm-caps">' + capsHtml + '</div></div>' +
            '<div class="mm-sec"><h5>Parameter yang didukung</h5><div class="mm-params">' + paramsHtml + '</div></div>' +
            '<button type="button" class="btn small mm-use"><i class="fa-solid fa-check"></i> Pakai model ini</button>';
        wrap.querySelector('.mm-back').addEventListener('click', function () {
            modelDetailId = null;
            renderModelMenu();
        });
        wrap.querySelector('.mm-use').addEventListener('click', function () {
            currentModel = m.id;
            saveModelChoice();
            updateModelName();
            modelDetailId = null;
            modelMenu.classList.remove('open');
            renderModelMenu();
            kuroToast('Model aktif: ' + shortModel(m.id), 'ok');
        });
        return wrap;
    }

    function renderModelMenu() {
        if (!modelMenu) return;
        modelMenu.innerHTML = '';
        var provName = 'provider ini';
        providers.forEach(function (p) { if (p.slug === currentProvider) provName = p.name; });

        // Sedang membuka detail satu model -> tampilkan kartu detail, bukan daftar.
        if (modelDetailId) {
            var detailModel = null;
            var cacheAll = modelCache[currentProvider];
            if (cacheAll && cacheAll.models) {
                cacheAll.models.forEach(function (m) { if (m.id === modelDetailId) detailModel = m; });
            }
            if (detailModel) {
                modelMenu.appendChild(renderModelDetail(detailModel));
                return;
            }
            modelDetailId = null; // model sudah tidak ada -> kembali ke daftar
        }

        var head = document.createElement('div');
        head.className = 'cp-model-head';
        head.innerHTML = 'Model di ' + esc(provName) +
            ' <button type="button" class="mm-reload" title="Muat ulang daftar model dari ' + esc(provName) + '"><i class="fa-solid fa-rotate"></i></button>';
        head.querySelector('.mm-reload').addEventListener('click', function (e) {
            e.stopPropagation();
            loadProviderModels(currentProvider, true);
        });
        modelMenu.appendChild(head);

        // Opsi pertama: ikuti model default provider (tanpa override)
        var auto = document.createElement('button');
        auto.type = 'button';
        auto.className = 'cp-prov-item';
        auto.innerHTML =
            '<i class="fa-solid fa-check chk" style="visibility:' + (currentModel ? 'hidden' : 'visible') + '"></i>' +
            '<span class="grow">Otomatis<small>Default provider — kalau error otomatis cari model & AI lain sampai jalan</small></span>';
        auto.addEventListener('click', function () {
            currentModel = null;
            saveModelChoice();
            updateModelName();
            modelMenu.classList.remove('open');
            renderModelMenu();
        });
        modelMenu.appendChild(auto);

        var cache = modelCache[currentProvider];
        var models = (cache && cache.ok ? (cache.models || []) : []).filter(function (m) { return chatCapable(m.id); });
        if (!models.length) {
            var empty = document.createElement('p');
            empty.className = 'cp-model-empty';
            empty.textContent = cache && cache.error ? '✗ ' + cache.error : 'Memuat daftar model…';
            modelMenu.appendChild(empty);
            var retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'btn small ghost mm-retry-btn';
            retry.innerHTML = '<i class="fa-solid fa-rotate"></i> Muat ulang daftar model';
            retry.addEventListener('click', function (e) {
                e.stopPropagation();
                loadProviderModels(currentProvider, true);
            });
            modelMenu.appendChild(retry);
            return;
        }
        models.forEach(function (m) {
            var t = m.tier || 'unknown';
            var row = document.createElement('div');
            row.className = 'cp-model-row';
            var main = document.createElement('button');
            main.type = 'button';
            main.className = 'cp-prov-item mm-main';
            if (m.note) main.title = m.note;
            main.innerHTML =
                '<i class="fa-solid fa-check chk" style="visibility:' + (m.id === currentModel ? 'visible' : 'hidden') + '"></i>' +
                '<span class="grow">' + esc(shortModel(m.id)) +
                '<small>' + esc(m.name || modelMeta(m.id).family || '') +
                (m.price ? ' · $' + m.price.input + ' / $' + m.price.output + ' per 1jt token' : '') +
                '</small></span>' + tierBadge(t);
            main.addEventListener('click', function () {
                currentModel = m.id;
                saveModelChoice();
                updateModelName();
                modelMenu.classList.remove('open');
                renderModelMenu();
                kuroToast('Model aktif: ' + shortModel(m.id), 'ok');
            });
            var info = document.createElement('button');
            info.type = 'button';
            info.className = 'mm-info';
            info.title = 'Detail model — akses, spesifikasi, kemampuan';
            info.innerHTML = '<i class="fa-solid fa-circle-info"></i>';
            info.addEventListener('click', function (e) {
                e.stopPropagation();
                modelDetailId = m.id;
                renderModelMenu();
                modelMenu.scrollTop = 0;
            });
            row.appendChild(main);
            row.appendChild(info);
            modelMenu.appendChild(row);
        });
    }

    function loadProviderModels(slug, force) {
        if (!slug) return;
        updateModelName();
        if (!force && modelCache[slug] && modelCache[slug].ok) {
            renderModelMenu();
            return;
        }
        modelCache[slug] = { ok: false, models: [], error: null };
        renderModelMenu();
        fetch('/api/ai/models/' + encodeURIComponent(slug), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (currentProvider !== slug) return; // provider sudah diganti di tengah jalan
                modelCache[slug] = data && data.ok
                    ? data
                    : { ok: false, models: [], error: (data && data.error) || 'Gagal memuat daftar model.' };
                // Pilihan tersimpan tidak ada di daftar model provider ini -> pakai default.
                if (currentModel) {
                    var found = (modelCache[slug].models || []).some(function (m) { return m.id === currentModel; });
                    if (!found) { currentModel = null; saveModelChoice(); }
                }
                updateModelName();
                renderModelMenu();
            })
            .catch(function () {
                if (currentProvider !== slug) return;
                modelCache[slug] = { ok: false, models: [], error: 'Gagal menghubungi server.' };
                renderModelMenu();
            });
    }

    if (modelBtn) {
        modelBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            modelMenu.classList.toggle('open');
            renderModelMenu();
        });
    }
    document.addEventListener('click', function (e) {
        if (modelMenu && !modelMenu.contains(e.target) && modelBtn && !modelBtn.contains(e.target)) {
            modelMenu.classList.remove('open');
        }
    });

    // ---------- Chat ----------
    var history = [];

    function renderChat() {
        chatLog.innerHTML = '';
        if (!history.length) {
            var empty = document.createElement('div');
            empty.className = 'chat-empty';
            empty.innerHTML = 'Halo! Saya <b>Kuro</b> 🐾 teman bincangmu.<br>' +
                'Tulis pesan di bawah, atau tekan ikon mic untuk mendikte dengan suara.';
            chatLog.appendChild(empty);
            return;
        }
        history.forEach(function (m) {
            var div = document.createElement('div');
            div.className = 'msg ' + (m.role === 'user' ? 'user' : 'ai');
            if (m.type === 'image' && m.image) {

                if (m.content) {
                    var cap = document.createElement('div');
                    cap.className = 'msg-cap';
                    cap.textContent = m.content;
                    div.appendChild(cap);
                }
                var img = document.createElement('img');
                img.className = 'msg-media';
                img.src = m.image;
                img.alt = m.content || 'Gambar hasil AI';
                div.appendChild(img);
            } else if (m.type === 'video' && m.video) {
                var cap2 = document.createElement('div');
                cap2.className = 'msg-cap';
                cap2.textContent = '🎬 ' + (m.content || '');
                div.appendChild(cap2);
                var vid = document.createElement('video');
                vid.className = 'msg-media';
                vid.src = m.video;
                vid.controls = true;
                div.appendChild(vid);
            } else {
                var txt = document.createElement('div');
                txt.className = 'msg-txt';
                txt.textContent = m.content;
                div.appendChild(txt);
                // Tombol "Dengarkan" — Kuro membacakan jawaban ini (fitur AI bicara).
                if (m.role === 'assistant' && m.content) {
                    var acts = document.createElement('div');
                    acts.className = 'msg-acts';
                    var sp = document.createElement('button');
                    sp.type = 'button';
                    sp.className = 'btn small ghost msg-speak';
                    sp.title = 'Kuro membacakan jawaban ini';
                    sp.innerHTML = '<i class="fa-solid fa-volume-high"></i> Dengarkan';
                    sp.addEventListener('click', function () { speakMessage(m.content, sp); });
                    acts.appendChild(sp);
                    div.appendChild(acts);
                }
            }
            chatLog.appendChild(div);
        });
        chatLog.scrollTop = chatLog.scrollHeight;
    }

    // Model non-teks (TTS / audio / embedding / image / video) tidak bisa
    // dipakai untuk chat & analisis — saring dari semua daftar model.
    function chatCapable(id) {
        return !/tts|embed|imagen|image|veo|aqa|audio|live/i.test(String(id || ''));
    }

    // ---------- Indikator "sedang mengetik" (titik 3 animasi) ----------
    function showTyping() {
        hideTyping();
        var div = document.createElement('div');
        div.className = 'msg ai typing';
        div.innerHTML = '<span class="dot"></span><span class="dot"></span><span class="dot"></span>';
        chatLog.appendChild(div);
        chatLog.scrollTop = chatLog.scrollHeight;
    }

    function hideTyping() {
        var t = chatLog.querySelector('.msg.typing');
        if (t && t.parentNode) t.parentNode.removeChild(t);
    }

    function saveCurrent() {
        try {
            localStorage.setItem('kuro_current', JSON.stringify({
                provider: currentProvider,
                messages: history
            }));
        } catch (e) {}

        // Simpan juga ke daftar percakapan di sidebar (auto-save tiap AI menjawab).
        upsertConv();
    }

    function restoreCurrent() {
        try {
            var raw = localStorage.getItem('kuro_current');
            if (!raw) return;
            var data = JSON.parse(raw);
            if (data.messages && data.messages.length) {
                history = data.messages;
                renderChat();
                setMode('chat');
            }
        } catch (e) {}
    }

    function sendChat() {
        var text = chatInput.value.trim();
        if (!text) return;
        var btn = document.getElementById('chat-send');
        var status = document.getElementById('chat-status');
        if (genMode === 'image') {
            chatInput.value = '';
            autoGrow();
            setMode('chat');
            generateImage(text);
            return;
        }
        if (genMode === 'video') {
            chatInput.value = '';
            autoGrow();
            setMode('chat');
            generateVideo(text);
            return;
        }
        history.push({role: 'user', content: text});
        renderChat();
        chatInput.value = '';
        autoGrow();
        setMode('chat');
        btn.disabled = true;
        status.textContent = 'Menunggu jawaban AI…';
        kuroThinking(true, 'Kuro sedang menganalisis… 🔍');
        showTyping(); // titik 3 — Kuro sedang mengetik

        fetch('/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF
            },
            body: JSON.stringify({
                message: text,
                provider: currentProvider,
                model: currentModel, // model dari dropdown model (null = default provider)
                history: history.slice(0, -1).slice(-20),
                // Dokumen terbuka + chip aktif -> AI membaca isi dokumen ini.
                document_id: (MODE === 'doc' && currentDocId && docLinkOn) ? currentDocId : null,
                concise: conciseOn // ⚡ jawaban singkat & jelas bila toggle aktif
            })
        }).then(function (r) {
            if (r.status === 419) throw new Error('Sesi sudah berakhir (server baru di-deploy ulang). Muat ulang halaman (tekan F5 / Ctrl+F5), masuk lagi bila diminta, lalu kirim ulang pesanmu.');
            if (!r.ok) {
                return r.json().catch(function () { return null; }).then(function (j) {
                    throw new Error((j && (j.message || j.error)) || ('Server menolak permintaan (HTTP ' + r.status + ').'));
                });
            }
            return r.json();
        }).then(function (data) {
            hideTyping();
            if (data.ok && data.content) {
                history.push({role: 'assistant', content: data.content});
                renderChat();
                status.textContent = '✔ ' + data.provider + ' · ' + data.model;
                kuroThinking(false, 'Selesai! Ada lagi? ✨');
                speak(data.content);
                saveCurrent();
            } else if (data.ok) {
                // Respons kosong — model non-teks (mis. TTS/audio) tidak bisa chat.
                renderChat();
                status.textContent = '';
                kuroAlert('Model "' + shortModel(data.model) + '" tidak menghasilkan teks — pilih model chat (bukan TTS/audio) di dropdown model.');
            } else {
                history.pop();
                renderChat();
                status.textContent = '';
                kuroThinking(false, 'Ada kendala 😿');
                                var errMsg = data.error || 'Gagal.';
                if (/no active credentials|model_not_found|model_not_exists|not_found/i.test(errMsg)) {
                    errMsg += '\n\nModel yang dipakai tidak aktif di router (tidak ada kredensial untuk provider model tersebut). Buka dropdown model di samping dropdown provider, lalu pilih model lain yang tersedia — atau tekan ikon 🔄 di dropdown untuk memuat ulang daftar model.';
                }
                kuroAlert(errMsg);
            }
        }).catch(function (e) {
            hideTyping();
            history.pop();
            renderChat();
            status.textContent = '';
            kuroThinking(false, 'Ada kendala 😿');
            kuroAlert(e.message);
        }).finally(function () { btn.disabled = false; chatInput.focus(); });
    }

    document.getElementById('chat-send').addEventListener('click', sendChat);
    chatInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            sendChat();
        }
    });
    function autoGrow() {
        chatInput.style.height = 'auto';
        chatInput.style.height = Math.min(chatInput.scrollHeight, 190) + 'px';
    }
    chatInput.addEventListener('input', autoGrow);

    // ---------- Generate gambar & video (Gemini image / Veo) ----------
    function generateImage(prompt) {
        var status = document.getElementById('chat-status');
        var btn = document.getElementById('chat-send');
        history.push({role: 'user', content: '🎨 ' + prompt});
        renderChat();
        setMode('chat');
        btn.disabled = true;
        status.textContent = 'Membuat gambar… (±10–30 detik)';
        showTyping();
        fetch('/api/ai/generate/image', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify({prompt: prompt})
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok) {
                hideTyping();
                history.push({role: 'assistant', type: 'image', content: data.text || prompt, image: data.image});
                renderChat();
                status.textContent = '✔ Gambar dibuat · ' + data.model;
                saveCurrent();
            } else {
                hideTyping();
                status.textContent = '';
                kuroAlert(data.error || 'Gagal membuat gambar.');
            }
        }).catch(function (e) {
            hideTyping();
            status.textContent = '';
            kuroAlert(e.message);
        }).finally(function () {
            btn.disabled = false;
            setGenMode(null);
            chatInput.focus();
        });
    }

    function generateVideo(prompt) {
        var status = document.getElementById('chat-status');
        var btn = document.getElementById('chat-send');
        history.push({role: 'user', content: '🎬 ' + prompt});
        renderChat();
        setMode('chat');
        btn.disabled = true;
        status.textContent = 'Membuat video… (±1–2 menit)';
        showTyping();
        fetch('/api/ai/generate/video', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify({prompt: prompt})
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.ok) {
                hideTyping();
                status.textContent = '';
                kuroAlert(data.error || 'Gagal membuat video.');
                btn.disabled = false;
                setGenMode(null);
                return;
            }
            var op = data.operation;
            var tries = 0;
            var timer = setInterval(function () {
                tries++;
                fetch('/api/ai/generate/video/status?operation=' + encodeURIComponent(op), {headers: {'Accept': 'application/json'}})
                    .then(function (r) { return r.json(); })
                    .then(function (s) {
                        if (s.ok && s.done && s.uri) {
                            clearInterval(timer);
                            hideTyping();
                            history.push({role: 'assistant', type: 'video', content: prompt, video: s.uri});
                            renderChat();
                            status.textContent = '✔ Video siap · Veo';
                            saveCurrent();
                            btn.disabled = false;
                            setGenMode(null);
                        } else if (!s.ok) {
                            clearInterval(timer);
                            hideTyping();
                            status.textContent = '';
                            kuroAlert(s.error || 'Gagal membuat video.');
                            btn.disabled = false;
                            setGenMode(null);
                        } else {
                            status.textContent = 'Membuat video… (' + (tries * 8) + ' detik)';
                        }
                    })
                    .catch(function () {});
                if (tries >= 45) {
                    clearInterval(timer);
                    hideTyping();
                    status.textContent = '';
                    kuroAlert('Video terlalu lama dibuat — coba lagi nanti.');
                    btn.disabled = false;
                    setGenMode(null);
                }
            }, 8000);
        }).catch(function (e) {
            hideTyping();
            status.textContent = '';
            kuroAlert(e.message);
            btn.disabled = false;
            setGenMode(null);
        });
    }

    document.getElementById('btn-new-chat').addEventListener('click', function () {
        history = [];
        currentConvId = null;
        try { localStorage.removeItem('kuro_conv_id'); } catch (e) {}
        renderSideConvs();
        renderChat();
        saveCurrent();
        currentDocId = null;
        setMode('landing');
        chatInput.focus();
        kuroThinking(false, 'Percakapan baru dimulai ✨');
        if (isMobile()) shell.classList.remove('sb-open-m');
    });

    // ---------- Dikte suara ----------
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    var listening = false;
    var rec = null;

    document.getElementById('btn-mic').addEventListener('click', function () {
        var micBtn = document.getElementById('btn-mic');
        if (!SR) { kuroAlert('Browser belum mendukung input suara — coba Chrome/Edge terbaru.'); return; }
        if (listening) { rec.stop(); return; }
        rec = new SR();
        rec.lang = 'id-ID';
        rec.interimResults = true;
        rec.continuous = false;
        listening = true;
        micBtn.innerHTML = STOP_HTML;
        micBtn.classList.add('rec');
        kuroThinking(true, 'Kuro mendengarkan… 🎙️');
        rec.onresult = function (e) {
            var t = '';
            for (var i = e.resultIndex; i < e.results.length; i++) t += e.results[i][0].transcript;
            chatInput.value = t;
            autoGrow();
        };
        rec.onend = function () {
            listening = false;
            micBtn.innerHTML = MIC_HTML;
            micBtn.classList.remove('rec');
            kuroThinking(false, 'Dikte siap — tekan kirim ✨');
        };
        rec.onerror = function (e) {
            listening = false;
            micBtn.innerHTML = MIC_HTML;
            micBtn.classList.remove('rec');
            kuroThinking(false, 'Suara gagal 😿');
            kuroAlert('Error suara: ' + e.error);
        };
        rec.start();
    });

    // ---------- Percakapan: daftar di sidebar + riwayat tersimpan ----------
    // Percakapan otomatis tersimpan ke DATABASE (tabel conversations +
    // messages) begitu AI menjawab — daftarnya tampil di sidebar (ala
    // ChatGPT/Gemini). localStorage dipakai sebagai cache, jadi sidebar
    // tetap terisi saat server sebentar tidak terjangkau.
    function getConvs() {
        try { return JSON.parse(localStorage.getItem('kuro_convs')) || []; }
        catch (e) { return []; }
    }
    function setConvs(list) {
        try { localStorage.setItem('kuro_convs', JSON.stringify((list || []).slice(0, 60))); } catch (e) {}
    }
    function convTitle(msgs) {
        var t = '';
        (msgs || []).forEach(function (m) {
            if (!t && m.role === 'user' && m.content) t = m.content;
        });
        return (t || 'Percakapan').replace(/\s+/g, ' ').trim().slice(0, 60);
    }
    var currentConvId = null;
    try { currentConvId = localStorage.getItem('kuro_conv_id') || null; } catch (e) {}

    // Impor sekali: riwayat lama (kuro_saved) masuk ke daftar percakapan baru.
    (function importLegacySaved() {
        try {
            var legacy = JSON.parse(localStorage.getItem('kuro_saved') || '[]');
            if (!legacy.length) return;
            var convs = getConvs();
            legacy.forEach(function (item, i) {
                convs.push({
                    id: 'legacy-' + i + '-' + Date.now(),
                    title: item.title || convTitle(item.messages),
                    time: item.time || '',
                    provider: item.provider || null,
                    messages: item.messages || []
                });
            });
            setConvs(convs);
            localStorage.removeItem('kuro_saved');
        } catch (e) {}
    })();

    // Tarik daftar percakapan dari server (database) lalu perbarui cache lokal.
    function fetchConvs() {
        fetch('/convs', {headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.ok) return;
                var local = getConvs();
                var localOnly = local.filter(function (c) { return !c.sid; });
                var server = (data.conversations || []).map(function (c) {
                    var cached = null;
                    local.forEach(function (x) { if (x.sid === c.id) cached = x; });
                    return {
                        id: 's' + c.id,
                        sid: c.id,
                        title: c.title || 'Percakapan',
                        time: c.time || '',
                        provider: c.provider || null,
                        count: c.msg_count || 0,
                        messages: cached ? cached.messages : null
                    };
                });
                setConvs(localOnly.concat(server));
                renderSideConvs();
            })
            .catch(function () {});
    }

    // Simpan / perbarui percakapan aktif (dipanggil otomatis tiap AI menjawab).
    function upsertConv(customTitle) {
        if (!history.length) return;
        var hasUser = false;
        history.forEach(function (m) { if (m.role === 'user') hasUser = true; });
        if (!hasUser) return;
        var convs = getConvs();
        var item = null;
        if (currentConvId) {
            convs.forEach(function (c) { if (c.id === currentConvId) item = c; });
        }
        if (!item) {
            item = { id: 'c' + Date.now() + '-' + Math.floor(Math.random() * 9999), sid: null, title: '', time: '', provider: null, messages: [] };
            convs.unshift(item);
        }
        if (customTitle) item.title = customTitle;
        if (!item.title) item.title = convTitle(history);
        item.time = new Date().toLocaleString('id-ID');
        item.provider = currentProvider;
        item.messages = history.slice();
        currentConvId = item.id;
        try { localStorage.setItem('kuro_conv_id', currentConvId); } catch (e) {}
        setConvs(convs);
        renderSideConvs();

        // Kirim ke database (server). sid (id server) disimpan supaya update
        // berikutnya menimpa baris yang sama, bukan menambah baris baru.
        var convId = item.id;
        fetch('/convs', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify({
                id: item.sid || null,
                title: item.title,
                provider: item.provider,
                messages: (item.messages || []).map(function (m) {
                    return {
                        role: m.role,
                        content: m.content || '',
                        type: m.type || null,
                        image: m.image || null,
                        video: m.video || null
                    };
                })
            })
        }).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
            if (!data || !data.ok || !data.id) return;
            var convs2 = getConvs();
            convs2.forEach(function (c) { if (c.id === convId && !c.sid) c.sid = data.id; });
            setConvs(convs2);
        }).catch(function () {});
    }

    function renderSideConvs() {
        var box = document.getElementById('side-convs');
        if (!box) return;
        var convs = getConvs();
        box.innerHTML = '';
        if (!convs.length) {
            box.innerHTML = '<p class="side-conv-empty">Belum ada percakapan — mulai chat, otomatis muncul di sini.</p>';
            return;
        }
        convs.forEach(function (c) {
            var div = document.createElement('button');
            div.type = 'button';
            div.className = 'side-conv' + (c.id === currentConvId ? ' active' : '');
            div.title = c.title || 'Percakapan';
            div.innerHTML =
                '<span class="ic"><i class="fa-solid fa-comment-dots"></i></span>' +
                '<span class="lbl">' + esc(c.title || 'Percakapan') + '</span>' +
                '<span class="side-conv-del" title="Hapus percakapan"><i class="fa-solid fa-trash-can"></i></span>';
            div.addEventListener('click', function (e) {
                if (e.target && e.target.closest && e.target.closest('.side-conv-del')) return;
                loadConv(c.id);
            });
            div.querySelector('.side-conv-del').addEventListener('click', function (e) {
                e.stopPropagation();
                deleteConv(c.id);
            });
            box.appendChild(div);
        });
    }

    function loadConv(id) {
        var item = null;
        getConvs().forEach(function (c) { if (c.id === id) item = c; });
        if (!item) return;
        currentConvId = id;
        try { localStorage.setItem('kuro_conv_id', id); } catch (e) {}
        renderSideConvs();

        var apply = function (msgs) {
            history = (msgs || []).slice();
            renderChat();
            saveCurrent();
            setMode('chat');
            renderSideConvs();
            if (isMobile()) shell.classList.remove('sb-open-m');
            kuroThinking(false, 'Percakapan dimuat kembali ✨');
        };

        // Sudah ada di cache lokal -> tampilkan langsung.
        if (item.messages && item.messages.length) { apply(item.messages); return; }

        // Belum ada isinya -> ambil dari database (server).
        if (item.sid) {
            fetch('/convs/' + item.sid, {headers: {'Accept': 'application/json'}})
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    var msgs = (data && data.ok && data.conversation) ? data.conversation.messages : [];
                    var convs = getConvs();
                    convs.forEach(function (c) { if (c.id === id) c.messages = msgs; });
                    setConvs(convs);
                    apply(msgs);
                })
            return;
        }
        apply(item.messages || []);
    }

    function deleteConv(id) {
        var sid = null;
        getConvs().forEach(function (c) { if (c.id === id) sid = c.sid || null; });
        setConvs(getConvs().filter(function (c) { return c.id !== id; }));
        if (currentConvId === id) {
            currentConvId = null;
            try { localStorage.removeItem('kuro_conv_id'); } catch (e) {}
        }
        if (sid) {
            fetch('/convs/' + sid, {
                method: 'DELETE',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF}
            }).then(function () { fetchConvs(); }).catch(function () {});
        }
        renderSideConvs();
        renderHistoryModal();
    }

    // Tombol 💾 — simpan percakapan dengan judul sendiri
    document.getElementById('btn-save-chat').addEventListener('click', function () {
        if (!history.length) { kuroAlert('Belum ada percakapan untuk disimpan.', 'info'); return; }
        var firstUser = '';
        history.forEach(function (m) { if (!firstUser && m.role === 'user') firstUser = m.content; });
        kuroPrompt('Simpan percakapan', (firstUser || 'Percakapan').slice(0, 40), function (title) {
            upsertConv(title);
            kuroThinking(false, 'Percakapan tersimpan 💾');
        });
    });

    // Modal "Riwayat tersimpan" (daftar yang sama dengan sidebar)
    function renderHistoryModal() {
        var box = document.getElementById('hist-list');
        var convs = getConvs();
        box.innerHTML = '';
        if (!convs.length) {
            box.innerHTML = '<p class="empty">Belum ada percakapan tersimpan.</p>';
            return;
        }
        convs.forEach(function (item) {
            var cnt = item.messages ? item.messages.length : (item.count || 0);
            var div = document.createElement('div');
            div.className = 'hist-item';
            div.innerHTML =
                '<div class="grow"><b>' + esc(item.title || 'Percakapan') + '</b>' +
                '<small>' + esc(item.time || '') + ' · ' + cnt + ' pesan</small></div>' +
                '<button class="btn small hist-load"><i class="fa-solid fa-rotate-right"></i> Muat</button>' +
                '<button class="btn small ghost hist-del"><i class="fa-solid fa-trash-can"></i></button>';
            div.querySelector('.hist-load').addEventListener('click', function () {
                loadConv(item.id);
                document.getElementById('hist-modal').classList.remove('open');
            });
            div.querySelector('.hist-del').addEventListener('click', function () {
                deleteConv(item.id);
            });
            box.appendChild(div);
        });
    }

    document.getElementById('btn-history').addEventListener('click', function () {
        renderHistoryModal();
        document.getElementById('hist-modal').classList.add('open');
        if (isMobile()) shell.classList.remove('sb-open-m');
    });
    document.getElementById('btn-hist-close').addEventListener('click', function () {
        document.getElementById('hist-modal').classList.remove('open');
    });
    document.getElementById('hist-modal').addEventListener('click', function (e) {
        if (e.target === this) this.classList.remove('open');
    });
    document.getElementById('btn-hist-clear').addEventListener('click', function () {
        if (confirm('Hapus semua percakapan tersimpan?')) {
            setConvs([]);
            currentConvId = null;
            try { localStorage.removeItem('kuro_conv_id'); } catch (e) {}
            renderSideConvs();
            renderHistoryModal();
            fetch('/convs', {
                method: 'DELETE',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF}
            }).then(function () { fetchConvs(); }).catch(function () {});
        }
    });

    // ---------- Dokumen ----------
    var docModal = document.getElementById('doc-modal');
    var polls = {};
    var currentDocId = null;

    function loadDocs(cb) {
        fetch('/api/documents', {headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(function (docs) {
                document.getElementById('cnt-docs').textContent = docs.length;
                // Sinkronkan kartu statistik di landing dengan data terbaru.
                var sd = document.getElementById('stat-documents');
                if (sd) sd.textContent = docs.length;
                var hl = 0;
                docs.forEach(function (d) { hl += d.highlights_count || 0; });
                var sh = document.getElementById('stat-highlights');
                if (sh) sh.textContent = hl;
                if (cb) cb(docs);
            })
            .catch(function () { if (cb) cb([]); });
    }

    // Impor ulang dokumen yang macet (status "uploaded", 0 paragraf)
    // tanpa perlu mengunggah file lagi.
    function reimportDoc(id, btn) {
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-rotate"></i>'; }
        fetch('/api/documents/' + id + '/reimport', {method: 'POST', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF}})
            .then(function (r) { return r.json().then(function (j) { return {ok: r.ok, j: j}; }); })
            .then(function (res) {
                if (res.ok) {
                    var n = res.j && res.j.document && res.j.document.sections ? res.j.document.sections.length : 0;
                    kuroToast('Impor ulang berhasil (' + n + ' paragraf) ✅', 'ok');
                    loadDocs(renderDocList);
                } else {
                    kuroAlert((res.j && (res.j.message || res.j.error)) || 'Impor ulang gagal.', 'error');
                }
            })
            .catch(function (e) {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-rotate"></i>'; }
                kuroAlert(e.message || 'Impor ulang gagal.', 'error');
            });
    }

    function openDocModal() {
        var box = document.getElementById('doc-list');
        box.innerHTML = '<p class="empty">Memuat…</p>';
        docModal.classList.add('open');
        if (isMobile()) shell.classList.remove('sb-open-m');
        loadDocs(function (docs) { renderDocList(docs); });
    }

    function renderDocList(docs) {
        var box = document.getElementById('doc-list');
        box.innerHTML = '';
        if (!docs.length) {
            box.innerHTML = '<p class="empty">Belum ada dokumen — tekan tombol <b>Unggah .docx</b> di kanan atas untuk mulai memeriksa skripsi/proposal.</p>';
            return;
        }
        var grid = document.createElement('div');
        grid.className = 'doc-cards';
        docs.forEach(function (d) {
            var st = d.status || 'uploaded';
            var date = '';
            try {
                date = d.created_at
                    ? new Date(String(d.created_at).replace(' ', 'T')).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'})
                    : ('#' + d.id);
            } catch (e) { date = '#' + d.id; }

            var stats = [];
            if (d.sections_count != null) stats.push('<span class="doc-stat"><i class="fa-solid fa-align-left"></i> ' + d.sections_count + ' paragraf</span>');
            if (d.highlights_count != null) stats.push('<span class="doc-stat"><i class="fa-solid fa-magnifying-glass"></i> ' + d.highlights_count + ' temuan</span>');

            var card = document.createElement('div');
            card.className = 'doc-card';
            card.title = 'Klik untuk membuka dokumen ini';
            card.innerHTML =
                '<div class="doc-card-top">' +
                    '<span class="doc-card-ic"><i class="fa-solid fa-file-lines"></i></span>' +
                    '<div class="grow"><b>' + esc(d.title) + '</b>' +
                    '<small>' + esc(d.type || 'skripsi') + ' · ' + esc(date) + '</small></div>' +

                    '<span class="badge ' + esc(st) + '">' + esc(st) + '</span>' +
                '</div>' +
                '<div class="doc-card-stats">' + stats.join('') + '</div>' +
                '<div class="doc-card-act">' +
                    '<button type="button" class="btn small open-btn"><i class="fa-solid fa-arrow-right"></i> Buka</button>' +
                    '<span class="grow"></span>' +
                    ((d.sections_count || 0) === 0
                        ? '<button type="button" class="btn small ghost retry-btn" title="Impor ulang isi dokumen (tanpa unggah lagi)"><i class="fa-solid fa-rotate"></i></button>'
                        : '') +
                    '<button type="button" class="btn small ghost review-btn" title="Periksa AI (review highlight + komentar)"><i class="fa-solid fa-wand-magic-sparkles"></i></button>' +
                    '<a class="btn small ghost dl-btn" title="Unduh .docx hasil"><i class="fa-solid fa-download"></i></a>' +
                    '<button type="button" class="btn small ghost del-btn" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>' +
                '</div>';

            card.addEventListener('click', function () {
                docModal.classList.remove('open');
                openDoc(d.id);
            });
            card.querySelector('.review-btn').addEventListener('click', function (e) {
                e.stopPropagation();
                docModal.classList.remove('open');
                openDoc(d.id);
                startReview(d.id);
            });
            var retryBtn = card.querySelector('.retry-btn');
            if (retryBtn) {
                retryBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    reimportDoc(d.id, retryBtn);
                });
            }
            card.querySelector('.dl-btn').href = '/api/documents/' + d.id + '/export';
            card.querySelector('.dl-btn').addEventListener('click', function (e) { e.stopPropagation(); });
            card.querySelector('.del-btn').addEventListener('click', function (e) {
                e.stopPropagation();
                if (!confirm('Hapus dokumen "' + d.title + '"?')) return;
                fetch('/api/documents/' + d.id, {method: 'DELETE', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF}})
                    .then(function () { loadDocs(renderDocList); });
            });
            grid.appendChild(card);
        });
        box.appendChild(grid);
    }

    document.getElementById('btn-docs').addEventListener('click', openDocModal);
    document.getElementById('btn-doc-close').addEventListener('click', function () { docModal.classList.remove('open'); });
    docModal.addEventListener('click', function (e) { if (e.target === this) docModal.classList.remove('open'); });

    // Kartu statistik di landing
    document.getElementById('st-docs').addEventListener('click', openDocModal);
    document.getElementById('st-highlights').addEventListener('click', openDocModal);
    document.getElementById('st-comments').addEventListener('click', openDocModal);
    document.getElementById('st-prov').addEventListener('click', function () {
        if (isMobile()) { shell.classList.add('sb-open-m'); }
        else if (sbClosed) toggleSidebar();
        apiDrop.classList.add('open');
    });

    // Unggah lewat tombol + / tombol di modal dokumen
    var attachInput = document.getElementById('attach-file');
    var attachBtn = document.getElementById('btn-attach');
    var attachMenu = document.getElementById('cp-attach-menu');
    var genChip = document.getElementById('gen-chip');

    attachBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        attachMenu.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
        if (!attachMenu.contains(e.target) && !attachBtn.contains(e.target)) attachMenu.classList.remove('open');
    });

    // ---------- Mode generate (gambar / video) ----------
    var genMode = null; // null | 'image' | 'video'
    function setGenMode(mode, label) {
        genMode = mode;
        if (mode) {
            genChip.hidden = false;
            genChip.textContent = label + ' — tulis deskripsinya lalu kirim';
        } else {
            genChip.hidden = true;
            genChip.textContent = '';
        }
    }

    document.getElementById('am-file').addEventListener('click', function () {
        attachMenu.classList.remove('open');
        setGenMode(null);
        attachInput.click();
    });
    document.getElementById('am-docs').addEventListener('click', function () {
        attachMenu.classList.remove('open');
        setGenMode(null);
        openDocModal();
    });
    document.getElementById('am-image').addEventListener('click', function () {
        attachMenu.classList.remove('open');
        setGenMode('image', '🎨 Mode Gambar');
        chatInput.focus();
    });
    document.getElementById('am-video').addEventListener('click', function () {
        attachMenu.classList.remove('open');
        setGenMode('video', '🎬 Mode Video');
        chatInput.focus();
    });

    // ---------- Mode Berbicara (AI membacakan jawaban — suara ala anime via Gemini TTS) ----------
    var speakMode = false;
    try { speakMode = localStorage.getItem('kuro_speak') === 'on'; } catch (e) {}
    var speakState = document.getElementById('am-speak-state');

    function applySpeak() {
        speakState.textContent = speakMode ? 'Nyala' : 'Mati';
        speakState.classList.toggle('on', speakMode);
        var sb = document.getElementById('btn-speak');
        if (sb) {
            sb.classList.toggle('on', speakMode);
            sb.title = speakMode
                ? 'AI bicara otomatis: NYALA — klik untuk mematikan'
                : 'AI bicara otomatis: mati — klik untuk mengaktifkan';
        }
    }
    function speak(text) {
        if (!speakMode || !text) return;
        speakServer(String(text).slice(0, 4000), null);
    }
    // ---------- AI bicara: suara Kuro ala anime Indonesia (Gemini TTS) ----------
    // Prioritas suara AI ala anime dari server (butuh API key Gemini);
    // bila belum ada key / server gagal -> fallback otomatis ke suara browser.
    var speakingBtn = null;
    var speakAudio = null;   // objek Audio hasil TTS Gemini
    var speakToken = 0;      // pembatalan bila user menekan tombol saat menunggu
    var ttsWarned = false;   // toast "butuh key" cukup sekali per sesi
    var SPEAK_HTML = '<i class="fa-solid fa-volume-high"></i> Dengarkan';
    var STOP2_HTML = '<i class="fa-solid fa-stop"></i> Berhenti';
    var WAIT_HTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyiapkan suara…';

    // Preset suara Kuro — sama dengan config/ai.php (tts.voices).
    var KURO_VOICES = [
        {id: 'anime',   label: 'Anime Ceria'},
        {id: 'energik', label: 'Anime Energik'},
        {id: 'lembut',  label: 'Anime Lembut'},
        {id: 'santai',  label: 'Santai Breezy'},
        {id: 'netral',  label: 'Netral Jelas'}
    ];
    var voiceId = 'anime';
    try {
        var savedVoice = localStorage.getItem('kuro_voice');
        if (savedVoice && KURO_VOICES.some(function (v) { return v.id === savedVoice; })) voiceId = savedVoice;
    } catch (e) {}

    function voiceLabel() {
        for (var i = 0; i < KURO_VOICES.length; i++) {
            if (KURO_VOICES[i].id === voiceId) return KURO_VOICES[i].label;
        }
        return 'Anime Ceria';
    }

    function applyVoiceState() {
        var el = document.getElementById('am-voice-state');
        if (el) el.textContent = voiceLabel();
    }

    function stopSpeakBtn() {
        speakToken++;
        try { window.speechSynthesis.cancel(); } catch (e) {}
        if (speakAudio) {
            try { speakAudio.pause(); } catch (e) {}
            speakAudio = null;
        }
        if (speakingBtn) {
            speakingBtn.classList.remove('on');
            speakingBtn.innerHTML = SPEAK_HTML;
            speakingBtn = null;
        }
    }

    // Putar hasil TTS dari server (data URI audio/wav).
    function playSpeakAudio(dataUri, btn) {
        speakAudio = new Audio(dataUri);
        speakAudio.onended = stopSpeakBtn;
        speakAudio.onerror = function () {
            stopSpeakBtn();
            if (btn) kuroAlert('Gagal memutar suara dari AI — coba lagi.');
        };
        if (btn) btn.innerHTML = STOP2_HTML;
        speakAudio.play().catch(function () {
            stopSpeakBtn();
            if (btn) kuroAlert('Browser memblokir pemutaran audio — tekan tombol Dengarkan lagi.');
        });
    }

    // Fallback: suara browser bila suara AI belum tersedia.
    function speakBrowser(text, btn) {
        if (!window.speechSynthesis) {
            stopSpeakBtn();
            if (btn) kuroAlert('Browser belum mendukung text-to-speech — coba Chrome/Edge terbaru.');
            return;
        }
        try {
            var u = new SpeechSynthesisUtterance(text);
            u.lang = 'id-ID';
            u.rate = 1;
            var voices = window.speechSynthesis.getVoices() || [];
            for (var i = 0; i < voices.length; i++) {
                if (/id[-_]ID|Indones/i.test(voices[i].lang + ' ' + voices[i].name)) { u.voice = voices[i]; break; }
            }
            u.onend = stopSpeakBtn;
            u.onerror = stopSpeakBtn;
            window.speechSynthesis.speak(u);
            if (btn) btn.innerHTML = STOP2_HTML;
        } catch (e) {
            stopSpeakBtn();
            if (btn) kuroAlert('Gagal memutar suara: ' + e.message);
        }
    }

    // Minta suara ala anime ke server, lalu putar.
    function speakServer(text, btn, tok) {
        fetch('/api/voice/speak', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify({text: text, voice: voiceId})
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (tok && tok !== speakToken) return; // dibatalkan user saat menunggu
            if (d.ok && d.audio) {
                playSpeakAudio(d.audio, btn);
            } else {
                if (d.need_key && !ttsWarned) {
                    ttsWarned = true;
                    kuroToast('Suara anime butuh API key Gemini — sementara pakai suara browser.', 'err');
                }
                speakBrowser(text, btn);
            }
        }).catch(function () {
            if (tok && tok !== speakToken) return;
            speakBrowser(text, btn);
        });
    }

    // Tombol "Dengarkan" di tiap jawaban AI.
    function speakMessage(text, btn) {
        if (speakingBtn === btn) { stopSpeakBtn(); return; }
        stopSpeakBtn();
        speakingBtn = btn;
        var tok = speakToken;
        btn.classList.add('on');
        btn.innerHTML = WAIT_HTML;
        speakServer(String(text).slice(0, 4000), btn, tok);
    }

    applySpeak();
    if (window.speechSynthesis) { try { window.speechSynthesis.getVoices(); } catch (e) {} }
    applyVoiceState();
    var voiceItem = document.getElementById('am-voice');
    if (voiceItem) {
        voiceItem.addEventListener('click', function () {
            attachMenu.classList.remove('open');
            var idx = 0;
            for (var i = 0; i < KURO_VOICES.length; i++) {
                if (KURO_VOICES[i].id === voiceId) { idx = i; break; }
            }
            voiceId = KURO_VOICES[(idx + 1) % KURO_VOICES.length].id;
            try { localStorage.setItem('kuro_voice', voiceId); } catch (e) {}
            applyVoiceState();
            kuroToast('Suara Kuro: ' + voiceLabel() + ' 🎀', 'ok');
        });
    }
    document.getElementById('am-speak').addEventListener('click', function () {
        attachMenu.classList.remove('open');
        speakMode = !speakMode;
        try { localStorage.setItem('kuro_speak', speakMode ? 'on' : 'off'); } catch (e) {}
        applySpeak();
        if (speakMode) { speak('Mode berbicara aktif.'); }
        else if (window.speechSynthesis) { try { window.speechSynthesis.cancel(); } catch (e) {} }
    });
    // Toggle bicara otomatis di composer (ikon speaker) — sejalan dengan menu + "Mode Berbicara".
    var speakToggleBtn = document.getElementById('btn-speak');
    if (speakToggleBtn) {
        speakToggleBtn.addEventListener('click', function () {
            speakMode = !speakMode;
            try { localStorage.setItem('kuro_speak', speakMode ? 'on' : 'off'); } catch (e) {}
            applySpeak();
            if (speakMode) { speak('Mode berbicara aktif. Kuro akan membacakan setiap jawaban.'); }
            else { stopSpeakBtn(); }
        });
    }
    document.getElementById('btn-doc-upload').addEventListener('click', function () { attachInput.click(); });
    attachInput.addEventListener('change', function () {
        var file = attachInput.files[0];
        if (!file) return;
        uploadDoc(file);
        attachInput.value = '';
    });

    function uploadDoc(file) {
        if (!/\.docx$/i.test(file.name)) { kuroAlert('File harus berformat .docx (Word 2007 ke atas). Simpan ulang sebagai ".docx" bila file masih ".doc".'); return; }
        if (file.size > 20 * 1024 * 1024) {
            kuroAlert('Ukuran file ' + (file.size / 1048576).toFixed(1) + ' MB — maksimal 20 MB. Kompres gambar di dalam dokumen lalu unggah ulang.');
            return;
        }
        kuroThinking(true, 'Kuro mengimpor dokumen… 📥');
        var fd = new FormData();
        fd.append('document', file);
        fd.append('type', 'skripsi');
        fetch('/api/documents', {method: 'POST', headers: {'Accept': 'application/json'}, body: fd})
            .then(function (r) {
                if (r.ok) return r.json();
                // Baca detail error dari server supaya pesannya jelas (bukan "gagal" generik).
                return r.json().catch(function () { return null; }).then(function (j) {
                    var m = 'Unggah gagal (HTTP ' + r.status + ').';
                    if (j && j.errors) {
                        for (var k in j.errors) { if (j.errors[k] && j.errors[k][0]) { m = j.errors[k][0]; break; } }
                    } else if (j && j.message) {
                        m = j.message;
                    }
                    if (r.status === 413 || /upload_max|post_max|too large|lebih besar/i.test(m)) {
                        m = 'File terlalu besar untuk server (melebihi batas unggah PHP). Kecilkan dokumen (kompres gambar) lalu coba lagi.';
                    } else if (r.status === 422 && /document.*required|No file/i.test(m)) {
                        m = 'File tidak sampai ke server — kemungkinan melebihi batas unggah. Coba file .docx yang lebih kecil.';
                    }
                    // Sertakan penyebab asli dari server supaya error tidak lagi generik.
                    if (j && j.error && String(j.error).indexOf(m) === -1) {
                        m += ' — Detail: ' + j.error;
                    }
                    throw new Error(m);
                });
            })
            .then(function (data) {
                docModal.classList.remove('open');
                kuroThinking(false, 'Dokumen terimpor ✅ mulai review…');
                loadDocs();
                var id = data.document ? data.document.id : null;
                if (id) {
                    openDoc(id);
                    startReview(id);
                }
            })
            .catch(function (e) {
                kuroThinking(false);
                kuroAlert(e.message || 'Unggah gagal — periksa koneksi lalu coba lagi.');
            });
    }

    // ---------- Animasi "Kuro membaca": garis warna menyapu + kursor kucing berjalan ----------
    // Selama review berjalan, seekor kucing 🐱 jalan menyusuri dokumen
    // dengan garis gradasi ungu-biru — menandakan bagian yang sedang dibaca.
    var readBeam = null;
    var readBeamTimer = null;

    function startReadAnimation() {
        var view = document.getElementById('dp-view');
        if (!view) return;
        stopReadAnimation();
        readBeam = document.createElement('div');
        readBeam.className = 'doc-readline';
        readBeam.innerHTML = '<span class="dr-cat">🐱</span>';
        view.appendChild(readBeam);
        updateReadBeamHeight();
        readBeamTimer = setInterval(updateReadBeamHeight, 1500);
    }

    function updateReadBeamHeight() {
        var view = document.getElementById('dp-view');
        if (view && readBeam) {
            view.style.setProperty('--doc-h', Math.max(view.scrollHeight - 6, 200) + 'px');
        }
    }

    function stopReadAnimation() {
        if (readBeamTimer) { clearInterval(readBeamTimer); readBeamTimer = null; }
        if (readBeam && readBeam.parentNode) readBeam.parentNode.removeChild(readBeam);
        readBeam = null;
    }

    // ---------- Tab panel dokumen: Dokumen / Temuan AI / Komentar ----------
    // Temuan & komentar TIDAK lagi menumpuk di bawah dokumen (dulu memakan
    // 42% tinggi panel & menyulitkan scroll) — kini masing-masing punya tab
    // sendiri dengan scroll penuh, dokumen tetap utuh 100%.
    var docTabs = document.getElementById('dp-tabs');

    var TYPE_LABEL = { grammar: 'Tata Bahasa', citation: 'Kutipan', methodology: 'Metodologi', structure: 'Struktur', style: 'Gaya Penulisan' };
    function typeLabel(t) { return TYPE_LABEL[t] || 'Lainnya'; }

    function showDocTab(name) {
        if (!docTabs) return;
        Array.prototype.forEach.call(docTabs.querySelectorAll('.doc-tab'), function (b) {
            b.classList.toggle('active', b.dataset.tab === name);
        });
        document.getElementById('dp-view').hidden = name !== 'doc';
        document.getElementById('dp-findings').hidden = name !== 'find';
        document.getElementById('dp-comments').hidden = name !== 'comments';
        document.getElementById('dp-chat').hidden = name !== 'chat';
    }

    if (docTabs) {
        docTabs.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('.doc-tab') : null;
            if (b) showDocTab(b.dataset.tab);
        });
    }

    function updateTabCounts(doc) {
        var fc = document.getElementById('tab-find-count');
        var cc = document.getElementById('tab-cm-count');
        if (fc) fc.textContent = (doc.highlights || []).length;
        if (cc) cc.textContent = (doc.comments || []).length;
    }

    // Komentar dokumen: temuan AI otomatis jadi komentar (badge AI + model)
    // plus komentar manual mahasiswa/dosen — bisa ditandai selesai.
    function renderComments(list, docId) {
        var box = document.getElementById('dp-comments');
        if (!box) return;
        box.innerHTML = '<h3><i class="fa-solid fa-comments"></i>Komentar <span class="chip">' + list.length + '</span></h3>';
        if (!list.length) {
            box.innerHTML += '<p class="muted" style="font-size:.8rem">Belum ada komentar — temuan AI juga akan muncul di sini.</p>';
            return;
        }
        list.forEach(function (c) {
            var div = document.createElement('div');
            div.className = 'cm-item' + (c.is_ai ? ' ai' : '') + (c.resolved ? ' resolved' : '');
            div.innerHTML =
                '<div class="cm-head">' +
                '<b>' + esc(c.author_name || 'Anonim') + '</b>' +
                (c.is_ai ? '<span class="cm-badge"><i class="fa-solid fa-robot"></i> AI</span>' : '') +
                (c.resolved ? '<span class="cm-res">Selesai</span>' : '') +
                '</div>' +
                '<div class="cm-body">' + esc(c.body) + '</div>';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn small ghost';
            btn.innerHTML = c.resolved
                ? '<i class="fa-solid fa-rotate-left"></i> Buka lagi'
                : '<i class="fa-solid fa-check"></i> Tandai selesai';
            btn.addEventListener('click', function () {
                fetch('/api/comments/' + c.id + '/resolve', {
                    method: 'PATCH',
                    headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF}
                }).then(function () { openDoc(docId); });
            });
            var act = document.createElement('div');
            act.className = 'cm-act';
            act.appendChild(btn);
            div.appendChild(act);
            box.appendChild(div);
        });
    }

    // ---------- Tanya Dokumen: chat khusus dengan konteks isi dokumen ----------
    var docChatHist = {}; // riwayat chat per dokumen

    function dcLog() { return document.getElementById('dc-log'); }

    function dcBubble(role, text) {
        var log = dcLog();
        if (!log) return;
        var empty = log.querySelector('.dc-empty');
        if (empty) empty.parentNode.removeChild(empty);
        var div = document.createElement('div');
        div.className = 'dc-msg ' + (role === 'user' ? 'me' : 'ai');
        div.textContent = text;
        log.appendChild(div);
        log.scrollTop = log.scrollHeight;
    }

    function sendDocChat() {
        var input = document.getElementById('dc-input');
        var text = (input ? input.value : '').trim();
        if (!text || !currentDocId) return;
        input.value = '';
        var hist = docChatHist[currentDocId] || (docChatHist[currentDocId] = []);
        dcBubble('user', text);
        hist.push({ role: 'user', content: text });

        var sel = document.getElementById('dp-scope');
        var scope = (sel && sel.value && sel.value !== 'all') ? sel.value.split('-') : null;

        var typing = document.createElement('div');
        typing.className = 'dc-msg ai typing';
        typing.textContent = 'Kuro sedang membaca dokumen…';
        dcLog().appendChild(typing);

        fetch('/chat', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify({
                message: text,
                provider: currentProvider,
                model: currentModel,
                document_id: currentDocId, // server menyertakan ISI dokumen sebagai konteks
                document_from: scope ? parseInt(scope[0], 10) : null,
                document_to: scope ? parseInt(scope[1], 10) : null,
                history: hist.slice(0, -1).slice(-20),
                concise: conciseOn // ⚡ jawaban singkat & jelas bila toggle aktif
            })
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (typing.parentNode) typing.parentNode.removeChild(typing);
            var reply = (data && data.ok) ? data.content : ('⚠️ ' + ((data && data.error) || 'AI gagal menjawab.'));
            dcBubble('ai', reply);
            hist.push({ role: 'assistant', content: reply });
        }).catch(function (e) {
            if (typing.parentNode) typing.parentNode.removeChild(typing);
            dcBubble('ai', '⚠️ ' + e.message);
        });
    }

    (function () {
        var sendBtn = document.getElementById('dc-send');
        var dcInput = document.getElementById('dc-input');
        if (sendBtn) sendBtn.addEventListener('click', sendDocChat);
        if (dcInput) dcInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); sendDocChat(); }
        });
    })();

    // ---------- Split-screen: buka dokumen ----------
    function markHighlights(bodyHtml, highlights) {
        var html = bodyHtml;
        highlights.forEach(function (h) {
            if (!h.quote) return;
            var q = esc(h.quote);
            if (html.indexOf(q) === -1) return;
            var mark = '<mark class="hl-mark c-' + esc(h.color || 'green') + '" title="' +
                esc((h.comment || '') + (h.suggestion ? ' → ' + h.suggestion : '')) + '">' + q + '</mark>';
            html = html.split(q).join(mark);
        });
        return html;
    }

    // Dropdown bagian untuk "Periksa AI": daftar BAB (heading level 1) dengan
    // rentang section-nya. Bila dokumen tanpa BAB, dibagi per 10 paragraf.
    // Review lalu HANYA memproses bab terpilih — cepat & hemat kuota AI.
    function renderScopeOptions(sections) {
        var sel = document.getElementById('dp-scope');
        if (!sel) return;
        var keep = sel.value;
        var opts = ['<option value="all">Seluruh dokumen</option>'];
        var babs = [];
        sections.forEach(function (s) {
            if (s.level === 1 && s.heading) babs.push({ from: s.index, name: s.heading });
        });
        if (!babs.length) {
            var per = 10;
            for (var i = 0; i * per < sections.length; i++) {
                var f = i * per, t = Math.min((i + 1) * per - 1, sections.length - 1);
                opts.push('<option value="' + sections[f].index + '-' + sections[t].index + '">Bagian ' + (i + 1) +
                    ' (paragraf ' + (f + 1) + '–' + (t + 1) + ')</option>');
            }
        } else {
            var last = sections[sections.length - 1].index;
            babs.forEach(function (b, i) {
                var t = (i + 1 < babs.length) ? (babs[i + 1].from - 1) : last;
                opts.push('<option value="' + b.from + '-' + t + '">' + esc(b.name) + '</option>');
            });
        }
        sel.innerHTML = opts.join('');
        if (keep) {
            var ada = false;
            Array.prototype.forEach.call(sel.options, function (o) { if (o.value === keep) ada = true; });
            if (ada) sel.value = keep;
        }
    }

    function renderDocView(doc) {
        document.getElementById('dp-title').textContent = doc.title || ('Dokumen #' + doc.id);
        var badge = document.getElementById('dp-status');
        badge.className = 'badge ' + esc(doc.status || 'uploaded');
        badge.textContent = doc.status || 'uploaded';
        document.getElementById('dp-export').href = '/api/documents/' + doc.id + '/export';

        var view = document.getElementById('dp-view');
        view.innerHTML = '';
        // Tampilan ala file Word (.docx): konten dibungkus "lembar kertas A4".
        var page = document.createElement('div');
        page.className = 'doc-page';
        view.appendChild(page);
        var sections = (doc.sections || []).slice().sort(function (a, b) { return (a.index || 0) - (b.index || 0); });
        if (!sections.length) {
            page.innerHTML = '<p class="dv-empty">Dokumen ini belum memiliki konten teks.</p>';
        }
        renderScopeOptions(sections);
        sections.forEach(function (s) {
            if (s.heading) {
                var h = document.createElement(s.level === 1 ? 'div' : (s.level === 2 ? 'div' : 'div'));
                h.className = s.level === 1 ? 'dv-h1' : (s.level === 2 ? 'dv-h2' : 'dv-h3');
                h.textContent = s.heading;
                page.appendChild(h);
            }
            if (s.body) {
                var p = document.createElement('p');
                p.innerHTML = markHighlights(esc(s.body), doc.highlights || []);
                page.appendChild(p);
            }
        });

        updateTabCounts(doc);
        renderFindings(doc.highlights || [], doc);
        renderComments(doc.comments || [], doc.id);
        renderReviewSummary(doc);
        if ((doc.status || '') === 'reviewing') startReadAnimation(); else stopReadAnimation();
        setMode('doc');
        updateDocLinkChip();
    }

    // Ringkasan hasil AI (skor + kesan umum) tepat di atas daftar temuan.
    function renderReviewSummary(doc) {
        var box = document.getElementById('dp-findings');
        if (!box) return;
        var s = doc.review_summary || {};
        var hasScore = s.score != null;
        if (!hasScore && !s.overall && !s.error) return;
        var p = document.createElement('p');
        p.className = 'dv-summary' + (s.error ? ' dv-summary-err' : '');
        p.textContent = s.error
            ? 'Review gagal: ' + s.error
            : (hasScore ? 'Skor AI: ' + s.score + '/100. ' : '') + (s.overall || '');
        if (!s.error && p.textContent) {
            var sumSpeak = document.createElement('button');
            sumSpeak.type = 'button';
            sumSpeak.className = 'btn small ghost msg-speak';
            sumSpeak.style.marginTop = '8px';
            sumSpeak.innerHTML = '<i class="fa-solid fa-volume-high"></i> Dengarkan';
            sumSpeak.addEventListener('click', function () { speakMessage(p.textContent, sumSpeak); });
            p.appendChild(document.createElement('br'));
            p.appendChild(sumSpeak);
        }
        box.insertBefore(p, box.firstChild.nextSibling);
    }

    function renderFindings(list, doc) {
        var docId = doc.id;
        var secMap = {};
        (doc.sections || []).forEach(function (s) { secMap[s.id] = s; });
        var box = document.getElementById('dp-findings');
        box.innerHTML = '<h3><i class="fa-solid fa-magnifying-glass"></i>Temuan AI <span class="chip">' + list.length + '</span></h3>';
        if (!list.length) {
            box.innerHTML += '<p class="muted" style="font-size:.8rem">Belum ada temuan — jalankan "Periksa AI".</p>';
            return;
        }

        // Tombol "Terima semua revisi" (terapkan sekaligus semua saran AI yang pending).
        var pending = list.filter(function (h) { return !h.status || h.status === 'pending'; }).length;
        if (pending > 1) {
            var allBtn = document.createElement('button');
            allBtn.type = 'button';
            allBtn.className = 'btn small doc-find-all';
            allBtn.innerHTML = '<i class="fa-solid fa-check-double"></i> Terima semua revisi (' + pending + ')';
            allBtn.addEventListener('click', function () {
                allBtn.disabled = true;
                fetch('/api/documents/' + docId + '/highlights/accept-all', {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF}
                }).then(function (r) { return r.json(); }).then(function (data) {
                    kuroToast('✔ ' + (data.message || 'Semua revisi diterapkan.'), 'ok');
                    openDoc(docId);
                }).catch(function () { allBtn.disabled = false; });
            });
            box.appendChild(allBtn);
        }

        list.slice(0, 40).forEach(function (h) {
            var div = document.createElement('div');
            div.className = 'hl-item c-' + esc(h.color || 'green') +
                (h.status === 'accepted' ? ' done' : (h.status === 'rejected' ? ' dropped' : ''));
            var sec = h.document_section_id ? secMap[h.document_section_id] : null;
            var locTxt = sec
                ? (sec.heading ? sec.heading : ('Paragraf ' + ((sec.index || 0) + 1)))
                : (h.paragraph_index != null ? ('Paragraf ' + (h.paragraph_index + 1)) : '');
            var src = (h.source_model || h.source_provider)
                ? (h.source_model || 'model?') + (h.source_provider ? ' · ' + h.source_provider : '')
                : 'AI';
            div.innerHTML =
                '<div class="hl-top">' +
                '<span class="hl-dot c-' + esc(h.color || 'green') + '"></span>' +
                (locTxt ? '<span class="hl-loc" title="Lokasi temuan di dokumen"><i class="fa-solid fa-location-dot"></i> ' + esc(locTxt) + '</span>' : '') +
                '<span class="hl-type t-' + esc(h.type || 'style') + '">' + esc(typeLabel(h.type)) + '</span>' +
                (h.severity && h.severity !== 'low' ? '<span class="hl-sev s-' + esc(h.severity) + '">' + esc(h.severity) + '</span>' : '') +
                (h.status && h.status !== 'pending' ? '<span class="hl-status st-' + esc(h.status) + '">' + esc(h.status) + '</span>' : '') +
                '<span class="hl-src" title="Model AI yang memberikan saran ini"><i class="fa-solid fa-robot"></i> ' + esc(src) + '</span>' +
                '</div>' +
                '<div class="q">' + esc(h.quote) + '</div>' +
                (h.comment ? '<div class="c">💬 ' + esc(h.comment) + '</div>' : '') +
                (h.suggestion ? '<div class="c">✍️ ' + esc(h.suggestion) + '</div>' : '') +
                '<div class="hl-act">' +
                '<button class="btn small ok-btn"><i class="fa-solid fa-check"></i> Terima</button>' +
                '<button class="btn small ghost no-btn"><i class="fa-solid fa-xmark"></i> Tolak</button>' +
                '<button class="btn small ghost icon del-btn" title="Hapus temuan ini"><i class="fa-solid fa-trash-can"></i></button>' +
                '</div>';
            div.querySelector('.ok-btn').addEventListener('click', function () {
                actHighlight(h.id, 'accept', docId);
            });
            div.querySelector('.no-btn').addEventListener('click', function () {
                actHighlight(h.id, 'reject', docId);
            });
            var delBtn = div.querySelector('.del-btn');
            if (delBtn) delBtn.addEventListener('click', function () {
                delHighlight(h.id, docId);
            });
            box.appendChild(div);
        });
    }

    function actHighlight(id, act, docId) {
        fetch('/api/highlights/' + id + '/' + act, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF}
        }).then(function () { openDoc(docId); });
    }

    // Hapus temuan (komentar AI terkait ikut terhapus otomatis di server).
    function delHighlight(id, docId) {
        fetch('/api/highlights/' + id, {
            method: 'DELETE',
            headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF}
        }).then(function () {
            kuroToast('Temuan dihapus 🗑️', 'ok');
            openDoc(docId);
        });
    }

    // Chip "Terhubung: <dokumen>" di composer — chat utama otomatis membawa
    // konteks dokumen yang sedang dibuka; klik chip untuk lepas / sambung lagi.
    function updateDocLinkChip() {
        var chip = document.getElementById('doc-link-chip');
        if (!chip) return;
        var on = (MODE === 'doc' && currentDocId);
        chip.hidden = !on;
        if (on) {
            var t = document.getElementById('dp-title').textContent || 'dokumen';
            chip.textContent = docLinkOn ? ('🧩 Terhubung: ' + t) : ('🧩 Konteks dokumen: LEPAS');
            chip.classList.toggle('off', !docLinkOn);
        }
    }

    (function () {
        var chip = document.getElementById('doc-link-chip');
        if (!chip) return;
        chip.addEventListener('click', function () {
            docLinkOn = !docLinkOn;
            updateDocLinkChip();
            kuroToast(docLinkOn ? 'Chat terhubung ke dokumen aktif 🔗' : 'Konteks dokumen dilepas ✂️', 'ok');
        });
    })();

    // ---------- Mode ⚡ Singkat & Jelas: jawaban super ringkat ----------
    function updateConciseBtns() {
        ['btn-concise', 'dc-concise'].forEach(function (id) {
            var b = document.getElementById(id);
            if (b) {
                b.classList.toggle('on', conciseOn);
                b.title = conciseOn
                    ? 'Mode Singkat & Jelas: AKTIF — klik untuk mematikan'
                    : 'Mode Singkat & Jelas: mati — klik agar jawaban AI singkat & jelas';
            }
        });
    }

    (function () {
        ['btn-concise', 'dc-concise'].forEach(function (id) {
            var b = document.getElementById(id);
            if (b) b.addEventListener('click', function () {
                conciseOn = !conciseOn;
                updateConciseBtns();
                kuroToast(conciseOn ? '⚡ Jawaban jadi singkat & jelas' : 'Mode singkat dimatikan — jawaban normal', 'ok');
            });
        });
        updateConciseBtns();
    })();

    // Review otomatis sekali: dokumen baru dibuka & belum pernah diperiksa →
    // Kuro langsung membacanya supaya pengguna tidak perlu menekan apa pun.
    var autoReviewed = {};

    function maybeAutoReview(doc) {
        if (!doc || !doc.id) return;
        if (doc.status !== 'imported' && doc.status !== 'uploaded') return;
        if (autoReviewed[doc.id]) return;
        var hasSections = doc.sections && doc.sections.length;
        var hasFindings = doc.highlights && doc.highlights.length;
        if (hasSections && !hasFindings) {
            autoReviewed[doc.id] = true;
            startReview(doc.id);
        }
    }

    function openDoc(id) {
        currentDocId = id;
        kuroThinking(true, 'Kuro membuka dokumen… 📄');
        fetch('/api/documents/' + id, {headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(function (doc) {
                renderDocView(doc);
                kuroThinking(false);
                maybeAutoReview(doc);
            })
            .catch(function (e) { kuroThinking(false); kuroAlert(e.message); });
    }

    document.getElementById('dp-close').addEventListener('click', function () {
        currentDocId = null;
        setMode(history.length ? 'chat' : 'landing');
        updateDocLinkChip();
    });

    // ---------- Periksa AI + polling ----------
    document.getElementById('dp-review').addEventListener('click', function () {
        if (!currentDocId) return;
        var sel = document.getElementById('dp-scope');
        var val = sel ? sel.value : 'all';
        if (val === 'all') { startReview(currentDocId); return; }
        var parts = val.split('-'); // "indexAwal-indexAkhir"
        startReview(currentDocId, parseInt(parts[0], 10), parseInt(parts[1], 10));
    });

    function startReview(id, from, to) {
        startReadAnimation();
        var st = { done: 0, total: 0, issues: 0 };
        var scoped = (from != null && to != null);
        kuroThinking(true, 'Kuro mulai membaca dokumen… 📖' + (scoped ? ' (fokus bagian terpilih)' : ''));

        // Review dijalankan PER-BATCH: setiap request memproses sebagian,
        // jadi tidak ada lagi loading diam — progres selalu terlihat dan
        // bila terputus, proses lanjut dari batch terakhir.
        function step() {
            fetch('/api/documents/' + id + '/review', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF
                },
                body: JSON.stringify({
                    provider: currentProvider,
                    model: currentModel, // model terpilih di dropdown (null = default provider)
                    from: scoped ? from : null, // rentang section (BAB terpilih) — null = seluruh dokumen
                    to: scoped ? to : null
                })
            }).then(function (r) { return r.json().then(function (j) { return {ok: r.ok, j: j || {}}; }); }).then(function (res) {
                loadDocs();
                if (!res.ok || res.j.status === 'review_failed') {
                    var err = res.j.error || (res.j.summary && res.j.summary.error) || res.j.message || '';
                    kuroThinking(false);
                    stopReadAnimation();
                    kuroAlert('Review gagal 😿 — ' + (err || 'cek provider AI & API Key di menu API Key, lalu coba lagi.'), 'error');
                    if (currentDocId === id) openDoc(id);
                    return;
                }
                if (res.j.status === 'reviewed') {
                    var n = res.j.highlights_count != null ? res.j.highlights_count : ((res.j.progress && res.j.progress.issues) || 0);
                    showDocTab('find');
                    stopReadAnimation();
                    var s = res.j.summary || {};
                    var skor = (s.score != null) ? '\nSkor AI: ' + s.score + '/100' : '';
                    var overall = s.overall ? '\n\n' + s.overall : '';
                    kuroThinking(false);
                    kuroAlert('Review selesai! ✅ ' + n + ' temuan & saran' + skor + (n ? '' : ' — dokumen sudah rapi menurut AI.') + overall, 'success');
                    if (currentDocId === id) openDoc(id);
                    return;
                }
                // Masih "reviewing" → lanjut batch berikutnya + tampilkan progres.
                var p = res.j.progress || {};
                st.done = p.done != null ? p.done : st.done;
                st.total = p.total != null ? p.total : st.total;
                st.issues = p.issues != null ? p.issues : st.issues;
                kuroThinking(true, 'Kuro sedang membaca dokumen & menyusun saran… 📖 (bagian ' + st.done + '/' + st.total + ' · ' + st.issues + ' temuan)');
                step();
            }).catch(function (e) {
                kuroThinking(false);
                kuroAlert((e && e.message) || 'Review gagal — periksa koneksi lalu coba lagi (progres tersimpan, bisa dilanjutkan).', 'error');
            });
        }
        step();
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
                        if (tries > 40) {
                            clearInterval(polls[id]);
                            polls[id] = null;
                            kuroThinking(false, 'Review masih diproses — buka lagi nanti 😿');
                        }
                        return;
                    }
                    clearInterval(polls[id]);
                    polls[id] = null;
                    loadDocs();
                    if (currentDocId === id) {
                        if (data.status === 'reviewed') {
                            var n = data.highlights_count || 0;
                            var s = (data.summary || {});
                            var skor = (s.score != null) ? '\nSkor AI: ' + s.score + '/100' : '';
                            var overall = s.overall ? '\n\n' + s.overall : '';
                            kuroThinking(false);
                            kuroAlert('Review selesai! ✅ ' + n + ' temuan & saran' + skor + (n ? '' : ' — dokumen sudah rapi menurut AI.') + overall, 'success');
                            openDoc(id);
                        } else {
                            var err = data.summary && data.summary.error ? data.summary.error : '';
                            kuroThinking(false);
                            stopReadAnimation();
                            kuroAlert('Review gagal 😿' + (err ? ' — ' + err : ' — cek provider AI & API Key di menu API Key, lalu coba lagi.'), 'error');
                            openDoc(id);
                        }
                    }
                })
                .catch(function () {});
        }, 4000);
    }

    // ---------- Sinkron Router & Mode AI (Gemini / Manual) ----------
    var syncModal = document.getElementById('sync-modal');

    function applySyncMode() {
        var mode = document.getElementById('sync-mode').value;
        document.getElementById('sync-gemini').classList.toggle('show', mode === 'gemini');
        document.getElementById('sync-manual').classList.toggle('show', mode === 'manual');
    }

    function fillSyncStatus(router) {
        var dotOn = document.getElementById('dot-online');
        var dotLc = document.getElementById('dot-local');
        var stOn = document.getElementById('st-online');
        var stLc = document.getElementById('st-local');
        var r = router || {};
        function txt(x) { return x && x.reachable ? 'aktif (' + x.latency_ms + ' ms)' : 'tidak terjangkau'; }
        stOn.textContent = r.online ? txt(r.online) : 'tidak dikonfigurasi';
        stLc.textContent = r.local ? txt(r.local) : '—';
        dotOn.className = 'sync-dot ' + (r.online && r.online.reachable ? 'on' : 'off');
        dotLc.className = 'sync-dot ' + (r.local && r.local.reachable ? 'on' : 'off');
    }

    function loadSetup() {
        fetch('/api/ai/setup', {headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) return;
                document.getElementById('sync-mode').value = data.mode || 'gemini';
                applySyncMode();
                document.getElementById('gemini-model-info').textContent =
                    'Model otomatis: ' + (data.gemini.model || 'gemini-3.8-flash') + ' — tidak perlu menulis manual.';
                document.getElementById('manual-base').value = data.manual.base_url || '';
                document.getElementById('manual-model-info').textContent =
                    'Model otomatis: ' + (data.manual.model || 'dari hasil sinkron router') + ' — tidak perlu menulis manual.';
                document.getElementById('setup-msg').textContent = data.gemini.api_key_set
                    ? 'Key Gemini sudah tersimpan — isi ulang hanya untuk mengganti.'
                    : 'Key Gemini belum diisi (ambil di aistudio.google.com/api-keys).';
            })
            .catch(function () {});
    }

    function loadRouterStatus() {
        fetch('/api/ai/router/status', {headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(function (data) {
                fillSyncStatus(data.router);
                var sel = document.getElementById('manual-scope');
                if (data.router && data.router.active_scope) sel.value = data.router.active_scope;
                var msg = document.getElementById('sync-msg');
                if (data.synced_at) {
                    msg.textContent = 'Sinkron terakhir: ' + data.synced_at
                        + ' — ' + (data.models_cached ? data.models_cached.length : 0) + ' model tersimpan.';
                } else {
                    msg.textContent = 'Belum pernah sinkron — tekan "Sinkron Sekarang".';
                }
            })
            .catch(function () {});
    }

    document.getElementById('btn-open-sync').addEventListener('click', function () {
        provModal.classList.remove('open');
        syncModal.classList.add('open');
        if (isMobile()) shell.classList.remove('sb-open-m');
        loadSetup();
        loadRouterStatus();
    });
    document.getElementById('btn-sync-close').addEventListener('click', function () { syncModal.classList.remove('open'); });
    syncModal.addEventListener('click', function (e) { if (e.target === this) syncModal.classList.remove('open'); });

    document.getElementById('sync-mode').addEventListener('change', applySyncMode);

    document.getElementById('manual-scope').addEventListener('change', function () {
        var base = document.getElementById('manual-base');
        if (this.value === 'local') base.value = 'http://localhost:3001/v1';
    });

    document.getElementById('btn-router-status').addEventListener('click', loadRouterStatus);

    document.getElementById('btn-router-sync').addEventListener('click', function () {
        var msg = document.getElementById('sync-msg');
        msg.textContent = 'Menyinkronkan…';
        var prefer = document.getElementById('manual-scope').value; // online | local
        // Base URL + API key dikirim langsung — sinkron tidak lagi bergantung
        // pada MAXROUTER_ONLINE_URL di .env. Berlaku utk online & lokal.
        fetch('/api/ai/router/sync', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify({
                prefer: prefer,
                base_url: document.getElementById('manual-base').value.trim() || null,
                api_key: document.getElementById('manual-key').value.trim() || null
            })
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok) {
                msg.textContent = '✔ ' + data.message;
                if (data.base_url) document.getElementById('manual-base').value = data.base_url;
                renderModelList(data.models);
                modelCache = {}; // paksa muat ulang daftar model di dropdown composer
                loadProviderModels(currentProvider, true);
                loadProviders();
                loadRouterStatus();
            } else {
                msg.textContent = '✗ ' + (data.error || 'Gagal sinkron — cek URL / jalankan router lokal.');
            }
        }).catch(function (e) { msg.textContent = '✗ ' + e.message; });
    });

    function postSetup() {
        var body = {
            mode: document.getElementById('sync-mode').value,
            gemini_api_key: document.getElementById('gemini-key').value.trim() || null,
            manual_base_url: document.getElementById('manual-base').value.trim() || null,
            manual_api_key: document.getElementById('manual-key').value.trim() || null
        };
        return fetch('/api/ai/setup', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }

    document.getElementById('btn-setup-save').addEventListener('click', function () {
        var msg = document.getElementById('setup-msg');
        postSetup().then(function (data) {
            if (data.ok) {
                msg.textContent = '✔ ' + data.message;
                document.getElementById('gemini-key').value = '';
                document.getElementById('manual-key').value = '';
                currentProvider = data.mode;
                updateProvName();
                loadProviders();
                kuroThinking(false, 'Mode AI tersimpan ✨');
            } else {
                msg.textContent = '✗ ' + (data.message || 'Gagal menyimpan setup.');
            }
        }).catch(function (e) { msg.textContent = '✗ ' + e.message; });
    });

    // ---------- Tes key Gemini langsung ke Google (simpan otomatis bila valid) ----------
    document.getElementById('btn-test-gemini').addEventListener('click', function () {
        var msg = document.getElementById('test-gemini-msg');
        var keyInput = document.getElementById('gemini-key');
        var key = keyInput.value.trim();
        if (!key) {
            msg.textContent = 'Tempel key kamu di kolom API Key Gemini dulu — lalu tekan tombol ini.';
            return;
        }
        msg.textContent = 'Menguji key ke Google…';
        fetch('/api/ai/setup/test-gemini', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify({api_key: key})
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok) {
                msg.textContent = '✔ ' + data.message;
                keyInput.value = '';
                document.getElementById('setup-msg').textContent = 'Key Gemini sudah tersimpan & teruji ✅';
                currentProvider = 'gemini';
                loadProviders();
            } else {
                msg.textContent = '✗ ' + (data.message || 'Key ditolak Google — salin ulang penuh dari aistudio.google.com/api-keys.');
            }
        }).catch(function (e) { msg.textContent = '✗ ' + e.message; });
    });

    // ---------- Daftar model: pencarian + dropdown (gratis / berbayar) ----------
    var availableModels = [];

    function openModelDrop(open) {
        var d = document.getElementById('model-drop');
        if (d) d.classList.toggle('open', !!open);
    }

    function copyModelId(id) {
        var done = function () { kuroToast('Model "' + id + '" disalin ke clipboard', 'ok'); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(id).then(done, done);
        } else {
            var ta = document.createElement('textarea');
            ta.value = id;
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta);
            done();
        }
    }

    // Render ulang isi dropdown mengikuti kata kunci pencarian + filter tier
    function renderModelDrop() {
        var box = document.getElementById('model-list');
        var search = document.getElementById('model-search');
        var tierSel = document.getElementById('model-tier');
        var q = (search ? search.value : '').toLowerCase().trim();
        var tier = tierSel ? tierSel.value : 'all';
        var n = 0;
        box.innerHTML = '';
        availableModels.filter(function (m) { return chatCapable(m.id); }).forEach(function (m) {
            var t = m.tier || 'unknown';
            if (tier !== 'all' && t !== tier) return;
            if (q && String(m.id).toLowerCase().indexOf(q) === -1) return;
            n++;
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'model-item';
            if (m.note) row.title = m.note;
            var badge = document.createElement('span');
            badge.className = t === 'free' ? 'badge-free' : (t === 'paid' ? 'badge-paid' : 'badge-unknown');
            badge.textContent = t === 'free' ? 'GRATIS' : (t === 'paid' ? 'BERBAYAR' : '?');
            var code = document.createElement('code');
            code.textContent = m.id;
            var cp = document.createElement('span');
            cp.className = 'model-copy';
            cp.innerHTML = '<i class="fa-regular fa-copy"></i>';
            row.appendChild(badge);
            row.appendChild(code);
            if (m.price) {
                var price = document.createElement('span');
                price.className = 'model-price';
                price.textContent = '$' + m.price.input + ' / $' + m.price.output + ' per 1jt token';
                row.appendChild(price);
            }
            row.appendChild(cp);
            row.addEventListener('click', function () { copyModelId(m.id); });
            box.appendChild(row);
        });
        if (!n) {
            var empty = document.createElement('p');
            empty.className = 'model-empty';
            empty.textContent = availableModels.length
                ? 'Tidak ada model yang cocok dengan pencarian.'
                : 'Belum ada model — tekan "Muat Daftar Model" dulu.';
            box.appendChild(empty);
        }
    }

    function renderModelList(models) {
        availableModels = models || [];
        var count = document.getElementById('model-count');
        var free = 0, paid = 0, unknown = 0;
        availableModels.forEach(function (m) {
            var t = m.tier || 'unknown';
            if (t === 'free') free++; else if (t === 'paid') paid++; else unknown++;
        });
        count.textContent = '🟢 ' + free + ' gratis • 🔴 ' + paid + ' berbayar' + (unknown ? ' • ⚪ ' + unknown + ' belum diketahui' : '');
        var search = document.getElementById('model-search');
        var tierSel = document.getElementById('model-tier');
        if (search) search.value = '';
        if (tierSel) tierSel.value = 'all';
        renderModelDrop();
        openModelDrop(true);
    }

    var modelSearchInput = document.getElementById('model-search');
    var modelTierSel = document.getElementById('model-tier');
    if (modelSearchInput) {
        modelSearchInput.addEventListener('focus', function () { if (availableModels.length) openModelDrop(true); });
        modelSearchInput.addEventListener('input', function () {
            if (!availableModels.length) return;
            openModelDrop(true);
            renderModelDrop();
        });
    }
    if (modelTierSel) {
        modelTierSel.addEventListener('change', function () {
            if (!availableModels.length) return;
            openModelDrop(true);
            renderModelDrop();

        });
    }
    // Klik di luar area pencarian model → tutup dropdown
    document.addEventListener('click', function (e) {
        var d = document.getElementById('model-drop');
        if (d && !d.contains(e.target)) openModelDrop(false);
    });

    document.getElementById('btn-model-list').addEventListener('click', function () {
        var count = document.getElementById('model-count');
        var key = document.getElementById('gemini-key').value.trim();
        var slug = document.getElementById('sync-mode').value === 'gemini' ? 'gemini' : 'manual';
        count.textContent = 'Memuat model…';
        document.getElementById('model-list').innerHTML = '<p class="model-empty">Memuat daftar model…</p>';
        openModelDrop(true);

        function go() {
            fetch('/api/ai/models/' + slug, {headers: {'Accept': 'application/json'}})
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        renderModelList(data.models);
                        if (data.pricing_source) count.title = 'Sumber label: ' + data.pricing_source;
                    } else {
                        count.textContent = '✗ ' + (data.error || 'Gagal memuat daftar model.');
                        document.getElementById('model-list').innerHTML =
                            '<p class="model-empty">✗ ' + esc(data.error || 'Gagal memuat daftar model.') + '</p>';
                    }
                })
                .catch(function (e) {
                    count.textContent = '✗ ' + e.message;
                    document.getElementById('model-list').innerHTML =
                        '<p class="model-empty">✗ ' + esc(e.message) + '</p>';
                });
        }

        // Mode Gemini + kolom key terisi → simpan dulu supaya tidak "belum dikonfigurasi".
        if (slug === 'gemini' && key) {
            postSetup().then(function (d) {
                if (d.ok) {
                    document.getElementById('gemini-key').value = '';
                    loadProviders();
                }
                go();
            }).catch(function () { go(); });
        } else {
            go();
        }
    });

    // ---------- Init ----------
    loadProviders();
    loadDocs();
    renderChat();
    renderSideConvs();
    fetchConvs();
    restoreCurrent();
})();




