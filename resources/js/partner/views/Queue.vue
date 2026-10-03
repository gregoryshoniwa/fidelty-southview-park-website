<script setup>
import { ref, watch, onMounted, onBeforeUnmount, computed } from 'vue';
import { useRouter } from 'vue-router';
import { Search, Inbox, FileText, ChevronRight } from 'lucide-vue-next';
import StatusChip from '@/shared/StatusChip.vue';
import EmptyState from '@/shared/EmptyState.vue';
import { papi, fmt, statusLabel } from '../http.js';
import { usePortal } from '../store.js';
import PageHeader from '../components/PageHeader.vue';
import Pager from '../components/Pager.vue';

const portal = usePortal();
const router = useRouter();

const stats = computed(() => portal.stats);
const cards = [
    { key: 'open', label: 'Open requests', status: '' },
    { key: 'waiting_resident', label: 'Waiting on resident', status: 'waiting_resident' },
    { key: 'waiting_payment', label: 'Waiting on payment', status: 'waiting_payment' },
    { key: 'waiting_partner', label: 'With you', status: 'waiting_partner' },
    { key: 'closed_30d', label: 'Closed in 30 days', status: 'closed' },
];
const filters = [
    { value: '', label: 'All active' },
    { value: 'open', label: 'Open' },
    { value: 'waiting_partner', label: 'With you' },
    { value: 'waiting_resident', label: 'Waiting on resident' },
    { value: 'waiting_payment', label: 'Waiting on payment' },
    { value: 'approved', label: 'Approved' },
    { value: 'closed', label: 'Closed' },
    { value: 'cancelled', label: 'Cancelled' },
];

const hasQueue = computed(() => portal.has('queue'));
const status = ref('');
const q = ref('');
const page = ref(1);
const rows = ref([]);
const meta = ref(null);
const loading = ref(true);
let seq = 0;
let debounce = null;

async function load() {
    if (!hasQueue.value) { loading.value = false; return; }
    const mine = ++seq;
    loading.value = true;
    const params = new URLSearchParams();
    if (status.value) params.set('status', status.value);
    if (q.value.trim()) params.set('q', q.value.trim());
    if (page.value > 1) params.set('page', page.value);
    try {
        const res = await papi('/requests' + (params.toString() ? '?' + params : ''));
        if (mine !== seq) return;
        rows.value = res.data || [];
        meta.value = res.meta || null;
    } catch (_) {
        if (mine === seq) rows.value = [];
    } finally {
        if (mine === seq) loading.value = false;
    }
}

function setStatus(s) { status.value = s; page.value = 1; load(); }
function goPage(p) { page.value = p; load(); }
watch(q, () => { clearTimeout(debounce); debounce = setTimeout(() => { page.value = 1; load(); }, 350); });
onMounted(load);
onBeforeUnmount(() => clearTimeout(debounce));

const open = (r) => router.push({ name: 'request', params: { ref: r.reference } });
const stepText = (r) => r.steps?.length ? `Step ${r.step} of ${r.steps.length}` : '';
</script>

