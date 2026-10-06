<script setup>
import { ref, onMounted, computed } from 'vue';
import { toast } from 'vue-sonner';
import { Smartphone, Clock } from 'lucide-vue-next';
import { api } from '@/shared/api.js';
import Field from '@/shared/Field.vue';
import WhatsAppProof from './WhatsAppProof.vue';
import { authConfig, sendPhoneCode, confirmPhoneCode, toE164, friendlyError } from '../firebase.js';
import { useAuth } from '../store.js';

const emit = defineEmits(['linked']);
const auth = useAuth();
const cfg = ref({ phone_provider: 'local', whatsapp: false });
const step = ref(1); const phone = ref(''); const code = ref(''); const dev = ref(null); const busy = ref(false); const errors = ref({});
let confirmation = null;
onMounted(async () => { cfg.value = await authConfig(); });

const sms = computed(() => cfg.value.phone_provider !== 'none');
const pending = computed(() => auth.user?.phone_pending_masked);

function linked(user) { auth.set(user); toast.success('Mobile number confirmed'); emit('linked'); }

async function send() {
    busy.value = true; errors.value = {};
    try {
        if (!sms.value) {
            const r = await api('/me/phone/unconfirmed', { method: 'POST', body: { phone: phone.value } });
            auth.set(r.user); toast.success('Saved. The committee will confirm your number.'); emit('linked');
            return;
        }
        if (cfg.value.phone_provider === 'firebase') {
            const e164 = toE164(phone.value);
            if (!e164) { errors.value = { phone: ['Enter a valid Zimbabwean mobile number.'] }; return; }
            confirmation = await sendPhoneCode(e164, 'link-send');
        } else {
            dev.value = (await api('/me/phone/otp', { method: 'POST', body: { phone: phone.value } })).dev_code;
        }
        step.value = 2;
    } catch (e) { errors.value = e.errors || { phone: [friendlyError(e)] }; } finally { busy.value = false; }
}
async function confirm() {
    busy.value = true; errors.value = {};
    try {
        const body = confirmation ? { token: (await confirmPhoneCode(confirmation, code.value)).token } : { code: code.value };
        linked((await api('/me/phone', { method: 'POST', body })).user);
    } catch (e) { errors.value = e.errors || { code: [friendlyError(e)] }; } finally { busy.value = false; }
}
function waError(e) { toast.error(e.first?.('phone') || e.message || 'Could not confirm your number.'); }
</script>
<template>
    <div class="card flex flex-col gap-4 p-6">
        <div class="flex items-center gap-3"><span class="flex size-10 items-center justify-center rounded-[8px] bg-forest-100 text-forest-700"><Smartphone class="size-5" /></span><div><p class="font-extrabold text-forest-900">Add your mobile number</p><p class="text-xs text-muted">Needed for stand verification and updates on your deed, messages and notices.</p></div></div>

        <p v-if="pending" class="flex gap-2 rounded-[8px] bg-gold-100 p-3 text-sm text-gold-700"><Clock class="mt-0.5 size-4 shrink-0" /><span>Your number {{ pending }} is waiting for the committee to confirm it.<template v-if="cfg.whatsapp"> Confirm it yourself now with WhatsApp.</template></span></p>

        <WhatsAppProof v-if="cfg.whatsapp" start-path="/me/phone/whatsapp" :label="pending ? 'Confirm with WhatsApp' : 'Verify with WhatsApp'" @done="linked" @error="waError" />

        <template v-if="!pending">
            <p v-if="cfg.whatsapp" class="text-center text-xs font-bold uppercase tracking-wider text-muted">{{ sms ? 'or by SMS' : 'no WhatsApp?' }}</p>
            <form class="flex flex-col gap-4" @submit.prevent="step === 1 ? send() : confirm()">
                <Field v-if="step === 1" id="link-phone" v-model="phone" label="Mobile number" type="tel" inputmode="tel" autocomplete="tel" placeholder="077 123 4567" required :error="errors.phone?.[0]"
                    :help="sms ? null : 'The committee confirms it when they check your stand.'" />
                <template v-else>
                    <Field id="link-code" v-model="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" :maxlength="6" required :error="errors.code?.[0] || errors.phone?.[0]" />
                    <p v-if="dev" class="rounded-[8px] border border-dashed border-gold-500 bg-gold-100 px-3 py-2 text-xs font-bold text-gold-700">Local development code: {{ dev }}</p>
                </template>
                <button :id="step === 1 ? 'link-send' : 'link-confirm'" class="btn self-start" :class="cfg.whatsapp ? 'btn-quiet' : 'btn-gold'" :disabled="busy">{{ step === 2 ? 'Confirm' : sms ? 'Send code' : 'Save number' }}</button>
            </form>
        </template>
    </div>
</template>
