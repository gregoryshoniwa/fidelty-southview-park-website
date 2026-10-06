<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Landmark, Check, Loader2, MapPin, Phone } from 'lucide-vue-next';
import { api } from '@/shared/api.js';

const router = useRouter(); const busy = ref(false); const loading = ref(true);
const firms = ref([]); const firm = ref(''); const error = ref('');
const steps = ['Documents received', 'Fidelity Life balance confirmed', 'Legal fees paid', 'Council rates clearance', 'Lodged at the Deeds Registry', 'Deed issued'];
onMounted(async () => {
    const r = await api('/requests');
    const deed = r.data.find((x) => x.service.slug === 'title-deed-tracker' && !['closed', 'cancelled'].includes(x.status));
    if (deed) { router.replace('/requests/' + deed.reference); return; }
    firms.value = (await api('/law-firms')).data;
    loading.value = false;
});
async function open() {
    busy.value = true;
    error.value = '';
    try { const r = await api('/services/title-deed-tracker/requests', { method: 'POST', body: { partner: firm.value } }); router.replace('/requests/' + r.reference); }
    catch (e) { error.value = e.first?.('partner') || e.message; }
    finally { busy.value = false; }
}
</script>
<template>
    <div v-if="loading" class="flex justify-center py-20"><Loader2 class="size-6 animate-spin text-forest-700" /></div>
    <div v-else class="mx-auto flex max-w-lg flex-col gap-5">
        <section class="rounded-[14px] bg-forest-900 p-6 text-cream">
            <span class="flex size-12 items-center justify-center rounded-[10px] bg-gold-500/20 text-gold-400"><Landmark class="size-6" /></span>
            <h2 class="mt-4 font-serif text-3xl font-bold">Open your title deed file</h2>
            <p class="mt-2 text-cream/80">Five law firms are processing title deeds for Southview Park. Choose the one handling yours, open your file once, upload your documents, and follow every step here.</p>
        </section>
        <ol class="card divide-y divide-line">
            <li v-for="(s, i) in steps" :key="s" class="flex items-center gap-3 p-4"><span class="flex size-7 items-center justify-center rounded-full border-[1.5px] border-line text-xs font-extrabold text-muted">{{ i + 1 }}</span><span class="font-semibold text-forest-900">{{ s }}</span></li>
        </ol>
        <div class="card p-5 text-sm text-muted"><p class="font-bold text-forest-900">You will need</p><ul class="mt-2 flex flex-col gap-1.5"><li v-for="d in ['Agreement of Sale', 'National ID or passport', 'Proof of residence']" :key="d" class="flex gap-2"><Check class="size-4 text-forest-700" />{{ d }}</li></ul></div>
        <fieldset class="card flex flex-col gap-3 p-5">
            <legend class="sr-only">Your law firm</legend>
            <p class="font-bold text-forest-900">Which law firm is handling your deed?</p>
            <p class="-mt-2 text-sm text-muted">It is on your Agreement of Sale or the letter from Fidelity Life. Not sure? <RouterLink to="/inbox/new" class="font-bold text-forest-700 underline">Ask the committee</RouterLink>.</p>
            <label v-for="f in firms" :key="f.slug" class="flex cursor-pointer items-center gap-4 rounded-[10px] border-[1.5px] p-3 transition"
                   :class="firm === f.slug ? 'border-gold-500 bg-gold-100/60 ring-2 ring-gold-500/25' : 'border-line hover:border-forest-700/40'">
                <input v-model="firm" type="radio" name="firm" :value="f.slug" class="sr-only">
                <span class="flex h-14 w-24 shrink-0 items-center justify-center rounded-[8px] border border-line bg-white p-1.5"><img v-if="f.logo" :src="f.logo" :alt="''" class="max-h-full max-w-full object-contain"></span>
                <span class="min-w-0">
                    <span class="block font-bold text-forest-900">{{ f.name }}</span>
                    <span v-if="f.address" class="mt-0.5 flex items-start gap-1 text-xs text-muted"><MapPin class="mt-0.5 size-3.5 shrink-0" />{{ f.address }}</span>
                    <span v-if="f.phone" class="mt-0.5 flex items-center gap-1 text-xs text-muted"><Phone class="size-3.5 shrink-0" />{{ f.phone }}</span>
                </span>
                <Check v-if="firm === f.slug" class="ml-auto size-5 shrink-0 text-gold-600" />
            </label>
            <p v-if="error" class="error" role="alert">{{ error }}</p>
        </fieldset>
        <button class="btn btn-gold" :disabled="busy || !firm" @click="open">Open my deed file</button>
    </div>
</template>