<template>
    <div class="animate-fade">
        <PageHeader eyebrow="Dashboard" title="Request queue" text="Requests from Southview residents routed to your organisation. Newest activity first." />

        <section aria-label="Summary" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
            <component :is="hasQueue ? 'button' : 'div'" v-for="c in cards" :key="c.key" :type="hasQueue ? 'button' : undefined"
                       class="card p-4 text-left" :class="[hasQueue ? 'card-hover' : '', hasQueue && status === c.status ? 'border-gold-500' : '']"
                       @click="hasQueue && setStatus(c.status)">
                <p class="eyebrow text-[10px] text-muted">{{ c.label }}</p>
                <p v-if="stats" class="mt-2 font-serif text-3xl font-bold text-forest-900">{{ stats[c.key] ?? 0 }}</p>
                <div v-else class="skeleton mt-2 h-9 w-12" />
            </component>
        </section>

        <section v-if="hasQueue" class="mt-8" aria-labelledby="queue-h">
            <h2 id="queue-h" class="sr-only">Requests</h2>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="relative w-full lg:max-w-sm">
                    <label for="queue-search" class="sr-only">Search requests</label>
                    <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
                    <input id="queue-search" v-model="q" type="search" class="input pl-10" placeholder="Reference, stand or resident name" autocomplete="off" />
                </div>
                <div class="-mx-4 overflow-x-auto px-4 lg:mx-0 lg:px-0" role="group" aria-label="Filter by status">
                    <div class="flex gap-1.5">
                        <button v-for="f in filters" :key="f.value" type="button"
                                class="min-h-9 shrink-0 whitespace-nowrap rounded-[6px] border px-3 text-sm font-bold transition-colors"
                                :class="status === f.value ? 'border-forest-700 bg-forest-700 text-cream' : 'border-line bg-white text-forest-700 hover:border-forest-700'"
                                :aria-pressed="status === f.value" @click="setStatus(f.value)">{{ f.label }}</button>
                    </div>
                </div>
            </div>

            <div class="mt-4" aria-live="polite" :aria-busy="loading">
                <div v-if="loading && !rows.length" class="flex flex-col gap-2">
                    <div v-for="i in 6" :key="i" class="skeleton h-14 w-full" />
                </div>
                <EmptyState v-else-if="!rows.length" :icon="Inbox" title="No requests here"
                            :text="q ? 'Nothing matches your search. Try a reference, stand number or surname.' : 'When residents send requests to you, they will appear in this queue.'" />
                <template v-else>
                    <!-- Desktop table -->
                    <div class="card hidden overflow-x-auto md:block" :class="loading ? 'opacity-60' : ''">
                        <table class="w-full min-w-[860px] text-left text-sm">
                            <thead class="border-b border-line bg-cream text-xs uppercase tracking-wider text-muted">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-extrabold">Reference</th>
                                    <th scope="col" class="px-4 py-3 font-extrabold">Resident</th>
                                    <th scope="col" class="px-4 py-3 font-extrabold">Stand</th>
                                    <th scope="col" class="px-4 py-3 font-extrabold">Service</th>
                                    <th scope="col" class="px-4 py-3 font-extrabold">Step</th>
                                    <th scope="col" class="px-4 py-3 font-extrabold">Status</th>
                                    <th scope="col" class="px-4 py-3 text-center font-extrabold">Docs</th>
                                    <th scope="col" class="px-4 py-3 font-extrabold">Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in rows" :key="r.reference" class="cursor-pointer border-b border-line last:border-0 hover:bg-forest-50" @click="open(r)">
                                    <td class="px-4 py-3">
                                        <RouterLink :to="{ name: 'request', params: { ref: r.reference } }" class="font-mono font-bold text-forest-700 underline-offset-2 hover:underline" @click.stop>{{ r.reference }}</RouterLink>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-ink">{{ r.resident?.name }}</td>
                                    <td class="px-4 py-3 text-muted">{{ r.resident?.stand || '-' }}</td>
                                    <td class="px-4 py-3">{{ r.service?.name }}</td>
                                    <td class="px-4 py-3">
                                        <span class="block font-semibold text-ink">{{ r.step_label || '-' }}</span>
                                        <span class="text-xs text-muted">{{ stepText(r) }}</span>
                                    </td>
                                    <td class="px-4 py-3"><StatusChip :status="r.status" :label="statusLabel(r.status)" /></td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center gap-1 text-muted"><FileText class="size-4" aria-hidden="true" />{{ r.documents_count }}<span class="sr-only"> documents</span></span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-muted"><time :datetime="r.updated_at" :title="fmt.datetime(r.updated_at)">{{ fmt.ago(r.updated_at) }}</time></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile cards -->
                    <ul class="flex flex-col gap-2 md:hidden" :class="loading ? 'opacity-60' : ''">
                        <li v-for="r in rows" :key="r.reference">
                            <RouterLink :to="{ name: 'request', params: { ref: r.reference } }" class="card card-hover flex items-start gap-3 p-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-sm font-bold text-forest-700">{{ r.reference }}</span>
                                        <StatusChip :status="r.status" :label="statusLabel(r.status)" />
                                    </div>
                                    <p class="mt-1.5 font-bold text-ink">{{ r.resident?.name }} <span class="font-normal text-muted">· Stand {{ r.resident?.stand || '-' }}</span></p>
                                    <p class="text-sm text-muted">{{ r.service?.name }}</p>
                                    <p class="mt-1 text-xs text-muted">{{ stepText(r) }}<template v-if="r.step_label">: {{ r.step_label }}</template> · {{ r.documents_count }} docs · {{ fmt.ago(r.updated_at) }}</p>
                                </div>
                                <ChevronRight class="mt-1 size-5 shrink-0 text-muted" aria-hidden="true" />
                            </RouterLink>
                        </li>
                    </ul>
                    <Pager :meta="meta" @page="goPage" />
                </template>
            </div>
        </section>
        <p v-else class="mt-8 text-sm text-muted">The request queue is not enabled for your organisation. Use the menu to open the modules you have.</p>
    </div>
</template>
