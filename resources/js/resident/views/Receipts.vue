<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { toast } from 'vue-sonner';
import { Receipt, Download } from 'lucide-vue-next';
import { api, download, fmt } from '@/shared/api.js';
import Skeleton from '@/shared/Skeleton.vue';
import EmptyState from '@/shared/EmptyState.vue';
import StatusChip from '@/shared/StatusChip.vue';
const route = useRoute(); const items = ref(null);
onMounted(async () => {
    items.value = (await api('/payments')).data;
    const paid = items.value.find((p) => p.id === route.query.paid);
    if (paid) paid.status === 'paid' ? toast.success('Payment received. Your receipt is ready.') : toast.info('Payment status: ' + paid.status);
});
</script>
<template>
    <Skeleton v-if="!items" />
    <EmptyState v-else-if="!items.length" :icon="Receipt" title="No receipts yet" text="When you pay through the platform, every receipt is saved here." />
    <div v-else class="card divide-y divide-line">
        <div v-for="p in items" :key="p.id" class="flex items-center gap-3 p-4">
            <span class="flex-1"><span class="block font-bold text-forest-900">{{ p.biller_label }}</span><span class="text-xs text-muted">{{ p.reference }} · {{ fmt.datetime(p.paid_at || p.created_at) }}</span><span v-if="p.token" class="mt-1 block font-mono text-sm font-bold text-forest-700">Token {{ p.token }}</span></span>
            <span class="text-right"><span class="block font-extrabold text-forest-900">{{ fmt.money(p.total, p.currency) }}</span><StatusChip :status="p.status" /></span>
            <button v-if="p.status === 'paid'" class="btn btn-sm btn-quiet" aria-label="Download receipt" @click="download('/payments/' + p.id + '/receipt', 'receipt-' + p.id + '.pdf')"><Download class="size-4" /></button>
        </div>
    </div>
</template>
