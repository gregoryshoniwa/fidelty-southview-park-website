<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { toast } from 'vue-sonner';
import { Phone, Mail, ArrowRight, ShieldCheck, MailCheck, Loader2 } from 'lucide-vue-next';
import { api } from '@/shared/api.js';
import Field from '@/shared/Field.vue';
import { useAuth } from '../store.js';
import { authConfig, googleToken, sendPhoneCode, confirmPhoneCode, toE164, friendlyError } from '../firebase.js';

const router = useRouter(); const route = useRoute(); const auth = useAuth();
const cfg = ref({ providers: [], phone_provider: 'local' });
const mode = ref('choose'); // choose | phone | code | email | email-sent | details | email-confirm
const phone = ref(''); const code = ref(''); const email = ref(''); const name = ref(''); const accept = ref(false);
const masked = ref(''); const devCode = ref(null); const busy = ref(''); const errors = ref({});
let confirmation = null; let pendingToken = null; let emailToken = null;

const hasGoogle = computed(() => cfg.value.providers.includes('google'));
const hasEmail = computed(() => true);

onMounted(async () => {
    cfg.value = await authConfig();
    if (route.name === 'login-email' && typeof route.query.token === 'string') {
        emailToken = route.query.token;
        router.replace({ name: 'login-email' }); // keep the one-time token out of history
        busy.value = 'email';
        try { await emailVerify(); } finally { busy.value = ''; }
    }
});

function done(user) {
    auth.set(user);
    const next = typeof route.query.next === 'string' && route.query.next.startsWith('/') && !route.query.next.startsWith('//') ? route.query.next : (user.resident?.verification_status === 'verified' ? '/' : '/verify');
    router.replace(next);
}

async function exchange(token, suggested) {
    pendingToken = token;
    try {
        const r = await api('/auth/firebase', { method: 'POST', body: { token, name: name.value || undefined, accept_terms: accept.value } });
        done(r.user);
    } catch (e) {
        if (e.data?.needs_details) { name.value ||= e.data.suggested_name || suggested || ''; mode.value = 'details'; return; }
        errors.value = e.errors || {}; toast.error(e.first?.('token') || e.message);
    }
}

async function google() {
    busy.value = 'google'; errors.value = {};
    try { const r = await googleToken(); await exchange(r.token, r.name); } catch (e) { toast.error(friendlyError(e)); } finally { busy.value = ''; }
}

async function emailStart() {
    busy.value = 'email'; errors.value = {};
    try { await api('/auth/email', { method: 'POST', body: { email: email.value.trim() } }); mode.value = 'email-sent'; }
    catch (e) { errors.value = e.errors || {}; } finally { busy.value = ''; }
}
async function emailVerify() {
    try {
        const r = await api('/auth/email/verify', { method: 'POST', body: { token: emailToken, name: name.value || undefined, accept_terms: accept.value } });
        done(r.user);
    } catch (e) {
        if (e.data?.needs_details) { mode.value = 'details'; return; }
        toast.error(e.first?.('token') || e.message); mode.value = 'email';
    }
}
function emailConfirm() { emailStart(); }

async function phoneStart() {
    busy.value = 'phone'; errors.value = {};
    try {
        if (cfg.value.phone_provider === 'firebase') {
            const e164 = toE164(phone.value);
            if (!e164) { errors.value = { phone: ['Enter a valid Zimbabwean mobile number, for example 077 123 4567.'] }; return; }
            confirmation = await sendPhoneCode(e164, 'phone-send');
            masked.value = e164.slice(0, 4) + ' •• ••• ' + e164.slice(-3); devCode.value = null;
        } else {
            const r = await api('/auth/otp', { method: 'POST', body: { phone: phone.value } });
            masked.value = r.phone_masked; devCode.value = r.dev_code;
        }
        mode.value = 'code'; toast.success('Code sent to ' + masked.value);
    } catch (e) { errors.value = e.errors || { phone: [friendlyError(e)] }; } finally { busy.value = ''; }
}

