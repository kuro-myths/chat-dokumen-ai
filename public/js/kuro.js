/* ============================================================
   Kuro AI — logika beranda (public/js/kuro.js)
   Dimuat di beranda saja (satu sumber, tidak ada script inline).
   ============================================================ */
(function () {
    var CSRF = document.querySelector('meta[name="csrf-token"]').content;
    var MIC_HTML = '<i class="fa-solid fa-microphone"></i>';
    var STOP_HTML = '<i class="fa-solid fa-stop"></i>';

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

    // ---------- Navigasi sidebar & kartu pintasan ----------
    document.querySelectorAll('[data-goto]').forEach(function (el) {
        el.addEventListener('click', function () {
            var t = document.getElementById(el.getAttribute('data-goto'));
            if (t) t.scrollIntoView({behavior: 'smooth'});
            if (el.getAttribute('data-goto') === 'chat') document.getElementById('chat-input').focus();
        });
    });
    document.querySelectorAll('[data-act]').forEach(function (el) {
        el.addEventListener('click', function () {
            var act = el.getAttribute('data-act');
            document.getElementById('chat').scrollIntoView({behavior: 'smooth'});
            if (act === 'mic') document.getElementById('btn-mic').click();
            else document.getElementById('chat-input').focus();
        });
    });
    document.querySelectorAll('.side-item').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('.side-item').forEach(function (x) { x.classList.remove('active'); });
            b.classList.add('active');
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
                '<button class="btn small show-models"><i class="fa-solid fa-list-check"></i> Lihat Model</button>' +
                (p.is_default ? '' : '<button class="btn small ghost set-default"><i class="fa-solid fa-star"></i> Jadikan Default</button>') +
                '</div>';

            card.querySelector('.show-models').addEventListener('click', function () {
                showModels(p.slug, card);
            });
            var defBtn = card.querySelector('.set-default');
            if (defBtn) defBtn.addEventListener('click', function () { setDefault(p.slug); });

            box.appendChild(card);
        });

        if (!data.providers.length) {
            box.innerHTML = '<p class="muted">Belum ada provider — tambahkan lewat form "Tambah Provider".</p>';
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

    // ---------- Chat + riwayat ----------
    var history = [];
    var chatLog = document.getElementById('chat-log');

    function renderChat() {
        chatLog.innerHTML = '';
        if (!history.length) {
            var empty = document.createElement('div');
            empty.className = 'chat-empty';
            empty.innerHTML = 'Halo! Saya <b>Kuro</b> 🐾 asisten akademikmu.<br>' +
                'Tulis pesan di bawah, atau tekan tombol mic untuk mendikte dengan suara.';
            chatLog.appendChild(empty);
            return;
        }
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
    document.getElementById('btn-new-chat').addEventListener('click', function () {
        history = [];
        renderChat();
        saveCurrent();
        document.getElementById('chat').scrollIntoView({behavior: 'smooth'});
        document.getElementById('chat-input').focus();
        kuroThinking(false, 'Percakapan baru dimulai ✨');
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
                '<button class="btn small hist-load"><i class="fa-solid fa-rotate-right"></i> Muat</button>' +
                '<button class="btn small ghost hist-del"><i class="fa-solid fa-trash-can"></i> Hapus</button>';
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
        return '<button class="btn small review-btn"><i class="fa-solid fa-wand-magic-sparkles"></i> Periksa AI</button>' +
            '<button class="btn small ghost hl-btn"><i class="fa-solid fa-list-check"></i> Muat Temuan</button>' +
            '<a class="btn small ghost" href="/api/documents/' + id + '/export">' +
            '<i class="fa-solid fa-download"></i> Unduh</a>';
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
                box.innerHTML = '<h3 style="margin-top:22px"><i class="fa-solid fa-magnifying-glass"></i>Temuan: ' + esc(title || ('Dokumen #' + id)) +
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
    renderChat();
    restoreCurrent();
})();
