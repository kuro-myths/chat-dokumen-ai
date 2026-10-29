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

        // Menu provider di composer
        renderProvMenu();
        updateProvName();
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
                '<span class="grow">' + esc(p.name) + '<small>' + esc(p.model ? p.model : driverLabel(p)) + '</small></span>';
            item.addEventListener('click', function () {
                currentProvider = p.slug;
                menu.classList.remove('open');
                renderProvMenu();
                updateProvName();
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

    document.getElementById('provider-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var f = e.target;
        var msg = document.getElementById('provider-msg');
        var body = {
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
                msg.textContent = '✔ Provider & API Key tersimpan.';
                f.reset();
                loadProviders();
                setTimeout(function () { provModal.classList.remove('open'); msg.textContent = ''; }, 900);
                kuroThinking(false, 'Provider baru ditambahkan ✨');
            } else {
                r.json().then(function (j) {
                    msg.textContent = '✗ ' + (j.message || 'Gagal menyimpan provider.');
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

    function saveCurrent() {
        try {
            localStorage.setItem('kuro_current', JSON.stringify({
                provider: currentProvider,
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
                history: history.slice(0, -1).slice(-20)
            })
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok) {
                history.push({role: 'assistant', content: data.content});
                renderChat();
                status.textContent = '✔ ' + data.provider + ' · ' + data.model;
                kuroThinking(false, 'Selesai! Ada lagi? ✨');
                speak(data.content);
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
        fetch('/api/ai/generate/image', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify({prompt: prompt})
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok) {
                history.push({role: 'assistant', type: 'image', content: data.text || prompt, image: data.image});
                renderChat();
                status.textContent = '✔ Gambar dibuat · ' + data.model;
                saveCurrent();
            } else {
                status.textContent = '';
                alert(data.error || 'Gagal membuat gambar.');
            }
        }).catch(function (e) {
            status.textContent = '';
            alert(e.message);
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
        fetch('/api/ai/generate/video', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify({prompt: prompt})
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.ok) {
                status.textContent = '';
                alert(data.error || 'Gagal membuat video.');
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
                            history.push({role: 'assistant', type: 'video', content: prompt, video: s.uri});
                            renderChat();
                            status.textContent = '✔ Video siap · Veo';
                            saveCurrent();
                            btn.disabled = false;
                            setGenMode(null);
                        } else if (!s.ok) {
                            clearInterval(timer);
                            status.textContent = '';
                            alert(s.error || 'Gagal membuat video.');
                            btn.disabled = false;
                            setGenMode(null);
                        } else {
                            status.textContent = 'Membuat video… (' + (tries * 8) + ' detik)';
                        }
                    })
                    .catch(function () {});
                if (tries >= 45) {
                    clearInterval(timer);
                    status.textContent = '';
                    alert('Video terlalu lama dibuat — coba lagi nanti.');
                    btn.disabled = false;
                    setGenMode(null);
                }
            }, 8000);
        }).catch(function (e) {
            status.textContent = '';
            alert(e.message);
            btn.disabled = false;
            setGenMode(null);
        });
    }

    document.getElementById('btn-new-chat').addEventListener('click', function () {
        history = [];
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
        if (!SR) { alert('Browser belum mendukung input suara — coba Chrome/Edge terbaru.'); return; }
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
            provider: currentProvider,
            messages: history.slice()
        });
        setSaved(list);
        kuroThinking(false, 'Percakapan tersimpan 💾');
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
                '<small>' + esc(item.time) + ' · ' + item.messages.length + ' pesan</small></div>' +
                '<button class="btn small hist-load"><i class="fa-solid fa-rotate-right"></i> Muat</button>' +
                '<button class="btn small ghost hist-del"><i class="fa-solid fa-trash-can"></i></button>';
            div.querySelector('.hist-load').addEventListener('click', function () {
                history = item.messages.slice();
                renderChat();
                saveCurrent();
                document.getElementById('hist-modal').classList.remove('open');
                setMode('chat');
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
            setSaved([]);
            renderHistory();
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
            box.innerHTML = '<p class="empty">Belum ada dokumen — unggah .docx lewat tombol di kanan atas.</p>';
            return;
        }
        docs.forEach(function (d) {
            var div = document.createElement('div');
            div.className = 'doc-item';
            div.title = 'Klik untuk membuka dokumen ini';
            div.innerHTML =
                '<div class="grow"><b>' + esc(d.title) + '</b>' +
                '<small>#' + d.id + ' · ' + esc(d.status) + '</small></div>' +
                '<span class="badge ' + esc(d.status) + '">' + esc(d.status) + '</span>' +
                '<div class="doc-actions">' +
                '<a class="btn small ghost dl-btn"><i class="fa-solid fa-download"></i></a>' +
                '<button class="btn small ghost del-btn" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>' +
                '</div>';
            div.addEventListener('click', function () {
                docModal.classList.remove('open');
                openDoc(d.id);
            });
            div.querySelector('.dl-btn').href = '/api/documents/' + d.id + '/export';
            div.querySelector('.dl-btn').addEventListener('click', function (e) { e.stopPropagation(); });
            div.querySelector('.del-btn').addEventListener('click', function (e) {
                e.stopPropagation();
                if (!confirm('Hapus dokumen "' + d.title + '"?')) return;
                fetch('/api/documents/' + d.id, {method: 'DELETE', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF}})
                    .then(function () { loadDocs(renderDocList); });
            });
            box.appendChild(div);
        });
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
    var speakMode = false;
    try { speakMode = localStorage.getItem('kuro_speak') === 'on'; } catch (e) {}
    var speakState = document.getElementById('am-speak-state');

    function applySpeak() {
        speakState.textContent = speakMode ? 'Nyala' : 'Mati';
        speakState.classList.toggle('on', speakMode);
    }
    function speak(text) {
        if (!speakMode || !window.speechSynthesis || !text) return;
        try {
            window.speechSynthesis.cancel();
            var u = new SpeechSynthesisUtterance(String(text).slice(0, 4000));
            u.lang = 'id-ID';
            u.rate = 1;
            var voices = window.speechSynthesis.getVoices() || [];
            for (var i = 0; i < voices.length; i++) {
                if (/id[-_]ID|Indones/i.test(voices[i].lang + ' ' + voices[i].name)) { u.voice = voices[i]; break; }
            }
            window.speechSynthesis.speak(u);
        } catch (e) {}
    }
    applySpeak();
    if (window.speechSynthesis) { try { window.speechSynthesis.getVoices(); } catch (e) {} }
    document.getElementById('am-speak').addEventListener('click', function () {
        attachMenu.classList.remove('open');
        speakMode = !speakMode;
        try { localStorage.setItem('kuro_speak', speakMode ? 'on' : 'off'); } catch (e) {}
        applySpeak();
        if (speakMode) { speak('Mode berbicara aktif.'); }
        else if (window.speechSynthesis) { try { window.speechSynthesis.cancel(); } catch (e) {} }
    });
    document.getElementById('btn-doc-upload').addEventListener('click', function () { attachInput.click(); });
    attachInput.addEventListener('change', function () {
        var file = attachInput.files[0];
        if (!file) return;
        uploadDoc(file);
        attachInput.value = '';
    });

    function uploadDoc(file) {
        if (!/\.docx$/i.test(file.name)) { alert('File harus berformat .docx'); return; }
        kuroThinking(true, 'Kuro mengimpor dokumen… 📥');
        var fd = new FormData();
        fd.append('document', file);
        fd.append('type', 'skripsi');
        fetch('/api/documents', {method: 'POST', headers: {'Accept': 'application/json'}, body: fd})
            .then(function (r) {
                if (!r.ok) throw new Error('Unggah gagal (cek format .docx).');
                return r.json();
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
            .catch(function (e) { kuroThinking(false); alert(e.message); });
    }

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

    function renderDocView(doc) {
        document.getElementById('dp-title').textContent = doc.title || ('Dokumen #' + doc.id);
        var badge = document.getElementById('dp-status');
        badge.className = 'badge ' + esc(doc.status || 'uploaded');
        badge.textContent = doc.status || 'uploaded';
        document.getElementById('dp-export').href = '/api/documents/' + doc.id + '/export';

        var view = document.getElementById('dp-view');
        view.innerHTML = '';
        var sections = (doc.sections || []).slice().sort(function (a, b) { return (a.index || 0) - (b.index || 0); });
        if (!sections.length) {
            view.innerHTML = '<p class="dv-empty">Dokumen ini belum memiliki konten teks.</p>';
        }
        sections.forEach(function (s) {
            if (s.heading) {
                var h = document.createElement(s.level === 1 ? 'div' : (s.level === 2 ? 'div' : 'div'));
                h.className = s.level === 1 ? 'dv-h1' : (s.level === 2 ? 'dv-h2' : 'dv-h3');
                h.textContent = s.heading;
                view.appendChild(h);
            }
            if (s.body) {
                var p = document.createElement('p');
                p.innerHTML = markHighlights(esc(s.body), doc.highlights || []);
                view.appendChild(p);
            }
        });

        renderFindings(doc.highlights || [], doc.id);
        setMode('doc');
    }

    function renderFindings(list, docId) {
        var box = document.getElementById('dp-findings');
        box.innerHTML = '<h3><i class="fa-solid fa-magnifying-glass"></i>Temuan AI <span class="chip">' + list.length + '</span></h3>';
        if (!list.length) {
            box.innerHTML += '<p class="muted" style="font-size:.8rem">Belum ada temuan — jalankan "Periksa AI".</p>';
            return;
        }
        list.slice(0, 40).forEach(function (h) {
            var div = document.createElement('div');
            div.className = 'hl-item c-' + esc(h.color || 'green');
            div.innerHTML =
                '<div class="q">' + esc(h.quote) + '</div>' +
                (h.comment ? '<div class="c">💬 ' + esc(h.comment) + '</div>' : '') +
                (h.suggestion ? '<div class="c">✍️ ' + esc(h.suggestion) + '</div>' : '') +
                (h.status && h.status !== 'pending' ? '<div class="c">Status: ' + esc(h.status) + '</div>' : '') +
                '<div class="hl-act">' +
                '<button class="btn small ok-btn"><i class="fa-solid fa-check"></i> Terima</button>' +
                '<button class="btn small ghost no-btn"><i class="fa-solid fa-xmark"></i> Tolak</button>' +
                '</div>';
            div.querySelector('.ok-btn').addEventListener('click', function () {
                actHighlight(h.id, 'accept', docId);
            });
            div.querySelector('.no-btn').addEventListener('click', function () {
                actHighlight(h.id, 'reject', docId);
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

    function openDoc(id) {
        currentDocId = id;
        kuroThinking(true, 'Kuro membuka dokumen… 📄');
        fetch('/api/documents/' + id, {headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(function (doc) {
                renderDocView(doc);
                kuroThinking(false);
            })
            .catch(function (e) { kuroThinking(false); alert(e.message); });
    }

    document.getElementById('dp-close').addEventListener('click', function () {
        currentDocId = null;
        setMode(history.length ? 'chat' : 'landing');
    });

    // ---------- Periksa AI + polling ----------
    document.getElementById('dp-review').addEventListener('click', function () {
        if (currentDocId) startReview(currentDocId);
    });

    function startReview(id) {
        kuroThinking(true, 'Kuro sedang membaca dokumen… 📄');
        fetch('/api/documents/' + id + '/review', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF
            },
            body: JSON.stringify({provider: currentProvider})
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
                        if (tries > 15) {
                            clearInterval(polls[id]);
                            polls[id] = null;
                            kuroThinking(false, 'Review masih diproses 😿');
                        }
                        return;
                    }
                    clearInterval(polls[id]);
                    polls[id] = null;
                    loadDocs();
                    if (currentDocId === id) {
                        if (data.status === 'reviewed') {
                            kuroThinking(false, 'Review selesai! ✅ (' + (data.highlights_count || 0) + ' temuan)');
                            openDoc(id);
                        } else {
                            kuroThinking(false, 'Review gagal 😿');
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
                document.getElementById('gemini-model').value = data.gemini.model || '';
                document.getElementById('manual-base').value = data.manual.base_url || '';
                document.getElementById('manual-model').value = data.manual.model || '';
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
        fetch('/api/ai/router/sync', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
            body: JSON.stringify({prefer: prefer})
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok) {
                msg.textContent = '✔ ' + data.message;
                if (data.base_url) document.getElementById('manual-base').value = data.base_url;
                renderModelList(data.models);
                loadProviders();
                loadRouterStatus();
                kuroThinking(false, 'Router tersinkron ✨');
            } else {
                msg.textContent = '✗ ' + (data.error || 'Gagal sinkron — cek URL / jalankan router lokal.');
            }
        }).catch(function (e) { msg.textContent = '✗ ' + e.message; });
    });

    function postSetup() {
        var body = {
            mode: document.getElementById('sync-mode').value,
            gemini_api_key: document.getElementById('gemini-key').value.trim() || null,
            gemini_model: document.getElementById('gemini-model').value.trim() || null,
            manual_base_url: document.getElementById('manual-base').value.trim() || null,
            manual_api_key: document.getElementById('manual-key').value.trim() || null,
            manual_model: document.getElementById('manual-model').value.trim() || null
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

    // ---------- Daftar model: gratis / berbayar ----------
    function renderModelList(models) {
        var list = document.getElementById('model-list');
        var count = document.getElementById('model-count');
        list.style.display = 'block';
        list.innerHTML = '';
        var free = 0, paid = 0, unknown = 0;
        (models || []).forEach(function (m) {
            var tier = m.tier || 'unknown';
            if (tier === 'free') free++; else if (tier === 'paid') paid++; else unknown++;
            var row = document.createElement('div');
            row.className = 'model-item';
            var badge = document.createElement('span');
            badge.className = tier === 'free' ? 'badge-free' : (tier === 'paid' ? 'badge-paid' : 'badge-unknown');
            badge.textContent = tier === 'free' ? 'GRATIS' : (tier === 'paid' ? 'BERBAYAR' : '?');
            var code = document.createElement('code');
            code.textContent = m.id;
            row.appendChild(badge);
            row.appendChild(code);
            if (m.price) {
                var price = document.createElement('span');
                price.className = 'model-price';
                price.textContent = '$' + m.price.input + ' / $' + m.price.output + ' per 1jt token';
                row.appendChild(price);
            }
            if (m.note) row.title = m.note;
            list.appendChild(row);
        });
        count.textContent = '🟢 ' + free + ' gratis • 🔴 ' + paid + ' berbayar' + (unknown ? ' • ⚪ ' + unknown + ' belum diketahui' : '');
    }

    document.getElementById('btn-model-list').addEventListener('click', function () {
        var count = document.getElementById('model-count');
        var key = document.getElementById('gemini-key').value.trim();
        var slug = document.getElementById('sync-mode').value === 'gemini' ? 'gemini' : 'manual';
        count.textContent = 'Memuat model…';

        function go() {
            fetch('/api/ai/models/' + slug, {headers: {'Accept': 'application/json'}})
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        renderModelList(data.models);
                        if (data.pricing_source) count.title = 'Sumber label: ' + data.pricing_source;
                    } else {
                        count.textContent = '✗ ' + (data.error || 'Gagal memuat daftar model.');
                    }
                })
                .catch(function (e) { count.textContent = '✗ ' + e.message; });
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
    restoreCurrent();
})();