async function codeSubmit() {
    busy.value = 'code'; errors.value = {};
    try {
        if (confirmation) { const r = await confirmPhoneCode(confirmation, code.value); await exchange(r.token); return; }
        const r = await api('/auth/verify', { method: 'POST', body: { phone: phone.value, code: code.value, name: name.value || undefined, accept_terms: accept.value } });
        done(r.user);
    } catch (e) {
        if (e.errors?.name) { mode.value = 'details'; return; }
        errors.value = e.errors || { code: [friendlyError(e)] };
    } finally { busy.value = ''; }
}

async function detailsSubmit() {
    busy.value = 'details';
    try {
        if (emailToken) await emailVerify();
        else if (pendingToken) await exchange(pendingToken);
        else await codeSubmit();
    } finally { busy.value = ''; }
}
</script>

<template>
    <div class="grid min-h-dvh lg:grid-cols-2">
        <section class="relative hidden overflow-hidden bg-forest-900 text-cream lg:flex lg:flex-col lg:justify-between lg:p-12">
            <img src="/images/hero-estate-1280.webp" alt="" class="absolute inset-0 size-full object-cover opacity-35">
            <a href="/" class="relative flex items-center gap-3"><img src="/images/logo-96.webp" width="48" height="48" alt="" class="size-12 rounded-[8px] bg-white"><span class="font-serif text-lg font-bold">Fidelity Southview Park<br><span class="font-sans text-xs font-extrabold uppercase tracking-[0.16em] text-gold-500">Residents Association</span></span></a>
            <div class="relative max-w-md"><h2 class="font-serif text-4xl font-bold leading-tight">Services first.<br><span class="text-gold-500">Trust earned.</span></h2><p class="mt-3 text-cream/80">Your stand, your documents and your committee, on your phone.</p></div>
        </section>
        <section class="flex flex-col justify-center px-5 py-10 sm:px-10">
            <div class="mx-auto w-full max-w-sm">
                <a href="/" class="mb-8 flex items-center gap-3 lg:hidden"><img src="/images/logo-96.webp" width="44" height="44" alt="" class="size-11 rounded-[8px]"><span class="font-serif font-bold text-forest-900">Southview Park Residents</span></a>

                <template v-if="mode === 'choose'">
                    <h1 class="font-serif text-3xl font-bold text-forest-900">Sign in or join</h1>
                    <p class="mt-2 text-muted">Free for every Southview Park resident. Choose how you want to sign in.</p>
                    <div class="mt-8 flex flex-col gap-3">
                        <button v-if="hasGoogle" type="button" class="btn w-full border-[1.5px] border-line bg-white text-ink hover:border-forest-700" :disabled="!!busy" @click="google">
                            <Loader2 v-if="busy === 'google'" class="size-4 animate-spin" />
                            <svg v-else viewBox="0 0 24 24" class="size-[18px]" aria-hidden="true"><path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.4h6.5a5.6 5.6 0 0 1-2.4 3.7v3h3.9c2.2-2.1 3.5-5.1 3.5-8.8z"/><path fill="#34A853" d="M12 24c3.2 0 6-1.1 8-2.9l-3.9-3c-1.1.7-2.5 1.2-4.1 1.2-3.1 0-5.8-2.1-6.7-5H1.3v3.1A12 12 0 0 0 12 24z"/><path fill="#FBBC05" d="M5.3 14.3a7.2 7.2 0 0 1 0-4.6V6.6H1.3a12 12 0 0 0 0 10.8l4-3.1z"/><path fill="#EA4335" d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4A12 12 0 0 0 1.3 6.6l4 3.1c.9-2.9 3.6-4.9 6.7-4.9z"/></svg>
                            Continue with Google
                        </button>
                        <button v-if="hasEmail" type="button" class="btn btn-outline w-full" :disabled="!!busy" @click="mode = 'email'"><Mail class="size-4" />Continue with email</button>
                        <button type="button" class="btn btn-gold w-full" :disabled="!!busy" @click="mode = 'phone'"><Phone class="size-4" />Continue with phone number</button>
                    </div>
                </template>

                <form v-else-if="mode === 'phone'" class="flex flex-col gap-5" @submit.prevent="phoneStart">
                    <h1 class="font-serif text-3xl font-bold text-forest-900">Your mobile number</h1>
                    <p class="-mt-3 text-muted">We send a 6-digit code by SMS.</p>
                    <Field id="phone" v-model="phone" label="Mobile number" type="tel" inputmode="tel" autocomplete="tel" placeholder="077 123 4567" required :error="errors.phone?.[0]" />
                    <button id="phone-send" class="btn btn-gold w-full" :disabled="!!busy || phone.length < 9"><Loader2 v-if="busy" class="size-4 animate-spin" />Send me a code</button>
                    <button type="button" class="btn btn-quiet" @click="mode = 'choose'">Other ways to sign in</button>
                </form>

                <form v-else-if="mode === 'code'" class="flex flex-col gap-5" @submit.prevent="codeSubmit">
                    <h1 class="font-serif text-3xl font-bold text-forest-900">Enter your code</h1>
                    <p class="-mt-3 text-muted">Sent by SMS to {{ masked }}.</p>
                    <Field id="code" v-model="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" :maxlength="6" required :error="errors.code?.[0]" />
                    <p v-if="devCode" class="rounded-[8px] border border-dashed border-gold-500 bg-gold-100 px-3 py-2 text-xs font-bold text-gold-700">Local development code: {{ devCode }}</p>
                    <button class="btn btn-gold w-full" :disabled="!!busy || code.length !== 6">Continue <ArrowRight class="size-4" /></button>
                    <button type="button" class="btn btn-quiet" @click="mode = 'phone'; code = ''; confirmation = null">Use a different number</button>
                </form>

                <form v-else-if="mode === 'email' || mode === 'email-confirm'" class="flex flex-col gap-5" @submit.prevent="mode === 'email' ? emailStart() : emailConfirm()">
                    <h1 class="font-serif text-3xl font-bold text-forest-900">{{ mode === 'email' ? 'Your email address' : 'Confirm your email' }}</h1>
                    <p class="-mt-3 text-muted">{{ mode === 'email' ? 'We email you a sign-in link. No password to remember.' : 'You opened the link on a different device. Enter the same email to finish.' }}</p>
                    <Field id="email" v-model="email" label="Email" type="email" autocomplete="email" required :error="errors.email?.[0]" />
                    <button class="btn btn-gold w-full" :disabled="!!busy || !email.includes('@')"><Loader2 v-if="busy" class="size-4 animate-spin" />{{ mode === 'email' ? 'Email me a sign-in link' : 'Continue' }}</button>
                    <button type="button" class="btn btn-quiet" @click="mode = 'choose'">Other ways to sign in</button>
                </form>

                <div v-else-if="mode === 'email-sent'" class="flex flex-col items-center gap-4 text-center">
                    <span class="flex size-16 items-center justify-center rounded-full bg-forest-100 text-forest-700"><MailCheck class="size-8" /></span>
                    <h1 class="font-serif text-3xl font-bold text-forest-900">Check your email</h1>
                    <p class="text-muted">If <strong class="text-forest-900">{{ email }}</strong> can receive mail, a sign-in link from info@fidelity-southview.co.zw is on its way. It works once and expires in 15 minutes. Check your spam folder if it does not arrive in a minute.</p>
                    <button type="button" class="btn btn-quiet" @click="mode = 'email'">Use a different email</button>
                </div>

                <form v-else-if="mode === 'details'" class="flex flex-col gap-5" @submit.prevent="detailsSubmit">
                    <h1 class="font-serif text-3xl font-bold text-forest-900">Welcome to Southview</h1>
                    <p class="-mt-3 text-muted">One last step to create your free account.</p>
                    <Field id="name" v-model="name" label="Your full name" autocomplete="name" required :error="errors.name?.[0]" />
                    <label class="flex items-start gap-3 text-sm text-muted"><input v-model="accept" type="checkbox" class="mt-1 size-4 accent-forest-700" required> <span>I accept the <a href="/terms" target="_blank" class="font-bold text-forest-700 underline">terms</a> and the <a href="/privacy" target="_blank" class="font-bold text-forest-700 underline">privacy notice</a>.</span></label>
                    <button class="btn btn-gold w-full" :disabled="!!busy || name.length < 2 || !accept">Create my account <ArrowRight class="size-4" /></button>
                </form>

                <p class="mt-10 flex items-center gap-2 text-xs text-muted"><ShieldCheck class="size-4 text-forest-700" />Protected under the Cyber and Data Protection Act.</p>
            </div>
        </section>
    </div>
</template>
