<script setup>
import { ref, inject } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { toast } from 'vue-sonner';
import { Phone, ArrowRight, ShieldCheck } from 'lucide-vue-next';
import { api } from '@/shared/api.js';
import Field from '@/shared/Field.vue';
import { useAuth } from '../store.js';

const router = useRouter(); const route = useRoute(); const auth = useAuth();
const step = ref('phone'); const phone = ref(''); const code = ref(''); const name = ref(''); const accept = ref(false);
const isNew = ref(false); const masked = ref(''); const devCode = ref(null); const busy = ref(false); const errors = ref({});

async function sendCode() {
    busy.value = true; errors.value = {};
    try {
        const r = await api('/auth/otp', { method: 'POST', body: { phone: phone.value } });
        masked.value = r.phone_masked; devCode.value = r.dev_code; step.value = 'code';
        toast.success('Code sent to ' + r.phone_masked);
    } catch (e) { errors.value = e.errors || {}; } finally { busy.value = false; }
}
async function verify() {
    busy.value = true; errors.value = {};
    try {
        const r = await api('/auth/verify', { method: 'POST', body: { phone: phone.value, code: code.value, name: name.value || undefined, accept_terms: accept.value } });
        auth.set(r.user);
        const next = typeof route.query.next === 'string' && route.query.next.startsWith('/') ? route.query.next : (r.user.resident?.verification_status === 'verified' ? '/' : '/verify');
        router.replace(next);
    } catch (e) { errors.value = e.errors || {}; if (e.errors?.name) isNew.value = true; } finally { busy.value = false; }
}
</script>

<template>
    <div class="grid min-h-dvh lg:grid-cols-2">
        <section class="relative hidden overflow-hidden bg-forest-900 text-cream lg:flex lg:flex-col lg:justify-between lg:p-12">
            <img src="/images/hero-estate-1280.webp" alt="" class="absolute inset-0 size-full object-cover opacity-35">
            <a href="/" class="relative flex items-center gap-3"><img src="/images/logo-96.webp" width="48" height="48" alt="" class="size-12 rounded-[8px] bg-white"><span class="font-serif text-lg font-bold">Fidelity Southview Park<br><span class="text-xs font-sans font-extrabold uppercase tracking-[0.16em] text-gold-500">Residents Association</span></span></a>
            <div class="relative max-w-md"><h2 class="font-serif text-4xl font-bold leading-tight">Services first.<br><span class="text-gold-500">Trust earned.</span></h2><p class="mt-3 text-cream/80">Your stand, your documents and your committee, on your phone.</p></div>
        </section>
        <section class="flex flex-col justify-center px-5 py-10 sm:px-10">
            <div class="mx-auto w-full max-w-sm">
                <a href="/" class="mb-8 flex items-center gap-3 lg:hidden"><img src="/images/logo-96.webp" width="44" height="44" alt="" class="size-11 rounded-[8px]"><span class="font-serif font-bold text-forest-900">Southview Park Residents</span></a>
                <h1 class="font-serif text-3xl font-bold text-forest-900">{{ step === 'phone' ? 'Sign in or join' : 'Enter your code' }}</h1>
                <p class="mt-2 text-muted">{{ step === 'phone' ? 'We send a 6-digit code by SMS. No password needed.' : 'Sent by SMS to ' + masked + '.' }}</p>

                <form v-if="step === 'phone'" class="mt-8 flex flex-col gap-5" @submit.prevent="sendCode">
                    <Field id="phone" v-model="phone" label="Mobile number" type="tel" inputmode="tel" autocomplete="tel" placeholder="077 123 4567" required :error="errors.phone?.[0]" />
                    <button class="btn btn-gold w-full" :disabled="busy || phone.length < 9"><Phone class="size-4" />Send me a code</button>
                </form>

                <form v-else class="mt-8 flex flex-col gap-5" @submit.prevent="verify">
                    <Field id="code" v-model="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" :maxlength="6" required :error="errors.code?.[0]" />
                    <p v-if="devCode" class="rounded-[8px] border border-dashed border-gold-500 bg-gold-100 px-3 py-2 text-xs font-bold text-gold-700">Local development code: {{ devCode }}</p>
                    <template v-if="isNew">
                        <Field id="name" v-model="name" label="Your full name" autocomplete="name" required :error="errors.name?.[0]" />
                        <label class="flex items-start gap-3 text-sm text-muted"><input v-model="accept" type="checkbox" class="mt-1 size-4 accent-forest-700" required> <span>I accept the <a href="/terms" target="_blank" class="font-bold text-forest-700 underline">terms</a> and the <a href="/privacy" target="_blank" class="font-bold text-forest-700 underline">privacy notice</a>.</span></label>
                    </template>
                    <button class="btn btn-gold w-full" :disabled="busy || code.length !== 6">{{ isNew ? 'Create my account' : 'Sign in' }}<ArrowRight class="size-4" /></button>
                    <button type="button" class="btn btn-quiet w-full" @click="step = 'phone'; code = ''">Use a different number</button>
                </form>
                <p class="mt-10 flex items-center gap-2 text-xs text-muted"><ShieldCheck class="size-4 text-forest-700" />Protected under the Cyber and Data Protection Act.</p>
            </div>
        </section>
    </div>
</template>
