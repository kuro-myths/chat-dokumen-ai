/**
 * AutoVoiceInput — auto voice masukan untuk SkripsiAI.
 *
 * Rekam mikrofon otomatis, BERHENTI SENDIRI saat pengguna diam
 * (deteksi silence), lalu kirim audio ke /api/voice/transcribe.
 * Hasil transkripsi (sudah dirapikan AI ke bahasa akademik)
 * otomatis masuk ke dokumen dan dikembalikan lewat callback onText.
 */
export class AutoVoiceInput {
  /**
   * @param {object} opts
   * @param {string} [opts.endpoint] endpoint API transkripsi
   * @param {number} [opts.silenceMs] lamanya diam sebelum auto-stop
   * @param {number} [opts.silenceThreshold] RMS (0-1) yang dianggap "diam"
   * @param {(text: string, response: object) => void} [opts.onText]
   * @param {(status: string, data?: object) => void} [opts.onStatus]
   */
  constructor({
    endpoint = '/api/voice/transcribe',
    silenceMs = 1500,
    silenceThreshold = 0.02,
    onText = () => {},
    onStatus = () => {},
  } = {}) {
    this.endpoint = endpoint;
    this.silenceMs = silenceMs;
    this.silenceThreshold = silenceThreshold;
    this.onText = onText;
    this.onStatus = onStatus;
    this.recording = false;
  }

  /**
   * Mulai merekam.
   * @param {{ documentId?: number|null, sectionIndex?: number|null, mode?: 'append'|'new-section' }} target
   */
  async start({ documentId = null, sectionIndex = null, mode = 'append' } = {}) {
    this.target = { documentId, sectionIndex, mode };
    this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    this.silenceSince = null;

    this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    this.analyser = this.audioCtx.createAnalyser();
    this.analyser.fftSize = 512;
    this.audioCtx.createMediaStreamSource(this.stream).connect(this.analyser);

    this.chunks = [];
    this.recorder = new MediaRecorder(this.stream, { mimeType: this.pickMime() });
    this.recorder.ondataavailable = (e) => e.data.size && this.chunks.push(e.data);
    this.recorder.onstop = () => this.submit();
    this.recorder.start();

    this.recording = true;
    this.startedAt = performance.now();
    this.onStatus('recording');
    this.watchSilence();
  }

  /** Berhenti manual (user klik tombol stop). */
  stop() {
    if (!this.recording) return;
    this.recording = false;
    this.recorder.stop();
  }

  watchSilence() {
    if (!this.recording) return;

    const buffer = new Uint8Array(this.analyser.fftSize);
    this.analyser.getByteTimeDomainData(buffer);

    // RMS sederhana untuk mengukur volume.
    let sum = 0;
    for (let i = 0; i < buffer.length; i++) {
      const v = (buffer[i] - 128) / 128;
      sum += v * v;
    }
    const rms = Math.sqrt(sum / buffer.length);

    if (rms < this.silenceThreshold) {
      this.silenceSince ??= performance.now();
      if (performance.now() - this.silenceSince >= this.silenceMs) {
        this.onStatus('auto-stopping');
        this.stop(); // diam terlalu lama -> auto stop & transkripsi
        return;
      }
    } else {
      this.silenceSince = null;
    }

    requestAnimationFrame(() => this.watchSilence());
  }

  async submit() {
    this.stream.getTracks().forEach((t) => t.stop());
    this.audioCtx.close();

    const blob = new Blob(this.chunks, { type: this.recorder.mimeType });
    const duration = (performance.now() - this.startedAt) / 1000;

    const form = new FormData();
    form.append('audio', blob, 'voice.webm');
    form.append('duration', duration.toFixed(1));
    form.append('mode', this.target.mode);
    if (this.target.documentId) form.append('document_id', this.target.documentId);
    if (this.target.sectionIndex !== null) form.append('section_index', this.target.sectionIndex);

    this.onStatus('transcribing');

    try {
      const res = await fetch(this.endpoint, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: form,
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      this.onStatus('done');
      this.onText(json.text, json);
    } catch (err) {
      this.onStatus('error', { error: err.message });
    }
  }

  pickMime() {
    const candidates = ['audio/webm', 'audio/webm;codecs=opus', 'audio/mp4'];
    return candidates.find((t) => MediaRecorder.isTypeSupported(t)) ?? '';
  }
}

// Contoh pemakaian:
// const voice = new AutoVoiceInput({
//   onText: (text, res) => {
//     editor.value += ' ' + text;            // tampilkan teks
//     renderDocument(res.document);          // dokumen sudah ter-update otomatis
//   },
//   onStatus: (s) => console.log('Voice:', s),
// });
// tombolMic.onclick = () => voice.start({ documentId: 1, sectionIndex: 3, mode: 'append' });
