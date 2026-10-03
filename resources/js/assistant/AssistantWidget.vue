<script setup>
import { ref, nextTick, onMounted } from 'vue';
import { DialogRoot, DialogPortal, DialogOverlay, DialogContent, DialogTitle, DialogDescription, DialogClose } from 'reka-ui';
import { MessageCircleQuestion, X, Send, Mic, MicOff, UserRound, Sparkles, Loader2 } from 'lucide-vue-next';
import { api } from '@/shared/api.js';

const props = defineProps({ signedIn: Boolean, startOpen: Boolean, bottomOffset: { type: Number, default: 16 } });
const open = ref(props.startOpen);
const input = ref('');
const busy = ref(false);
const sessionId = ref(null);
const voiceAvailable = ref(false);
const voice = ref({ on: false, state: 'idle', consent: false, askConsent: false });
const escalating = ref(false);
const escalated = ref(null);
const log = ref(null);
const messages = ref([{ role: 'assistant', text: "Hi, I'm the Southview assistant, an automated helper. Ask me about a service, a fee or your title deed. I'll pass anything I can't answer to the committee." }]);
let live = null;

onMounted(async () => {
    try { voiceAvailable.value = (await api('/assistant/config', { quiet: true })).voice; } catch {}
});

function scroll() { nextTick(() => { if (log.value) log.value.scrollTop = log.value.scrollHeight; }); }

async function send() {
    const text = input.value.trim();
    if (!text || busy.value) return;
    messages.value.push({ role: 'user', text }); input.value = ''; busy.value = true; scroll();
    try {
        const r = await api('/assistant/chat', { method: 'POST', body: { message: text, session_id: sessionId.value }, quiet: true });
        sessionId.value = r.session_id;
        messages.value.push({ role: 'assistant', text: r.reply, sources: r.sources, escalate: r.escalate_suggested });
    } catch (e) {
        messages.value.push({ role: 'assistant', text: e.status === 429 ? 'You are asking very quickly. Wait a minute and try again.' : 'I could not answer just now. Tap "Talk to a person" to reach the committee.' });
    } finally { busy.value = false; scroll(); }
}

async function escalate() {
    if (!props.signedIn) { location.href = '/app/login?next=/app/inbox/new'; return; }
    escalating.value = true;
    try {
        const lastQ = [...messages.value].reverse().find((m) => m.role === 'user')?.text || 'I need help from the committee.';
        const r = await api('/assistant/escalate', { method: 'POST', body: { session_id: sessionId.value || 'none', summary: lastQ } });
        escalated.value = r.reference;
        messages.value.push({ role: 'assistant', text: `I've passed this to the committee. Your reference is ${r.reference}. They reply in Messages, usually within 48 hours.` });
        scroll();
    } finally { escalating.value = false; }
}

async function startVoice() {
    if (!voice.value.consent) { voice.value.askConsent = true; return; }
    voice.value.on = true; voice.value.state = 'connecting';
    try {
        const t = await api('/assistant/live', { method: 'POST', body: { consent: true }, quiet: true });
        sessionId.value = t.session_id;
        const { LiveSession } = await import('./live.js');
        let lastRole = null;
        live = new LiveSession({
            token: t.token, model: t.model, systemInstruction: t.system_instruction,
            onState: (s) => { voice.value.state = s; if (s === 'error' || s === 'ended') stopVoice(); },
            onTranscript: (role, text) => {
                const last = messages.value[messages.value.length - 1];
                if (lastRole === role && last?.role === role && last.live) last.text += text;
                else messages.value.push({ role, text, live: true });
                lastRole = role; scroll();
            },
        });
        await live.start();
    } catch (e) {
        voice.value.on = false; voice.value.state = 'idle';
        messages.value.push({ role: 'assistant', text: e?.message?.includes('Permission') ? 'Microphone permission was blocked. You can type instead.' : 'Voice is not available right now. You can type instead.' });
        scroll();
    }
}
function stopVoice() {
    live?.stop(); live = null; voice.value.on = false; voice.value.state = 'idle';
    const turns = messages.value.filter((m) => m.live).map((m) => ({ role: m.role, text: m.text.slice(0, 2000) }));
    if (turns.length && sessionId.value) api('/assistant/transcript', { method: 'POST', body: { session_id: sessionId.value, turns }, quiet: true }).catch(() => {});
}
function acceptVoice() { voice.value.consent = true; voice.value.askConsent = false; startVoice(); }
function onOpenChange(v) { open.value = v; if (!v && voice.value.on) stopVoice(); }
</script>

