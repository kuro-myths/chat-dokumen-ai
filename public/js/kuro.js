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

    // ---------- Maskot Kuro (dihapus dari UI — no-op agar sisa kode tetap aman) ----------
    function kuroThinking() {}

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
        var body = {
            name: f.name.value.trim(),
            driver: f.driver.value,
            base_url: f.base_url.value.trim() || null,
            api_key: f.api_key.value.trim() || null,
            is_default: f.is_default.checked
        };
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
        el.textContent = currentModel ? shortModel(currentModel) : 'Model otomatis';
        el.title = currentModel ? currentModel : 'Pakai model default provider aktif';
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
            '<span class="grow">Otomatis<small>Model default provider ini</small></span>';
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
                div.textContent = m.content;
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
                history: history.slice(0, -1).slice(-20)
            })
        }).then(function (r) { return r.json(); }).then(function (data) {
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
                .catch(function () { apply(item.messages || []); });
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
                if (cb) cb(docs);
            })
            .catch(function () { if (cb) cb([]); });
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

    // ---------- Mode Berbicara (AI membacakan jawaban — TTS browser) ----------
