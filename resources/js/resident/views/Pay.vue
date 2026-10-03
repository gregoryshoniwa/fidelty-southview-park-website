<script setup>
import { ref, inject, onMounted, computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Landmark, Zap, Smartphone, Scale, GraduationCap, Tv, Store, Clock, BellRing, Lock } from 'lucide-vue-next';
import { api, fmt } from '@/shared/api.js';

const live = inject('paymentsLive');
const icons = { council: Landmark, zesa: Zap, airtime: Smartphone, legal: Scale, school: GraduationCap, dstv: Tv, merchant: Store };
const billers = ref([]); const biller = ref('zesa'); const ref_ = ref(''); const amount = ref(''); const currency = ref('USD');
const quote = ref(null); const busy = ref(false); const errors = ref({}); const notified = ref(false);
onMounted(async () => { billers.value = (await api('/billers')).data; });
const current = computed(() => billers.value.find((b) => b.code === biller.value));
let t; watch([biller, amount, currency], () => { clearTimeout(t); quote.value = null; if (live && Number(amount.value) >= 0.5) t = setTimeout(getQuote, 400); });
async function getQuote() { try { quote.value = (await api('/payments/quote', { method: 'POST', body: { biller_code: biller.value, amount: amount.value, currency: currency.value }, quiet: true })).data; } catch {} }
async function pay() {
    busy.value = true; errors.value = {};
    try { const r = await api('/payments', { method: 'POST', body: { biller_code: biller.value, biller_reference: ref_.value, amount: amount.value, currency: currency.value } }); location.href = r.checkout_url; }
    catch (e) { errors.value = e.errors || {}; busy.value = false; }
}
async function notify() { const r = await api('/payments/notify-me', { method: 'POST' }); notified.value = true; toast.success(r.message); }
</script>

<template>
    <div class="mx-auto flex max-w-xl flex-col gap-5">
        <section class="rounded-[14px] bg-forest-900 p-5 text-cream">
            <p class="eyebrow text-gold-500">TN CyberTech Bank gateway</p>
            <h2 class="font-serif text-2xl font-bold">Pay a bill</h2>
            <p class="text-sm text-cream/75">Your fee is shown before you confirm. Receipts are kept in your account.</p>
        </section>

        <section>
            <h3 class="eyebrow mb-3 text-muted">Choose a biller</h3>
            <div class="grid grid-cols-3 gap-2.5 sm:grid-cols-4">
                <button v-for="b in billers" :key="b.code" type="button" class="flex min-h-20 flex-col items-center justify-center gap-1.5 rounded-[10px] border-[1.5px] bg-white p-2 text-center text-xs font-bold text-forest-900 transition"
                    :class="biller === b.code ? 'border-gold-500 bg-gold-100' : 'border-line hover:border-gold-500'" :aria-pressed="biller === b.code" @click="biller = b.code">
                    <component :is="icons[b.code] || Store" class="size-5 text-forest-700" />{{ b.label.replace('City of Harare rates', 'Council rates').replace(', deed fees', '') }}
                </button>
            </div>
        </section>

        <section v-if="!live" class="card flex flex-col items-center gap-3 p-8 text-center animate-rise">
            <span class="flex size-16 items-center justify-center rounded-full bg-gold-100 text-gold-600"><Clock class="size-8" /></span>
            <h3 class="font-serif text-2xl font-bold text-forest-900">{{ current?.label || 'Payments' }}: coming soon</h3>
            <p class="max-w-sm text-muted">Online payments open as soon as the TN CyberTech Bank gateway is connected. Until then, please pay through your usual channels.</p>
            <button class="btn btn-gold mt-2" :disabled="notified" @click="notify"><BellRing class="size-4" />{{ notified ? 'We will tell you' : 'Tell me when it opens' }}</button>
        </section>

        <form v-else class="card flex flex-col gap-4 p-5" @submit.prevent="pay">
            <div><label for="bref" class="label">{{ current?.reference_label }}</label><input id="bref" v-model="ref_" class="input" required maxlength="60"><p v-if="errors.biller_reference" class="error">{{ errors.biller_reference[0] }}</p></div>
            <div><label for="amt" class="label">Amount</label>
                <div class="flex gap-2"><input id="amt" v-model="amount" inputmode="decimal" placeholder="0.00" class="input flex-1" required><select v-model="currency" class="input w-28" aria-label="Currency"><option>USD</option><option>ZWG</option></select></div>
                <p v-if="errors.amount" class="error">{{ errors.amount[0] }}</p></div>
            <div class="rounded-[10px] border border-line bg-cream px-4 text-sm">
                <div class="flex justify-between border-b border-line py-2.5"><span class="text-muted">Paying</span><span class="font-bold text-forest-900">{{ current?.label }}</span></div>
                <div class="flex justify-between border-b border-line py-2.5"><span class="text-muted">Platform fee</span><span class="font-extrabold text-gold-600">{{ quote ? fmt.money(quote.platform_fee, quote.currency) : 'US$' + (current?.fee || '0.00') }}</span></div>
                <div class="flex justify-between py-2.5"><span class="text-muted">Total</span><span class="font-extrabold text-forest-900">{{ quote ? fmt.money(quote.total, quote.currency) : '—' }}</span></div>
            </div>
            <button class="btn btn-gold" :disabled="busy">Continue to secure checkout</button>
            <p class="flex items-center justify-center gap-1.5 text-xs text-muted"><Lock class="size-3.5" />EcoCash, ZIPIT, card and transfer via TN CyberTech Bank</p>
        </form>
    </div>
</template>
