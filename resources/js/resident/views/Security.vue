<script setup>
import { ref, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import { Siren, ShieldAlert, Phone } from 'lucide-vue-next';
import { api, fmt } from '@/shared/api.js';
import Modal from '@/shared/Modal.vue';
import StatusChip from '@/shared/StatusChip.vue';
const data = ref(null); const open = ref(false); const busy = ref(false); const errors = ref({});
const form = ref({ category: 'suspicious', location: '', description: '' });
const cats = { break_in: 'Break-in', suspicious: 'Suspicious activity', assault: 'Assault', fire: 'Fire', vandalism: 'Vandalism', other: 'Other' };
async function load() { data.value = await api('/security'); }
onMounted(load);
async function submit() {
    busy.value = true; errors.value = {};
    try { const r = await api('/incidents', { method: 'POST', body: form.value }); toast.success('Report ' + r.reference + ' recorded'); open.value = false; form.value = { category: 'suspicious', location: '', description: '' }; await load(); }
    catch (e) { errors.value = e.errors || {}; } finally { busy.value = false; }
}
</script>
<template>
    <div class="flex flex-col gap-5">
        <section class="flex gap-3 rounded-[12px] border-[1.5px] border-danger/30 bg-red-50 p-4 text-sm"><Phone class="size-5 shrink-0 text-danger" /><p><strong class="text-danger">Emergency?</strong> Call ZRP on 999 or 995 first. This page is for reports and follow-up, not emergencies.</p></section>
        <section class="card flex flex-col items-start gap-3 p-5"><span class="flex size-11 items-center justify-center rounded-[8px] bg-forest-100 text-forest-700"><ShieldAlert class="size-5" /></span><h2 class="font-serif text-2xl font-bold text-forest-900">Report an incident</h2><p class="text-sm text-muted">Reports go privately to the committee, and to your security company once partners sign up.</p><button class="btn btn-gold" @click="open = true"><Siren class="size-4" />Make a report</button></section>
        <section v-if="data?.incidents?.length"><h3 class="eyebrow mb-3 text-muted">Your reports</h3><div class="card divide-y divide-line"><div v-for="i in data.incidents" :key="i.reference" class="flex items-center justify-between p-4"><span><span class="block font-bold text-forest-900">{{ cats[i.category] }}</span><span class="text-xs text-muted">{{ i.reference }} · {{ fmt.ago(i.at) }}</span></span><StatusChip :status="i.status" /></div></div></section>
        <section class="card p-5"><h3 class="font-bold text-forest-900">Rapid-response subscriptions</h3><p class="mt-1 text-sm text-muted">{{ data?.companies?.length ? 'Choose a licensed company below.' : 'Coming in phase 3. We will only list companies licensed under the Private Investigators and Security Guards Act.' }}</p></section>
        <Modal v-model:open="open" title="Report an incident" description="Do not include anyone's ID number. False reports may be a criminal offence.">
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div><label for="ic" class="label">What happened</label><select id="ic" v-model="form.category" class="input"><option v-for="(l, k) in cats" :key="k" :value="k">{{ l }}</option></select></div>
                <div><label for="il" class="label">Where (optional)</label><input id="il" v-model="form.location" maxlength="160" class="input" placeholder="Street or stand number"></div>
                <div><label for="id" class="label">Details</label><textarea id="id" v-model="form.description" rows="4" maxlength="1500" required class="input py-3" /><p v-if="errors.description" class="error">{{ errors.description[0] }}</p></div>
                <button class="btn btn-gold" :disabled="busy">Send report</button>
            </form>
        </Modal>
    </div>
</template>
