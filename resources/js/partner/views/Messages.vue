<script setup>
import { ref, watch, onMounted } from 'vue';
import { ArrowLeft, MessagesSquare, MessageCircle } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import EmptyState from '@/shared/EmptyState.vue';
import StatusChip from '@/shared/StatusChip.vue';
import Thread from '@/shared/Thread.vue';
import { papi, fmt, ApiError } from '../http.js';
import { usePortal } from '../store.js';
import PageHeader from '../components/PageHeader.vue';

const props = defineProps({ reference: { type: String, default: '' } });
const portal = usePortal();

const threads = ref([]);
const loading = ref(true);
const current = ref(null);
const currentLoading = ref(false);
const sending = ref(false);
let seq = 0;

async function loadList() {
    try { threads.value = (await papi('/threads')).data || []; } catch (_) { /* toast shown */ } finally { loading.value = false; }
}

async function loadThread(refId) {
    const mine = ++seq;
    if (!refId) { current.value = null; return; }
    currentLoading.value = true;
    try {
        const res = await papi('/threads/' + encodeURIComponent(refId));
        if (mine !== seq) return;
        current.value = res.data;
        const row = threads.value.find((t) => t.reference === refId);
        if (row) row.unread = 0;
        portal.loadStats();
    } catch (e) {
        if (mine === seq) current.value = null;
    } finally {
        if (mine === seq) currentLoading.value = false;
    }
}

async function reply(text, clear) {
    sending.value = true;
    try {
        const res = await papi('/threads/' + encodeURIComponent(current.value.reference) + '/messages', { method: 'POST', body: { body: text } });
        current.value = res.data;
        clear();
        const row = threads.value.find((t) => t.reference === res.data.reference);
        if (row) { row.last_message_at = res.data.last_message_at; row.status = res.data.status; }
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) toast.error(e.first('body') || e.message);
    } finally {
        sending.value = false;
    }
}

watch(() => props.reference, loadThread);
onMounted(() => { loadList(); loadThread(props.reference); });
</script>

<template>
    <div class="animate-fade">
        <div :class="reference ? 'hidden lg:block' : ''"><PageHeader eyebrow="Inbox" title="Messages" text="Conversations with residents about their requests." /></div>

        <div class="grid gap-4 lg:grid-cols-[minmax(280px,360px)_1fr]">
            <!-- List -->
            <section aria-label="Conversations" :class="reference ? 'hidden lg:block' : ''">
                <div v-if="loading" class="flex flex-col gap-2"><div v-for="i in 5" :key="i" class="skeleton h-20 w-full" /></div>
                <EmptyState v-else-if="!threads.length" :icon="MessagesSquare" title="No conversations yet" text="Start one from a request page, or wait for a resident to write to you." />
                <ul v-else class="card divide-y divide-line overflow-hidden lg:max-h-[calc(100dvh-12rem)] lg:overflow-y-auto">
                    <li v-for="t in threads" :key="t.reference">
                        <RouterLink :to="{ name: 'messages', params: { ref: t.reference } }" class="flex gap-3 px-4 py-3.5 transition-colors"
                                    :class="t.reference === reference ? 'bg-forest-50 shadow-[inset_3px_0_0_var(--color-gold-500)]' : 'hover:bg-cream'"
                                    :aria-current="t.reference === reference ? 'page' : undefined">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate font-bold text-ink">{{ t.resident?.name }}</p>
                                    <time class="shrink-0 text-xs text-muted" :datetime="t.last_message_at">{{ fmt.ago(t.last_message_at) }}</time>
                                </div>
                                <p class="text-xs text-muted">Stand {{ t.resident?.stand || '-' }}<template v-if="t.request"> · {{ t.request }}</template></p>
                                <div class="mt-1 flex items-center justify-between gap-2">
                                    <p class="truncate text-sm" :class="t.unread ? 'font-bold text-forest-900' : 'text-muted'">{{ t.subject }}</p>
                                    <span v-if="t.unread" class="min-w-6 shrink-0 rounded-[4px] bg-gold-500 px-1.5 py-0.5 text-center text-xs font-extrabold text-ink">{{ t.unread }}<span class="sr-only"> unread</span></span>
                                </div>
                            </div>
                        </RouterLink>
                    </li>
                </ul>
            </section>

            <!-- Detail -->
            <section class="card min-h-[460px] flex-col overflow-hidden lg:h-[calc(100dvh-12rem)]" :class="reference ? 'flex' : 'hidden lg:flex'" aria-label="Conversation">
                <template v-if="reference">
                    <div class="flex items-start gap-2 border-b border-line px-4 py-3 sm:px-5">
                        <RouterLink :to="{ name: 'messages' }" class="btn btn-quiet btn-sm -ml-2 px-2 lg:hidden" aria-label="Back to conversations"><ArrowLeft class="size-5" aria-hidden="true" /></RouterLink>
                        <div v-if="current" class="min-w-0 flex-1">
                            <h2 class="truncate font-serif text-xl font-bold text-forest-900">{{ current.resident?.name }}</h2>
                            <p class="truncate text-sm text-muted">
                                Stand {{ current.resident?.stand || '-' }} · {{ current.subject }}
                                <template v-if="current.request && portal.has('queue')"> · <RouterLink :to="{ name: 'request', params: { ref: current.request } }" class="font-bold text-forest-700 underline decoration-gold-500 underline-offset-2">{{ current.request }}</RouterLink></template>
                            </p>
                        </div>
                        <div v-else class="skeleton h-10 flex-1" />
                        <StatusChip v-if="current" :status="current.status" />
                    </div>
                    <div v-if="currentLoading && !current" class="flex-1 p-5"><div class="skeleton h-40 w-full" /></div>
                    <Thread v-else-if="current" :messages="current.messages || []" :busy="sending" :closed="current.status === 'closed'" placeholder="Reply to the resident" @send="reply" />
                    <p v-else class="m-auto p-6 text-sm text-muted">This conversation could not be loaded.</p>
                </template>
                <div v-else class="m-auto flex flex-col items-center gap-2 p-6 text-center text-muted">
                    <MessageCircle class="size-8 text-forest-700" aria-hidden="true" />
                    <p class="text-sm">Choose a conversation to read and reply.</p>
                </div>
            </section>
        </div>
    </div>
</template>
