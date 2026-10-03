<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '@/shared/api.js';
import Skeleton from '@/shared/Skeleton.vue';
import StatusChip from '@/shared/StatusChip.vue';
import Thread from '@/shared/Thread.vue';
const route = useRoute(); const t = ref(null); const busy = ref(false); let timer;
async function load() { t.value = (await api('/threads/' + route.params.ref, { quiet: true })).data; }
onMounted(() => { load(); timer = setInterval(() => document.visibilityState === 'visible' && load(), 20000); });
onUnmounted(() => clearInterval(timer));
async function send(text, clear) { busy.value = true; try { t.value = (await api(`/threads/${t.value.reference}/messages`, { method: 'POST', body: { body: text } })).data; clear(); } finally { busy.value = false; } }
</script>
<template>
    <Skeleton v-if="!t" />
    <div v-else class="card flex h-[calc(100dvh-11rem)] flex-col overflow-hidden lg:h-[calc(100dvh-8rem)]">
        <div class="flex items-center gap-3 border-b border-line px-5 py-3">
            <div class="min-w-0 flex-1"><p class="truncate font-bold text-forest-900">{{ t.subject }}</p><p class="text-xs text-muted">With {{ t.with }} · {{ t.reference }}</p></div><StatusChip :status="t.status" />
        </div>
        <Thread :messages="t.messages" :busy="busy" :closed="t.status === 'closed'" @send="send" />
    </div>
</template>
