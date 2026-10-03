<script setup>
import { ref, onMounted, inject } from 'vue';
import { GraduationCap } from 'lucide-vue-next';
import { api, fmt } from '@/shared/api.js';
import Skeleton from '@/shared/Skeleton.vue';
import EmptyState from '@/shared/EmptyState.vue';
import StatusChip from '@/shared/StatusChip.vue';
const live = inject('paymentsLive'); const items = ref(null);
onMounted(async () => { items.value = (await api('/school-invoices')).data; });
async function pay(i) { const r = await api(`/school-invoices/${i.id}/pay`, { method: 'POST' }); location.href = r.checkout_url; }
</script>
<template>
    <div class="flex flex-col gap-4">
        <p class="text-sm text-muted">Invoices from partner schools for your children appear here. Schools near the estate are listed in <RouterLink to="/community" class="font-bold text-forest-700 underline">Community</RouterLink>; none has signed up yet.</p>
        <Skeleton v-if="!items" />
        <EmptyState v-else-if="!items.length" :icon="GraduationCap" title="No school invoices" text="When your child's school joins, its invoices, reports and notices will appear here." />
        <div v-else class="card divide-y divide-line">
            <div v-for="i in items" :key="i.id" class="flex items-center gap-3 p-4"><span class="flex-1"><span class="block font-bold text-forest-900">{{ i.school }}: {{ i.learner }}</span><span class="text-xs text-muted">{{ i.description }} · due {{ fmt.date(i.due_on) }}</span></span><span class="font-extrabold">{{ fmt.money(i.amount, i.currency) }}</span>
                <button v-if="i.status !== 'paid' && live" class="btn btn-gold btn-sm" @click="pay(i)">Pay</button><StatusChip v-else :status="i.status" :label="i.status !== 'paid' && !live ? 'Pay online soon' : undefined" /></div>
        </div>
    </div>
</template>
