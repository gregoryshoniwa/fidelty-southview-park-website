<script setup>
import { ref, onMounted } from 'vue';
import { ClipboardList } from 'lucide-vue-next';
import { api, fmt } from '@/shared/api.js';
import Skeleton from '@/shared/Skeleton.vue';
import EmptyState from '@/shared/EmptyState.vue';
import StatusChip from '@/shared/StatusChip.vue';
const items = ref(null);
onMounted(async () => { items.value = (await api('/requests')).data; });
</script>
<template>
    <Skeleton v-if="!items" />
    <EmptyState v-else-if="!items.length" :icon="ClipboardList" title="No requests yet" text="Requests you open, like your deed file or a replacement agreement, appear here." />
    <div v-else class="card divide-y divide-line">
        <RouterLink v-for="r in items" :key="r.reference" :to="'/requests/' + r.reference" class="flex items-center gap-3 p-4 hover:bg-cream">
            <span class="flex-1"><span class="block font-bold text-forest-900">{{ r.service.name }}</span><span class="text-xs text-muted">{{ r.reference }} · {{ r.partner?.name }} · {{ fmt.ago(r.updated_at) }}</span></span>
            <StatusChip :status="r.status" :label="r.status_label" />
        </RouterLink>
    </div>
</template>
