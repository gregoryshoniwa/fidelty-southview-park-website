<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Landmark, Check, Loader2 } from 'lucide-vue-next';
import { api } from '@/shared/api.js';

const router = useRouter(); const busy = ref(false); const loading = ref(true);
const steps = ['Documents received', 'Fidelity Life balance confirmed', 'Legal fees paid', 'Council rates clearance', 'Lodged at the Deeds Registry', 'Deed issued'];
onMounted(async () => {
    const r = await api('/requests');
    const deed = r.data.find((x) => x.service.slug === 'title-deed-tracker' && !['closed', 'cancelled'].includes(x.status));
    if (deed) router.replace('/requests/' + deed.reference); else loading.value = false;
});
async function open() {
    busy.value = true;
    try { const r = await api('/services/title-deed-tracker/requests', { method: 'POST', body: {} }); router.replace('/requests/' + r.reference); }
    finally { busy.value = false; }
}
</script>
<template>
    <div v-if="loading" class="flex justify-center py-20"><Loader2 class="size-6 animate-spin text-forest-700" /></div>
    <div v-else class="mx-auto flex max-w-lg flex-col gap-5">
        <section class="rounded-[14px] bg-forest-900 p-6 text-cream">
            <span class="flex size-12 items-center justify-center rounded-[10px] bg-gold-500/20 text-gold-400"><Landmark class="size-6" /></span>
            <h2 class="mt-4 font-serif text-3xl font-bold">Open your title deed file</h2>
            <p class="mt-2 text-cream/80">Marufu Attorneys are processing title deeds for Southview Park. Open your file once, upload your documents, and follow every step here.</p>
        </section>
        <ol class="card divide-y divide-line">
            <li v-for="(s, i) in steps" :key="s" class="flex items-center gap-3 p-4"><span class="flex size-7 items-center justify-center rounded-full border-[1.5px] border-line text-xs font-extrabold text-muted">{{ i + 1 }}</span><span class="font-semibold text-forest-900">{{ s }}</span></li>
        </ol>
        <div class="card p-5 text-sm text-muted"><p class="font-bold text-forest-900">You will need</p><ul class="mt-2 flex flex-col gap-1.5"><li v-for="d in ['Agreement of Sale', 'National ID, both sides', 'Proof of residence', 'Later: council rates clearance certificate']" :key="d" class="flex gap-2"><Check class="size-4 text-forest-700" />{{ d }}</li></ul></div>
        <button class="btn btn-gold" :disabled="busy" @click="open">Open my deed file</button>
    </div>
</template>
