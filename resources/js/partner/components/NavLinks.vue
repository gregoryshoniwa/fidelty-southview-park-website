<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { ListChecks, MessagesSquare, Megaphone, Landmark, ReceiptText, ShieldAlert, Download } from 'lucide-vue-next';
import { usePortal } from '../store.js';

const emit = defineEmits(['navigate']);
const portal = usePortal();
const route = useRoute();

const all = [
    { module: 'queue', label: 'Queue', to: '/', icon: ListChecks, match: (n) => n === 'queue' || n === 'request' },
    { module: 'messages', label: 'Messages', to: '/messages', icon: MessagesSquare, match: (n) => n === 'messages', badge: 'unread_messages' },
    { module: 'broadcast', label: 'Broadcast', to: '/broadcast', icon: Megaphone, match: (n) => n === 'broadcast' },
    { module: 'settlements', label: 'Settlements', to: '/settlements', icon: Landmark, match: (n) => n === 'settlements' },
    { module: 'invoices', label: 'Invoices', to: '/invoices', icon: ReceiptText, match: (n) => n === 'invoices' },
    { module: 'incidents', label: 'Incidents', to: '/incidents', icon: ShieldAlert, match: (n) => n === 'incidents' },
    { module: 'export', label: 'Export', to: '/export', icon: Download, match: (n) => n === 'export' },
];
// The queue page doubles as the dashboard, so it is always reachable.
const items = computed(() => all.filter((i) => i.module === 'queue' || portal.has(i.module)));
</script>
<template>
    <nav aria-label="Partner portal">
        <ul class="flex flex-col gap-1">
            <li v-for="item in items" :key="item.module">
                <RouterLink
                    :to="item.to"
                    class="flex min-h-11 items-center gap-3 rounded-[6px] px-3 text-[15px] font-bold transition-colors"
                    :class="item.match(route.name) ? 'bg-forest-700 text-cream' : 'text-cream/75 hover:bg-forest-800 hover:text-cream'"
                    :aria-current="item.match(route.name) ? 'page' : undefined"
                    @click="emit('navigate')"
                >
                    <component :is="item.icon" class="size-5 shrink-0" :class="item.match(route.name) ? 'text-gold-400' : ''" aria-hidden="true" />
                    <span class="flex-1">{{ item.label }}</span>
                    <span v-if="item.badge && portal.stats?.[item.badge]" class="min-w-6 rounded-[4px] bg-gold-500 px-1.5 py-0.5 text-center text-xs font-extrabold text-ink">
                        {{ portal.stats[item.badge] }}<span class="sr-only"> unread</span>
                    </span>
                </RouterLink>
            </li>
        </ul>
    </nav>
</template>
