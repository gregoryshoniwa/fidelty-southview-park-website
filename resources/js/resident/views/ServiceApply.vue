<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import { api } from '@/shared/api.js';
const route = useRoute(); const router = useRouter();
const slug = route.params.slug; const busy = ref(false); const errors = ref({});
const loans = slug === 'home-and-deed-loans';
const fields = ref({ product: 'Deed completion loan', amount: '', purpose: '' }); const notes = ref('');
async function submit() {
    busy.value = true; errors.value = {};
    try { const r = await api(`/services/${slug}/requests`, { method: 'POST', body: loans ? { fields: fields.value } : { notes: notes.value } }); toast.success('Request ' + r.reference + ' sent'); router.replace('/requests/' + r.reference); }
    catch (e) { errors.value = e.errors || {}; } finally { busy.value = false; }
}
</script>
<template>
    <div class="mx-auto max-w-lg">
        <form class="card flex flex-col gap-4 p-6" @submit.prevent="submit">
            <h2 class="font-serif text-2xl font-bold text-forest-900">{{ loans ? 'Apply for a loan' : 'Open a request' }}</h2>
            <p v-if="loans" class="text-sm text-muted">TN CyberTech Bank is the lender and makes every decision. The association is paid an introducer fee by the bank, which the bank discloses on your application.</p>
            <template v-if="loans">
                <div><label for="lp" class="label">Which loan</label><select id="lp" v-model="fields.product" class="input"><option>Deed completion loan</option><option>Home improvement loan</option></select></div>
                <div><label for="la" class="label">Amount you need (US$)</label><input id="la" v-model="fields.amount" inputmode="decimal" class="input" required><p v-if="errors['fields.amount']" class="error">{{ errors['fields.amount'][0] }}</p></div>
                <div><label for="lu" class="label">What it is for (optional)</label><textarea id="lu" v-model="fields.purpose" rows="3" maxlength="500" class="input py-3" /></div>
            </template>
            <div v-else><label for="sn" class="label">Details</label><textarea id="sn" v-model="notes" rows="4" maxlength="1000" class="input py-3" /></div>
            <p v-if="errors.message" class="error">{{ errors.message }}</p>
            <button class="btn btn-gold" :disabled="busy">Send</button>
        </form>
    </div>
</template>
