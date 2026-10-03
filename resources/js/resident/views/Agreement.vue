<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import { Download, FileWarning, Loader2 } from 'lucide-vue-next';
import { api, download, fmt } from '@/shared/api.js';
import Modal from '@/shared/Modal.vue';
import Skeleton from '@/shared/Skeleton.vue';

const router = useRouter();
const rows = ref(null); const stand = ref(''); const busy = ref(false); const dl = ref(false);
const openReplace = ref(false); const reason = ref('lost'); const details = ref('');
onMounted(async () => { try { const r = await api('/fidelity/payments'); rows.value = r.data; stand.value = r.stand; } catch { rows.value = []; } });
async function get() { dl.value = true; try { await download('/fidelity/agreement', `agreement-of-sale-stand-${stand.value}.pdf`); toast.success('Agreement downloaded'); } finally { dl.value = false; } }
async function replace() {
    busy.value = true;
    try {
        const r = await api('/fidelity/agreement/replace', { method: 'POST', body: { reason: reason.value, details: details.value } });
        openReplace.value = false;
        if (r.checkout_url) { location.href = r.checkout_url; return; }
        toast.success('Request ' + r.reference + ' sent to Fidelity Life');
        router.push('/requests/' + r.reference);
    } finally { busy.value = false; }
}
</script>
<template>
    <div class="flex flex-col gap-5">
        <section class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
            <div class="flex-1"><p class="eyebrow text-gold-600">Fidelity Life Assurance</p><h2 class="font-serif text-2xl font-bold text-forest-900">Agreement of Sale</h2><p class="text-sm text-muted">Stand {{ stand || '…' }}. A digital copy from Fidelity Life's records.</p></div>
            <div class="flex flex-wrap gap-2">
                <button class="btn btn-gold" :disabled="dl" @click="get"><Loader2 v-if="dl" class="size-4 animate-spin" /><Download v-else class="size-4" />Download PDF</button>
                <button class="btn btn-outline" @click="openReplace = true"><FileWarning class="size-4" />Lost the original?</button>
            </div>
        </section>
        <section>
            <h2 class="eyebrow mb-3 text-muted">Payment history</h2>
            <Skeleton v-if="!rows" :lines="4" />
            <div v-else class="card overflow-x-auto">
                <table class="w-full min-w-[480px] text-sm">
                    <thead><tr class="border-b border-line text-left text-[11px] uppercase tracking-wider text-muted"><th class="p-3">Date</th><th class="p-3">Description</th><th class="p-3 text-right">Amount</th><th class="p-3 text-right">Balance</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="(r, i) in rows" :key="i"><td class="p-3 text-muted">{{ fmt.date(r.date) }}</td><td class="p-3">{{ r.description }}</td><td class="p-3 text-right font-bold text-forest-700">{{ fmt.money(r.amount, r.currency) }}</td><td class="p-3 text-right">{{ fmt.money(r.balance, r.currency) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <Modal v-model:open="openReplace" title="Request a replacement" description="Fidelity Life prints a certified copy and the app tells you when it is ready to collect.">
            <form class="flex flex-col gap-4" @submit.prevent="replace">
                <fieldset><legend class="label">What happened to the original?</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <label v-for="o in ['lost', 'damaged', 'stolen', 'other']" :key="o" class="flex min-h-11 cursor-pointer items-center gap-2 rounded-[8px] border-[1.5px] px-3 text-sm font-semibold capitalize" :class="reason === o ? 'border-gold-500 bg-gold-100' : 'border-line'"><input v-model="reason" type="radio" :value="o" class="accent-forest-700">{{ o }}</label>
                    </div>
                </fieldset>
                <div><label for="rdetails" class="label">Anything else? (optional)</label><textarea id="rdetails" v-model="details" maxlength="500" rows="3" class="input py-3" /></div>
                <p v-if="reason === 'stolen'" class="rounded-[8px] bg-gold-100 p-3 text-xs text-gold-700">Fidelity Life may ask for a police report number when you collect.</p>
                <button class="btn btn-gold" :disabled="busy">Send request</button>
            </form>
        </Modal>
    </div>
</template>
