<script setup>
import { ref, computed, onMounted } from 'vue';
import { Landmark } from 'lucide-vue-next';
import EmptyState from '@/shared/EmptyState.vue';
import StatusChip from '@/shared/StatusChip.vue';
import { papi, fmt } from '../http.js';
import PageHeader from '../components/PageHeader.vue';

const res = ref(null);
const loading = ref(true);
const rows = computed(() => res.value?.data || []);
// Totals per currency (the API total mixes currencies, so we split it here).
const byCurrency = computed(() => {
    const out = {};
    for (const p of rows.value) out[p.currency] = (out[p.currency] || 0) + Number(p.amount || 0);
    return out;
});

onMounted(async () => {
    try { res.value = await papi('/settlements'); } catch (_) { /* toast shown */ } finally { loading.value = false; }
});
</script>

<template>
    <div class="animate-fade">
        <PageHeader eyebrow="Settlements" title="Payments received" text="Paid transactions routed to your organisation through the association platform. Latest 200 shown." />

        <section aria-label="Totals" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="card p-4">
                <p class="eyebrow text-[10px] text-muted">Payments</p>
                <p v-if="!loading" class="mt-2 font-serif text-3xl font-bold text-forest-900">{{ res?.count ?? 0 }}</p>
                <div v-else class="skeleton mt-2 h-9 w-12" />
            </div>
            <template v-if="!loading">
                <div v-for="(sum, cur) in byCurrency" :key="cur" class="card p-4">
                    <p class="eyebrow text-[10px] text-muted">Total {{ cur }}</p>
                    <p class="mt-2 font-serif text-3xl font-bold text-forest-900">{{ fmt.money(sum, cur) }}</p>
                </div>
            </template>
            <div v-else class="card p-4"><div class="skeleton h-14 w-full" /></div>
        </section>

        <div class="mt-6">
            <div v-if="loading" class="flex flex-col gap-2"><div v-for="i in 5" :key="i" class="skeleton h-12 w-full" /></div>
            <EmptyState v-else-if="!rows.length" :icon="Landmark" title="No settled payments yet" text="Paid transactions will appear here once residents pay through the platform." />
            <div v-else class="card overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b border-line bg-cream text-xs uppercase tracking-wider text-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-extrabold">Paid</th>
                            <th scope="col" class="px-4 py-3 font-extrabold">Biller</th>
                            <th scope="col" class="px-4 py-3 font-extrabold">Reference</th>
                            <th scope="col" class="px-4 py-3 text-right font-extrabold">Amount</th>
                            <th scope="col" class="px-4 py-3 text-right font-extrabold">Fee</th>
                            <th scope="col" class="px-4 py-3 font-extrabold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in rows" :key="p.id" class="border-b border-line last:border-0">
                            <td class="whitespace-nowrap px-4 py-3 text-muted">{{ fmt.datetime(p.paid_at) }}</td>
                            <td class="px-4 py-3">{{ p.biller_label }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ p.reference }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-bold text-ink">{{ fmt.money(p.amount, p.currency) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-muted">{{ fmt.money(p.platform_fee, p.currency) }}</td>
                            <td class="px-4 py-3"><StatusChip :status="p.status" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
