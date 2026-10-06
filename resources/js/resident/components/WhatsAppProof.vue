<script setup>
// "Verify with WhatsApp": the resident sends VERIFY <code> from their phone; we poll until it arrives.
import { ref, onBeforeUnmount } from 'vue';
import { MessageCircle, Loader2, Copy, RotateCw } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { api } from '@/shared/api.js';

const props = defineProps({ startPath: { type: String, required: true }, label: { type: String, default: 'Verify with WhatsApp' } });
const emit = defineEmits(['done', 'needs-details', 'error']);

const challenge = ref(null); const state = ref('idle'); // idle | waiting | expired
const busy = ref(false);
let timer = null;

async function start() {
    busy.value = true;
    try {
        challenge.value = await api(props.startPath, { method: 'POST' });
        state.value = 'waiting';
        poll();
    } catch (e) { emit('error', e); } finally { busy.value = false; }
}

function poll() {
    clearTimeout(timer);
    timer = setTimeout(check, 3000);
}
async function check() {
    if (!challenge.value || state.value !== 'waiting') return;
    try {
        const r = await api('/phone/whatsapp/' + challenge.value.id, { quiet: true });
        if (r.status === 'pending') return poll();
        if (r.status === 'expired') { state.value = 'expired'; return; }
        state.value = 'idle';
        if (r.status === 'needs_details') emit('needs-details', r);
        else emit('done', r.user);
    } catch (e) {
        state.value = 'idle';
        emit('error', e);
    }
}
const onVisible = () => { if (document.visibilityState === 'visible' && state.value === 'waiting') check(); };
document.addEventListener('visibilitychange', onVisible);
onBeforeUnmount(() => { clearTimeout(timer); document.removeEventListener('visibilitychange', onVisible); });

async function copy() {
    try { await navigator.clipboard.writeText('VERIFY ' + challenge.value.code); toast.success('Copied'); } catch {}
}
</script>

<template>
    <div>
        <button v-if="state === 'idle'" type="button" class="btn w-full bg-[#25D366] text-[#073b1f] hover:bg-[#1fb757]" :disabled="busy" @click="start">
            <Loader2 v-if="busy" class="size-4 animate-spin" /><MessageCircle v-else class="size-4" />{{ label }}
        </button>

        <div v-else class="flex flex-col gap-3 rounded-[10px] border border-line bg-white p-4">
            <template v-if="state === 'waiting'">
                <p class="text-sm text-ink">Send this message from <b>your own WhatsApp</b> to <b class="whitespace-nowrap">{{ challenge.number }}</b>:</p>
                <div class="flex items-center justify-between gap-3 rounded-[8px] bg-forest-100 px-4 py-3">
                    <span class="font-mono text-xl font-bold tracking-wider text-forest-900">VERIFY {{ challenge.code }}</span>
                    <button type="button" class="text-forest-700" aria-label="Copy message" @click="copy"><Copy class="size-4" /></button>
                </div>
                <a :href="challenge.link" target="_blank" rel="noopener" class="btn w-full bg-[#25D366] text-[#073b1f] hover:bg-[#1fb757]"><MessageCircle class="size-4" />Open WhatsApp and send</a>
                <p class="flex items-center gap-2 text-xs text-muted" role="status"><Loader2 class="size-3.5 animate-spin" />Waiting for your message. This page continues by itself.</p>
            </template>
            <template v-else>
                <p class="text-sm text-ink">That code expired before your message arrived.</p>
                <button type="button" class="btn btn-quiet self-start" @click="start"><RotateCw class="size-4" />Get a new code</button>
            </template>
        </div>
    </div>
</template>
