<script setup>
import { ref, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import { Vote } from 'lucide-vue-next';
import { api, fmt } from '@/shared/api.js';
import Skeleton from '@/shared/Skeleton.vue';
import EmptyState from '@/shared/EmptyState.vue';
import ConfirmDialog from '@/shared/ConfirmDialog.vue';
const polls = ref(null); const choice = ref({}); const confirm = ref({ open: false, poll: null }); const busy = ref(false);
async function load() { polls.value = (await api('/polls')).data; }
onMounted(load);
function ask(p) { if (choice.value[p.id] === undefined) return; confirm.value = { open: true, poll: p }; }
async function vote() {
    const p = confirm.value.poll; busy.value = true;
    try { await api(`/polls/${p.id}/vote`, { method: 'POST', body: { option: choice.value[p.id] } }); toast.success('Vote recorded for your stand'); confirm.value.open = false; await load(); }
    catch (e) { toast.error(e.message); } finally { busy.value = false; }
}
const pct = (p, v) => (p.voters ? Math.round((v / p.voters) * 100) : 0);
</script>
<template>
    <Skeleton v-if="!polls" />
    <EmptyState v-else-if="!polls.length" :icon="Vote" title="No polls right now" text="When the committee asks residents a question, it appears here. One vote per verified stand." />
    <div v-else class="flex flex-col gap-4">
        <section v-for="p in polls" :key="p.id" class="card p-5">
            <p class="eyebrow" :class="p.open ? 'text-forest-700' : 'text-muted'">{{ p.open ? 'Open until ' + fmt.date(p.closes_at) : 'Closed' }}</p>
            <h2 class="mt-1 font-serif text-xl font-bold text-forest-900">{{ p.question }}</h2>
            <p v-if="p.description" class="mt-1 text-sm text-muted">{{ p.description }}</p>
            <div v-if="p.results" class="mt-4 flex flex-col gap-2.5">
                <div v-for="(r, i) in p.results" :key="i"><div class="flex justify-between text-sm"><span class="font-semibold" :class="p.my_vote === i ? 'text-forest-700' : ''">{{ r.label }}{{ p.my_vote === i ? ' (your vote)' : '' }}</span><span class="text-muted">{{ pct(p, r.votes) }}%</span></div><div class="mt-1 h-2 overflow-hidden rounded-full bg-sand"><div class="h-full rounded-full bg-forest-700 transition-all duration-700" :style="{ width: pct(p, r.votes) + '%' }" /></div></div>
                <p class="text-xs text-muted">{{ p.voters }} stand{{ p.voters === 1 ? '' : 's' }} voted</p>
            </div>
            <fieldset v-else-if="p.open" class="mt-4 flex flex-col gap-2">
                <legend class="sr-only">{{ p.question }}</legend>
                <label v-for="(o, i) in p.options" :key="i" class="flex min-h-12 cursor-pointer items-center gap-3 rounded-[8px] border-[1.5px] px-4 text-[15px] font-semibold" :class="choice[p.id] === i ? 'border-gold-500 bg-gold-100' : 'border-line hover:border-gold-500'"><input v-model="choice[p.id]" type="radio" :name="'poll' + p.id" :value="i" class="accent-forest-700">{{ o }}</label>
                <button class="btn btn-gold mt-2 self-start" :disabled="choice[p.id] === undefined" @click="ask(p)">Cast my vote</button>
            </fieldset>
        </section>
        <ConfirmDialog v-model:open="confirm.open" title="Cast your vote?" :description="confirm.poll ? 'You are voting for: ' + confirm.poll.options[choice[confirm.poll.id]] + '. One vote per stand, and it cannot be changed.' : ''" confirm-label="Vote" :busy="busy" @confirm="vote" />
    </div>
</template>
