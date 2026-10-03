<script setup>
import { ref, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import { Smartphone } from 'lucide-vue-next';
import { api } from '@/shared/api.js';
import Field from '@/shared/Field.vue';
import { authConfig, sendPhoneCode, confirmPhoneCode, toE164, friendlyError } from '../firebase.js';
import { useAuth } from '../store.js';

const emit = defineEmits(['linked']);
const auth = useAuth();
const provider = ref('local'); const step = ref(1); const phone = ref(''); const code = ref(''); const dev = ref(null); const busy = ref(false); const errors = ref({});
let confirmation = null;
onMounted(async () => { provider.value = (await authConfig()).phone_provider; });

async function send() {
    busy.value = true; errors.value = {};
    try {
        if (provider.value === 'firebase') {
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
        const r = await api('/me/phone', { method: 'POST', body });
        auth.set(r.user); toast.success('Mobile number added'); emit('linked');
    } catch (e) { errors.value = e.errors || { code: [friendlyError(e)] }; } finally { busy.value = false; }
}
</script>
<template>
    <form class="card flex flex-col gap-4 p-6" @submit.prevent="step === 1 ? send() : confirm()">
        <div class="flex items-center gap-3"><span class="flex size-10 items-center justify-center rounded-[8px] bg-forest-100 text-forest-700"><Smartphone class="size-5" /></span><div><p class="font-extrabold text-forest-900">Add your mobile number</p><p class="text-xs text-muted">Needed for stand verification and SMS updates on your deed, messages and notices.</p></div></div>
        <Field v-if="step === 1" id="link-phone" v-model="phone" label="Mobile number" type="tel" inputmode="tel" autocomplete="tel" placeholder="077 123 4567" required :error="errors.phone?.[0]" />
        <template v-else>
            <Field id="link-code" v-model="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" :maxlength="6" required :error="errors.code?.[0] || errors.phone?.[0]" />
            <p v-if="dev" class="rounded-[8px] border border-dashed border-gold-500 bg-gold-100 px-3 py-2 text-xs font-bold text-gold-700">Local development code: {{ dev }}</p>
        </template>
        <button :id="step === 1 ? 'link-send' : 'link-confirm'" class="btn btn-gold self-start" :disabled="busy">{{ step === 1 ? 'Send code' : 'Confirm' }}</button>
    </form>
</template>
