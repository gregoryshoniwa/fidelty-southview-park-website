// Minimal Gemini Live client: browser talks to Google directly with a short-lived token minted by our server.
// Mic audio: 16 kHz PCM16 in; model audio: 24 kHz PCM16 out.
const WS = 'wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContentConstrained';

function b64FromInt16(int16) {
    let s = ''; const bytes = new Uint8Array(int16.buffer);
    for (let i = 0; i < bytes.length; i += 0x8000) s += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
    return btoa(s);
}
function int16FromB64(b64) {
    const bin = atob(b64); const bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    return new Int16Array(bytes.buffer);
}

export class LiveSession {
    constructor({ token, model, systemInstruction, onTranscript, onState }) {
        Object.assign(this, { token, model, systemInstruction, onTranscript, onState });
        this.playHead = 0; this.closed = false;
    }

    async start() {
        this.onState?.('connecting');
        this.stream = await navigator.mediaDevices.getUserMedia({ audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true } });
        this.inCtx = new AudioContext({ sampleRate: 16000 });
        this.outCtx = new AudioContext({ sampleRate: 24000 });
        this.ws = new WebSocket(`${WS}?access_token=${encodeURIComponent(this.token)}`);
        this.ws.onopen = () => {
            this.ws.send(JSON.stringify({ setup: {
                model: 'models/' + this.model,
                generationConfig: { responseModalities: ['AUDIO'] },
                systemInstruction: { parts: [{ text: this.systemInstruction }] },
                inputAudioTranscription: {}, outputAudioTranscription: {},
            } }));
        };
        this.ws.onmessage = async (ev) => {
            const text = typeof ev.data === 'string' ? ev.data : await ev.data.text();
            const msg = JSON.parse(text);
            if (msg.setupComplete) { this.onState?.('listening'); this.startMic(); }
            const sc = msg.serverContent;
            if (!sc) return;
            sc.modelTurn?.parts?.forEach((p) => { if (p.inlineData?.data) this.play(int16FromB64(p.inlineData.data)); });
            if (sc.inputTranscription?.text) this.onTranscript?.('user', sc.inputTranscription.text);
            if (sc.outputTranscription?.text) this.onTranscript?.('assistant', sc.outputTranscription.text);
            if (sc.interrupted) { this.playHead = this.outCtx.currentTime; }
        };
        this.ws.onerror = () => this.onState?.('error');
        this.ws.onclose = () => { if (!this.closed) this.onState?.('ended'); this.stop(); };
    }

    startMic() {
        const src = this.inCtx.createMediaStreamSource(this.stream);
        // ScriptProcessor keeps us inside a strict CSP (no worklet module needed).
        this.proc = this.inCtx.createScriptProcessor(2048, 1, 1);
        this.proc.onaudioprocess = (e) => {
            if (this.ws?.readyState !== 1) return;
            const f = e.inputBuffer.getChannelData(0); const out = new Int16Array(f.length);
            for (let i = 0; i < f.length; i++) out[i] = Math.max(-1, Math.min(1, f[i])) * 0x7fff;
            this.ws.send(JSON.stringify({ realtimeInput: { audio: { data: b64FromInt16(out), mimeType: 'audio/pcm;rate=16000' } } }));
        };
        src.connect(this.proc); this.proc.connect(this.inCtx.destination);
    }

    play(int16) {
        const f = new Float32Array(int16.length);
        for (let i = 0; i < int16.length; i++) f[i] = int16[i] / 0x8000;
        const buf = this.outCtx.createBuffer(1, f.length, 24000); buf.copyToChannel(f, 0);
        const node = this.outCtx.createBufferSource(); node.buffer = buf; node.connect(this.outCtx.destination);
        this.playHead = Math.max(this.playHead, this.outCtx.currentTime); node.start(this.playHead); this.playHead += buf.duration;
        this.onState?.('speaking');
        clearTimeout(this.speakTimer);
        this.speakTimer = setTimeout(() => !this.closed && this.onState?.('listening'), (this.playHead - this.outCtx.currentTime) * 1000 + 200);
    }

    stop() {
        this.closed = true;
        try { this.proc?.disconnect(); } catch {}
        this.stream?.getTracks().forEach((t) => t.stop());
        try { this.ws?.close(); } catch {}
        this.inCtx?.close().catch(() => {}); this.outCtx?.close().catch(() => {});
    }
}
