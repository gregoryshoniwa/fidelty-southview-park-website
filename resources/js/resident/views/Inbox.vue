<script setup>
import { ref, onMounted } from 'vue';
import { MessageSquare, PenSquare } from 'lucide-vue-next';
import { api, fmt } from '@/shared/api.js';
import Skeleton from '@/shared/Skeleton.vue';
import EmptyState from '@/shared/EmptyState.vue';
import StatusChip from '@/shared/StatusChip.vue';
const items = ref(null);
onMounted(async () => { items.value = (await api('/threads')).data; });
</script>
<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-3"><p class="text-sm text-muted">Private conversations with the committee and with partners on your requests.</p><RouterLink to="/inbox/new" class="btn btn-gold btn-sm shrink-0"><PenSquare class="size-4" />New</RouterLink></div>
        <Skeleton v-if="!items" />
        <EmptyState v-else-if="!items.length" :icon="MessageSquare" title="No messages yet" text="Write to the committee privately. You get a reference number and a reply within 48 hours." />
        <div v-else class="card divide-y divide-line">
            <RouterLink v-for="t in items" :key="t.reference" :to="'/inbox/' + t.reference" class="flex items-center gap-3 p-4 hover:bg-cream">
                <img v-if="t.partner_logo" :src="t.partner_logo" alt="" class="size-10 rounded-[8px] border border-line bg-white object-contain p-1">
                <span v-else class="flex size-10 items-center justify-center rounded-[8px] bg-forest-100 text-forest-700"><MessageSquare class="size-5" /></span>
                <span class="min-w-0 flex-1"><span class="block truncate font-bold text-forest-900">{{ t.subject }}</span><span class="text-xs text-muted">{{ t.with }} · {{ t.reference }} · {{ fmt.ago(t.last_message_at) }}</span></span>
                <span v-if="t.unread" class="flex size-6 items-center justify-center rounded-full bg-gold-500 text-xs font-extrabold text-forest-900">{{ t.unread }}</span>
                <StatusChip v-else :status="t.status" />
            </RouterLink>
        </div>
    </div>
</template>
