<script setup>
import { ref, reactive, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, KeyRound, LogIn, ShieldCheck } from 'lucide-vue-next';
import { api, ApiError } from '@/shared/api.js';
import Field from '@/shared/Field.vue';
import Logo from '../components/Logo.vue';
import { usePortal } from '../store.js';

const router = useRouter();
const route = useRoute();
const portal = usePortal();

const step = ref('password');
const busy = ref(false);
const form = reactive({ phone: '', password: '', code: '' });
const errors = reactive({ phone: '', password: '', code: '', general: '' });
const masked = ref('');
const devCode = ref('');

function clearErrors() { Object.keys(errors).forEach((k) => { errors[k] = ''; }); }

function handle(e, fields) {
    if (e instanceof ApiError && e.status === 422) {
        fields.forEach((f) => { errors[f] = e.first(f) || ''; });
        if (!fields.some((f) => errors[f])) errors.general = e.message;
    } else if (e instanceof ApiError && e.status === 429) {
        errors.general = 'Too many attempts. Wait a minute and try again.';
    } else if (e instanceof ApiError && e.status === 419) {
        errors.general = 'Your session expired. Refresh the page and try again.';
    } else {
        errors.general = e?.message || 'Something went wrong. Try again.';
    }
}

async function submitPassword() {
    clearErrors();
    busy.value = true;
    try {
        const res = await api('/partner/auth/password', { method: 'POST', body: { phone: form.phone, password: form.password }, quiet: true });
        masked.value = res.phone_masked || '';
        devCode.value = res.dev_code || '';
        form.code = '';
        step.value = 'otp';
        await nextTick();
        document.getElementById('code')?.focus();
    } catch (e) {
        handle(e, ['phone', 'password']);
    } finally {
        busy.value = false;
    }
}

async function submitCode() {
    clearErrors();
    busy.value = true;
    try {
        await api('/partner/auth/otp', { method: 'POST', body: { code: form.code }, quiet: true });
        portal.$reset();
        await portal.load(true);
        const next = typeof route.query.next === 'string' && route.query.next.startsWith('/') && !route.query.next.startsWith('//') ? route.query.next : '/';
        router.replace(next);
    } catch (e) {
        handle(e, ['code']);
    } finally {
        busy.value = false;
    }
}

function restart() {
    clearErrors();
    step.value = 'password';
    form.password = '';
    devCode.value = '';
}
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-sand lg:flex-row">
        <section class="flex flex-col justify-between gap-8 bg-forest-900 px-6 py-8 text-cream lg:w-[44%] lg:px-12 lg:py-12">
            <div class="flex items-center gap-3">
                <Logo />
                <div>
                    <p class="eyebrow text-cream/60">Southview Park Residents</p>
                    <p class="font-serif text-lg font-bold text-gold-400">Partner portal</p>
                </div>
            </div>
            <div class="hidden max-w-md lg:block">
                <h2 class="font-serif text-4xl font-bold leading-tight">Serve Southview residents from one queue.</h2>
                <p class="mt-4 text-cream/75">Review requests and documents, update residents by SMS, and keep every conversation in one place.</p>
            </div>
            <p class="hidden text-xs text-cream/50 lg:block">Fidelity Southview Park Residents Association, Harare</p>
        </section>

        <section class="flex flex-1 items-start justify-center px-4 py-10 sm:items-center">
            <div class="card w-full max-w-md p-6 animate-rise sm:p-8">
                <template v-if="step === 'password'">
                    <p class="eyebrow text-gold-600">Step 1 of 2</p>
                    <h1 class="mt-1 font-serif text-3xl font-bold text-forest-900">Sign in</h1>
                    <p class="mt-1 text-sm text-muted">Use the phone number and password your organisation registered with the association.</p>
                    <form class="mt-6 flex flex-col gap-4" novalidate @submit.prevent="submitPassword">
                        <Field id="phone" v-model="form.phone" label="Phone number" type="tel" inputmode="tel" autocomplete="username" placeholder="077 000 0000" required :error="errors.phone" />
                        <Field id="password" v-model="form.password" label="Password" type="password" autocomplete="current-password" required :error="errors.password" />
                        <p v-if="errors.general" class="error" role="alert">{{ errors.general }}</p>
                        <button type="submit" class="btn btn-gold mt-2 w-full" :disabled="busy || !form.phone || !form.password">
                            <LogIn class="size-4" aria-hidden="true" /> {{ busy ? 'Checking' : 'Continue' }}
                        </button>
                    </form>
                </template>

                <template v-else>
                    <p class="eyebrow text-gold-600">Step 2 of 2</p>
                    <h1 class="mt-1 font-serif text-3xl font-bold text-forest-900">Enter your code</h1>
                    <p class="mt-1 text-sm text-muted">We sent a 6-digit code by SMS<template v-if="masked"> to {{ masked }}</template>. It expires in a few minutes.</p>
                    <div v-if="devCode" class="mt-4 flex items-center gap-2 rounded-[8px] border border-dashed border-gold-500 bg-gold-100 px-3 py-2 text-sm text-gold-700" role="note">
                        <KeyRound class="size-4 shrink-0" aria-hidden="true" />
                        <span>Local test code: <strong class="font-mono tracking-widest">{{ devCode }}</strong></span>
                    </div>
                    <form class="mt-6 flex flex-col gap-4" novalidate @submit.prevent="submitCode">
                        <div>
                            <label for="code" class="label">6-digit code</label>
                            <input id="code" v-model="form.code" class="input text-center font-mono text-2xl tracking-[0.5em]" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required
                                   :aria-invalid="!!errors.code" :aria-describedby="errors.code ? 'code-err' : undefined" @input="form.code = form.code.replace(/\D/g, '').slice(0, 6)" />
                            <p v-if="errors.code" id="code-err" class="error" role="alert">{{ errors.code }}</p>
                        </div>
                        <p v-if="errors.general" class="error" role="alert">{{ errors.general }}</p>
                        <button type="submit" class="btn btn-gold w-full" :disabled="busy || form.code.length !== 6">
                            <ShieldCheck class="size-4" aria-hidden="true" /> {{ busy ? 'Verifying' : 'Verify and sign in' }}
                        </button>
                        <button type="button" class="btn btn-quiet btn-sm self-start" @click="restart">
                            <ArrowLeft class="size-4" aria-hidden="true" /> Start again
                        </button>
                    </form>
                </template>
            </div>
        </section>
    </div>
</template>
