<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Bell } from 'lucide-vue-next';
import { api, fmt } from '@/shared/api.js';
import Skeleton from '@/shared/Skeleton.vue';
import EmptyState from '@/shared/EmptyState.vue';
import { useAuth } from '../store.js';
const items = ref(null); const auth = useAuth(); const router = useRouter();
onMounted(async () => { items.value = (await api('/notifications')).data; await api('/notifications/read', { method: 'POST', quiet: true }); auth.unread = 0; });
function go(n) { if (n.link?.startsWith('/app/')) router.push(n.link.slice(4)); else if (n.link) location.href = n.link; }
</script>
<template>
    <Skeleton v-if="!items" />
    <EmptyState v-else-if="!items.length" :icon="Bell" title="You are all caught up" text="Updates on your requests, messages and payments appear here and by SMS." />
    <div v-else class="card divide-y divide-line">
        <button v-for="n in items" :key="n.id" class="flex w-full gap-3 p-4 text-left hover:bg-cream" @click="go(n)">
            <span class="mt-1.5 size-2.5 shrink-0 rounded-full" :class="n.read ? 'bg-line' : 'bg-gold-500'" />
            <span class="flex-1"><span class="block font-bold text-forest-900">{{ n.title }}</span><span v-if="n.body" class="text-sm text-muted">{{ n.body }}</span><span class="mt-0.5 block text-xs text-muted">{{ fmt.ago(n.at) }}</span></span>
        </button>
    </div>
</template>