<template>
    <button v-if="!open" type="button" class="fixed right-4 z-50 inline-flex size-14 items-center justify-center gap-2 rounded-full border-[1.5px] border-gold-500 bg-forest-700 sm:size-auto sm:rounded-[8px] sm:px-4 sm:py-3 text-sm font-bold text-cream shadow-lift hover:bg-forest-600 sm:right-6"
        :style="{ bottom: bottomOffset + 'px' }" aria-haspopup="dialog" @click="open = true">
        <MessageCircleQuestion class="size-5 text-gold-400" /><span class="sr-only sm:not-sr-only">Ask the assistant</span>
    </button>
    <DialogRoot :open="open" @update:open="onOpenChange">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-[80] bg-forest-950/40 animate-fade sm:bg-transparent" />
            <DialogContent class="fixed inset-x-0 bottom-0 z-[81] flex h-[86dvh] flex-col overflow-hidden rounded-t-[16px] bg-white shadow-2xl animate-rise focus:outline-none sm:inset-auto sm:bottom-6 sm:right-6 sm:h-[600px] sm:w-[400px] sm:rounded-[14px] sm:border sm:border-line">
                <div class="flex items-center gap-3 bg-forest-900 px-4 py-3 text-cream">
                    <span class="flex size-9 items-center justify-center rounded-[8px] bg-gold-500/20 text-gold-400"><Sparkles class="size-5" /></span>
                    <div class="flex-1"><DialogTitle class="font-serif text-base font-bold">Southview assistant</DialogTitle><DialogDescription class="text-xs text-cream/70">Automated. Answers from our help pages only.</DialogDescription></div>
                    <DialogClose class="inline-flex size-9 items-center justify-center rounded-[6px] hover:bg-white/10" aria-label="Close assistant"><X class="size-5" /></DialogClose>
                </div>
                <div ref="log" class="flex flex-1 flex-col gap-3 overflow-y-auto bg-cream px-4 py-4" role="log" aria-live="polite">
                    <div v-for="(m, i) in messages" :key="i" class="flex flex-col" :class="m.role === 'user' ? 'items-end' : 'items-start'">
                        <div class="max-w-[88%] whitespace-pre-line rounded-[10px] px-3.5 py-2.5 text-[14px] leading-relaxed" :class="m.role === 'user' ? 'rounded-br-[2px] bg-forest-900 text-cream' : 'rounded-bl-[2px] border border-line bg-white'">{{ m.text.replace(/\*\*/g, '') }}</div>
                        <div v-if="m.sources?.length" class="mt-1.5 flex max-w-[88%] flex-wrap gap-1.5">
                            <a v-for="s in m.sources.slice(0, 3)" :key="s.url" :href="s.url" class="rounded-[4px] border border-line bg-white px-2 py-1 text-[11px] font-bold text-forest-700 hover:border-gold-500">{{ s.title }}</a>
                        </div>
                    </div>
                    <div v-if="busy" class="flex items-center gap-2 text-sm text-muted"><Loader2 class="size-4 animate-spin" />Thinking</div>
                    <div v-if="voice.askConsent" class="rounded-[10px] border border-gold-500/50 bg-gold-100 p-3 text-[13px]">
                        <p class="font-bold text-forest-900">Talk instead of typing?</p>
                        <p class="mt-1 text-muted">Your voice is sent to Google's Gemini service to understand and answer you. We keep a text transcript for 90 days to improve answers. Don't say your ID number.</p>
                        <div class="mt-2 flex gap-2"><button class="btn btn-gold btn-sm" @click="acceptVoice">I agree, start</button><button class="btn btn-quiet btn-sm" @click="voice.askConsent = false">Not now</button></div>
                    </div>
                </div>
                <div class="border-t border-line bg-white p-3">
                    <div v-if="voice.on" class="mb-2 flex items-center justify-between rounded-[8px] bg-forest-100 px-3 py-2 text-sm font-bold text-forest-700">
                        <span class="flex items-center gap-2"><span class="size-2.5 animate-pulse rounded-full bg-forest-500" />{{ { connecting: 'Connecting…', listening: 'Listening', speaking: 'Speaking' }[voice.state] || voice.state }}</span>
                        <button class="btn btn-sm bg-white text-forest-900" @click="stopVoice"><MicOff class="size-4" />Stop</button>
                    </div>
                    <form class="flex items-end gap-2" @submit.prevent="send">
                        <label for="assistant-input" class="sr-only">Your question</label>
                        <input id="assistant-input" v-model="input" maxlength="800" autocomplete="off" placeholder="Ask about a service or fee" class="input min-h-11 flex-1" :disabled="voice.on">
                        <button v-if="voiceAvailable && !voice.on" type="button" class="btn btn-outline min-h-11 px-3" aria-label="Talk to the assistant" @click="startVoice"><Mic class="size-4" /></button>
                        <button type="submit" class="btn btn-gold min-h-11 px-3" :disabled="busy || !input.trim()" aria-label="Send"><Send class="size-4" /></button>
                    </form>
                    <button type="button" class="mt-2 flex w-full items-center justify-center gap-1.5 text-xs font-bold text-forest-700 hover:underline disabled:opacity-60" :disabled="escalating || !!escalated" @click="escalate">
                        <UserRound class="size-3.5" />{{ escalated ? 'Sent to the committee' : 'Talk to a person' }}
                    </button>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
