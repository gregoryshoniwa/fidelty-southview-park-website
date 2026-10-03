<script setup>
import { ref, onMounted, computed } from 'vue';
import { FileText, CreditCard, Landmark, MessageSquare, BadgeCheck, ShieldCheck, ChevronRight, Vote, Megaphone } from 'lucide-vue-next';
import { api, fmt } from '@/shared/api.js';
import Skeleton from '@/shared/Skeleton.vue';
import StatusChip from '@/shared/StatusChip.vue';
import { useAuth } from '../store.js';

const auth = useAuth();
const requests = ref(null); const notices = ref(null); const threads = ref([]);
onMounted(async () => {
    const jobs = [api('/notices', { quiet: true }).then((r) => (notices.value = r.data.slice(0, 4))).catch(() => (notices.value = [])), api('/threads', { quiet: true }).then((r) => (threads.value = r.data)).catch(() => {})];
    if (auth.verified) jobs.push(api('/requests', { quiet: true }).then((r) => (requests.value = r.data)).catch(() => (requests.value = [])));
    await Promise.all(jobs);
});
const deed = computed(() => requests.value?.find((r) => r.service.slug === 'title-deed-tracker'));
const unreadThreads = computed(() => threads.value.reduce((a, t) => a + t.unread, 0));
const tiles = [
    { to: '/agreement', icon: FileText, title: 'My Agreement', text: 'Download PDF · payment history', tone: 'bg-forest-100 text-forest-700' },
    { to: '/pay', icon: CreditCard, title: 'Pay bills', text: 'Council · ZESA · airtime', tone: 'bg-forest-100 text-forest-700' },
    { to: '/deed', icon: Landmark, title: 'Deed tracker', text: 'With Marufu Attorneys', tone: 'bg-gold-100 text-gold-600' },
    { to: '/inbox/new', icon: MessageSquare, title: 'Write to us', text: 'Private, with a reference', tone: 'bg-forest-100 text-forest-700' },
];
</script>

<template>
    <div class="flex flex-col gap-6">
        <section class="overflow-hidden rounded-[14px] bg-forest-900 p-5 text-cream sm:p-6">
            <p class="text-sm text-cream/70">Welcome back</p>
            <h2 class="font-serif text-2xl font-bold">{{ auth.user?.name }}</h2>
            <div v-if="auth.verified" class="mt-4 flex items-center justify-between rounded-[10px] border border-gold-500/40 bg-white/5 px-4 py-3">
                <span><span class="eyebrow block text-gold-500">Verified resident</span><span class="font-bold">Stand {{ auth.user.resident.stand }}, Southview Park</span></span>
                <span class="flex size-9 items-center justify-center rounded-full bg-gold-500 text-forest-900"><BadgeCheck class="size-5" /></span>
            </div>
            <RouterLink v-else to="/verify" class="mt-4 flex items-center justify-between gap-3 rounded-[10px] bg-gold-500 px-4 py-3 text-forest-900">
                <span><span class="block font-extrabold">Verify your stand</span><span class="text-sm">Two minutes with Fidelity Life records. Unlocks every service.</span></span><ShieldCheck class="size-6 shrink-0" />
            </RouterLink>
        </section>

        <section v-if="deed" aria-label="Needs attention">
            <RouterLink :to="'/requests/' + deed.reference" class="flex items-center gap-4 rounded-[12px] bg-gold-100 p-4 text-forest-900">
                <span class="flex size-11 items-center justify-center rounded-[8px] bg-gold-500"><Landmark class="size-5" /></span>
                <span class="flex-1"><span class="block font-extrabold">Title deed: step {{ deed.step }} of {{ deed.steps.length }}</span><span class="text-sm text-muted">{{ deed.step_label }}</span></span>
                <ChevronRight class="size-5 text-gold-600" />
            </RouterLink>
        </section>

        <section>
            <h2 class="eyebrow mb-3 text-muted">My services</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <RouterLink v-for="t in tiles" :key="t.to" :to="t.to" class="card card-hover flex flex-col gap-2 p-4">
                    <span class="flex size-10 items-center justify-center rounded-[8px]" :class="t.tone"><component :is="t.icon" class="size-5" /></span>
                    <span class="font-extrabold text-forest-900">{{ t.title }}</span><span class="text-xs text-muted">{{ t.text }}</span>
                </RouterLink>
            </div>
        </section>

        <section v-if="unreadThreads" class="card flex items-center gap-3 p-4">
            <MessageSquare class="size-5 text-forest-700" /><span class="flex-1 font-bold text-forest-900">{{ unreadThreads }} unread message{{ unreadThreads > 1 ? 's' : '' }}</span>
            <RouterLink to="/inbox" class="btn btn-sm btn-outline">Open</RouterLink>
        </section>

        <section>
            <div class="mb-3 flex items-center justify-between"><h2 class="eyebrow text-muted">Latest notices</h2><RouterLink to="/notices" class="text-sm font-bold text-forest-700">All</RouterLink></div>
            <Skeleton v-if="!notices" :lines="3" />
            <div v-else class="card divide-y divide-line">
                <a v-for="n in notices" :key="n.slug" :href="'/notices/' + n.slug" class="flex gap-3 p-4 hover:bg-cream">
                    <span class="w-1.5 shrink-0 rounded-full" :class="n.sponsored ? 'bg-line' : n.category === 'deeds' ? 'bg-gold-500' : 'bg-forest-700'" />
                    <span class="min-w-0"><span class="block font-bold text-forest-900">{{ n.title }}</span><span class="text-xs text-muted">{{ n.sponsored ? 'From the association' : n.category }} · {{ fmt.date(n.published_at) }}{{ n.signed_by ? ' · ' + n.signed_by : '' }}</span></span>
                </a>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2">
            <RouterLink to="/polls" class="card card-hover flex items-center gap-3 p-4"><Vote class="size-5 text-forest-700" /><span class="flex-1 font-bold text-forest-900">Polls</span><ChevronRight class="size-4 text-muted" /></RouterLink>
            <RouterLink to="/community" class="card card-hover flex items-center gap-3 p-4"><Megaphone class="size-5 text-forest-700" /><span class="flex-1 font-bold text-forest-900">Community pages</span><ChevronRight class="size-4 text-muted" /></RouterLink>
        </section>
    </div>
</template>
