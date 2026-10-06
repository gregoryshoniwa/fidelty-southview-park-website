<script setup>
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ShieldCheck, Check, FileText, Landmark, MessageSquare, Info } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { api } from '@/shared/api.js';
import Field from '@/shared/Field.vue';
import PhoneLink from '../components/PhoneLink.vue';
import { useAuth } from '../store.js';

const auth = useAuth(); const router = useRouter(); const route = useRoute();
const status = computed(() => auth.user?.resident?.verification_status);
const step = ref(status.value === 'verified' ? 3 : status.value === 'pending' ? 2 : status.value === 'review' ? 4 : 1);
const nid = ref(''); const stand = ref(''); const consent = ref(false); const code = ref('');
const masked = ref(auth.user?.resident?.phone_on_file_masked || ''); const devCode = ref(null);
const busy = ref(false); const errors = ref({});

async function start() {
    busy.value = true; errors.value = {};
    try {
        const r = await api('/verify/start', { method: 'POST', body: { national_id: nid.value, stand_number: stand.value, consent: consent.value } });
        if (r.status === 'review') { await auth.load(true); step.value = 4; return; }
        masked.value = r.phone_on_file_masked; devCode.value = r.dev_code; step.value = 2;
    } catch (e) {
        errors.value = e.errors || {};
        const other = Object.keys(errors.value).find((k) => !['national_id', 'stand_number', 'consent'].includes(k));
        if (other) toast.error(errors.value[other][0]);
    } finally { busy.value = false; }
}
async function confirm() {
    busy.value = true; errors.value = {};
    try { const r = await api('/verify/confirm', { method: 'POST', body: { code: code.value } }); auth.set(r.user); step.value = 3; }
    catch (e) { errors.value = e.errors || {}; } finally { busy.value = false; }
}
const next = computed(() => (typeof route.query.next === 'string' && route.query.next.startsWith('/') ? route.query.next : '/'));
</script>

<template>
    <div class="mx-auto max-w-lg">
        <div class="overflow-hidden rounded-[14px] bg-forest-900 p-6 text-cream">
            <div class="flex gap-2" aria-hidden="true"><span v-for="i in 3" :key="i" class="h-1.5 flex-1 rounded-full" :class="i <= step ? 'bg-gold-500' : 'bg-white/20'" /></div>
            <p class="eyebrow mt-4 text-gold-500">Verify me · step {{ Math.min(step, 3) }} of 3</p>
            <h2 class="mt-2 font-serif text-3xl font-bold">{{ ['Prove your stand is yours', 'Check your phone', 'You are verified', 'Being checked'][step - 1] }}</h2>
            <p class="mt-2 text-cream/80">{{ ['Your ID and stand number are matched against Fidelity Life records. Two minutes, no paperwork.', 'Fidelity Life sent a one-time code to the phone number on your Agreement of Sale.', 'Your stand is linked. Every service is now open to you.', 'The committee is confirming your stand with Fidelity Life records. You will get an SMS, usually within two working days.'][step - 1] }}</p>
        </div>

        <div v-if="step === 1 && !auth.user?.has_phone && !auth.user?.phone_pending_masked" class="mt-5"><PhoneLink /></div>
        <form v-else-if="step === 1" class="card mt-5 flex flex-col gap-5 p-6" @submit.prevent="start">
            <Field id="nid" v-model="nid" label="National ID number" placeholder="63-123456-A-12" autocomplete="off" required :error="errors.national_id?.[0]" help="Used only to match Fidelity Life's record. We never store the full number." />
            <Field id="stand" v-model="stand" label="Stand number" placeholder="e.g. 1234" inputmode="text" required :error="errors.stand_number?.[0]" />
            <label class="flex items-start gap-3 rounded-[8px] bg-forest-100 p-4 text-sm text-forest-900">
                <input v-model="consent" type="checkbox" class="mt-0.5 size-4 accent-forest-700" required>
                <span>I agree that the association may send my ID number and stand number to Fidelity Life Assurance to confirm I own this stand. <a href="/privacy" target="_blank" class="font-bold underline">Privacy notice</a></span>
            </label>
            <p v-if="errors.consent" class="error">{{ errors.consent[0] }}</p>
            <button class="btn btn-gold" :disabled="busy || !consent"><ShieldCheck class="size-4" />Send me a code</button>
        </form>

        <form v-else-if="step === 2" class="card mt-5 flex flex-col gap-5 p-6" @submit.prevent="confirm">
            <p class="text-sm text-muted">Code sent to the number Fidelity Life has on file{{ masked ? ', ending ' + masked.slice(-3) : '' }}.</p>
            <Field id="vcode" v-model="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" :maxlength="6" required :error="errors.code?.[0]" />
            <p v-if="devCode" class="rounded-[8px] border border-dashed border-gold-500 bg-gold-100 px-3 py-2 text-xs font-bold text-gold-700">Local development code: {{ devCode }}</p>
            <button class="btn btn-gold" :disabled="busy || code.length !== 6">Confirm code</button>
            <div class="flex gap-2 rounded-[8px] bg-sand p-3 text-xs text-muted"><Info class="size-4 shrink-0" /><span>Not your number any more? <RouterLink to="/inbox/new" class="font-bold text-forest-700 underline">Write to the committee</RouterLink> and we will help you update it with Fidelity Life.</span></div>
            <button type="button" class="btn btn-quiet" @click="step = 1">Start again</button>
        </form>

        <div v-else-if="step === 4" class="card mt-5 flex flex-col gap-3 p-6 text-sm text-muted"><p>Stand <strong class="text-forest-900">{{ auth.user?.resident?.stand }}</strong> is waiting for confirmation. Meanwhile you can read notices and write to the committee.</p><RouterLink to="/inbox/new" class="btn btn-outline self-start">Write to the committee</RouterLink></div>
        <div v-else class="card mt-5 flex flex-col items-center gap-4 p-6 text-center">
            <span class="flex size-20 items-center justify-center rounded-full border-[3px] border-gold-500 bg-forest-100 text-forest-700 animate-rise"><Check class="size-10" :stroke-width="2.6" /></span>
            <p class="font-serif text-2xl font-bold text-forest-900">Welcome, verified resident</p>
            <p class="text-muted">Stand {{ auth.user?.resident?.stand }} is linked to your account. You are now a member of the association. No fee, no obligation.</p>
            <div class="mt-2 grid w-full gap-2">
                <RouterLink to="/agreement" class="btn btn-outline justify-between"><span class="flex items-center gap-2"><FileText class="size-4" />Download my Agreement of Sale</span>→</RouterLink>
                <RouterLink to="/deed" class="btn btn-outline justify-between"><span class="flex items-center gap-2"><Landmark class="size-4" />Open my title deed file</span>→</RouterLink>
                <RouterLink to="/inbox/new" class="btn btn-outline justify-between"><span class="flex items-center gap-2"><MessageSquare class="size-4" />Write to the committee</span>→</RouterLink>
            </div>
            <RouterLink :to="next" class="btn btn-gold w-full">Go to My Home</RouterLink>
        </div>
    </div>
</template>
