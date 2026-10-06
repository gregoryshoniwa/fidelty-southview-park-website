<script setup>
import { ref, onMounted, computed, inject } from 'vue';
import { useRoute } from 'vue-router';
import { toast } from 'vue-sonner';
import { Check, Upload, FileText, Loader2, MessageSquare, CreditCard } from 'lucide-vue-next';
import { api, download, fmt } from '@/shared/api.js';
import Skeleton from '@/shared/Skeleton.vue';
import StatusChip from '@/shared/StatusChip.vue';
import Modal from '@/shared/Modal.vue';
import Thread from '@/shared/Thread.vue';

const route = useRoute(); const paymentsLive = inject('paymentsLive');
const req = ref(null); const thread = ref(null); const sending = ref(false);
const upOpen = ref(false); const kind = ref('agreement_of_sale'); const file = ref(null); const consent = ref(false); const uploading = ref(false); const upErr = ref('');
const kinds = { agreement_of_sale: 'Agreement of Sale', national_id: 'National ID', passport: 'Passport', proof_of_residence: 'Proof of residence', bank_statement: 'Bank statement or payslip', other: 'Other' };

async function load() {
    req.value = (await api('/requests/' + route.params.ref)).data;
    if (req.value.thread) thread.value = (await api('/threads/' + req.value.thread)).data;
}
onMounted(load);
const isDeed = computed(() => req.value?.service.slug === 'title-deed-tracker');

async function upload() {
    if (!file.value) return;
    uploading.value = true; upErr.value = '';
    const fd = new FormData(); fd.append('kind', kind.value); fd.append('file', file.value); fd.append('consent', consent.value ? '1' : '0');
    try { await api(`/requests/${req.value.reference}/documents`, { method: 'POST', body: fd }); toast.success('Document uploaded. The partner has been told.'); upOpen.value = false; file.value = null; await load(); }
    catch (e) { upErr.value = e.first('file') || e.first('consent') || e.message; } finally { uploading.value = false; }
}
async function send(text, clear) {
    sending.value = true;
    try {
        if (thread.value) thread.value = (await api(`/threads/${thread.value.reference}/messages`, { method: 'POST', body: { body: text } })).data;
        else { await api(`/requests/${req.value.reference}/messages`, { method: 'POST', body: { body: text } }); await load(); }
        clear();
    } finally { sending.value = false; }
}
function eventText(e) {
    return { opened: 'File opened', status_change: 'Updated' + (e.payload?.to?.step ? ` to step ${e.payload.to.step}` : '') + (e.payload?.note ? `: ${e.payload.note}` : ''), document_added: 'Document added: ' + (kinds[e.payload?.kind] || e.payload?.kind), payment: 'Payment received' }[e.type] || e.type;
}
</script>

<template>
    <Skeleton v-if="!req" :lines="5" />
    <div v-else class="flex flex-col gap-5">
        <section class="rounded-[14px] bg-forest-900 p-5 text-cream">
            <div class="flex items-start justify-between gap-3"><div><p class="eyebrow text-gold-500">{{ req.partner?.name }}</p><h2 class="font-serif text-2xl font-bold">{{ req.service.name }}</h2><p class="text-sm text-cream/70">Reference {{ req.reference }}</p></div><StatusChip :status="req.status" :label="req.status_label" /></div>
            <template v-if="req.steps.length">
                <div class="mt-4 flex items-end justify-between text-sm"><span class="text-cream/80">{{ req.step_label }}</span><span class="font-serif text-xl font-bold text-gold-400">Step {{ req.step }} of {{ req.steps.length }}</span></div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/15"><div class="h-full rounded-full bg-gold-500 transition-all duration-700" :style="{ width: (req.step / req.steps.length) * 100 + '%' }" /></div>
            </template>
        </section>

        <div class="grid gap-5 lg:grid-cols-[1fr_1fr]">
            <section v-if="req.steps.length" class="card p-5">
                <h3 class="eyebrow mb-4 text-muted">Progress</h3>
                <ol class="flex flex-col">
                    <li v-for="(s, i) in req.steps" :key="s" class="flex gap-3">
                        <div class="flex flex-col items-center">
                            <span class="flex size-7 items-center justify-center rounded-full text-xs font-extrabold" :class="i + 1 < req.step ? 'bg-forest-700 text-white' : i + 1 === req.step ? 'bg-gold-500 text-forest-900' : 'border-[1.5px] border-line text-muted'"><Check v-if="i + 1 < req.step" class="size-4" /><template v-else>{{ i + 1 }}</template></span>
                            <span v-if="i < req.steps.length - 1" class="my-1 w-0.5 flex-1 min-h-5" :class="i + 1 < req.step ? 'bg-forest-700' : 'bg-line'" />
                        </div>
                        <div class="pb-4"><p class="text-sm font-bold" :class="i + 1 <= req.step ? 'text-forest-900' : 'text-muted'">{{ s }}</p>
                            <RouterLink v-if="isDeed && i + 1 === req.step && i === 3" to="/pay" class="btn btn-gold btn-sm mt-2"><CreditCard class="size-4" />{{ paymentsLive ? 'Pay council rates' : 'Council payments: coming soon' }}</RouterLink>
                        </div>
                    </li>
                </ol>
            </section>

            <section class="card flex flex-col p-5">
                <div class="mb-3 flex items-center justify-between"><h3 class="eyebrow text-muted">Your documents</h3><button class="btn btn-sm btn-outline" @click="upOpen = true"><Upload class="size-4" />Upload</button></div>
                <ul v-if="req.documents.length" class="divide-y divide-line">
                    <li v-for="d in req.documents" :key="d.id" class="flex items-center gap-3 py-3 text-sm">
                        <FileText class="size-5 text-forest-700" /><span class="flex-1"><span class="block font-semibold text-forest-900">{{ d.kind_label }}</span><span class="text-xs text-muted">{{ d.name }} · {{ fmt.size(d.size) }}</span></span>
                        <button class="text-xs font-bold text-forest-700 hover:underline" @click="download('/documents/' + d.id, d.name)">View</button>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted">No documents yet. {{ isDeed ? 'Upload your Agreement of Sale, national ID or passport, and proof of residence.' : '' }}</p>
                <h3 class="eyebrow mb-2 mt-6 text-muted">History</h3>
                <ul class="flex flex-col gap-2 text-sm"><li v-for="(e, i) in [...req.events].reverse()" :key="i" class="flex justify-between gap-3"><span>{{ eventText(e) }}</span><span class="shrink-0 text-xs text-muted">{{ fmt.ago(e.at) }}</span></li></ul>
            </section>
        </div>

        <section class="card flex h-[460px] flex-col overflow-hidden">
            <div class="flex items-center gap-2 border-b border-line px-5 py-3"><MessageSquare class="size-4 text-forest-700" /><h3 class="font-bold text-forest-900">Messages with {{ req.partner?.name }}</h3></div>
            <Thread :messages="thread?.messages || []" :busy="sending" :closed="thread?.status === 'closed'" :placeholder="'Write to ' + (req.partner?.name || 'the partner')" @send="send" />
        </section>

        <Modal v-model:open="upOpen" title="Upload a document" description="PDF or photo, up to 8 MB. Only the partner on this file can see it.">
            <form class="flex flex-col gap-4" @submit.prevent="upload">
                <div><label for="dkind" class="label">Document</label><select id="dkind" v-model="kind" class="input"><option v-for="(l, k) in kinds" :key="k" :value="k">{{ l }}</option></select></div>
                <div><label for="dfile" class="label">File</label><input id="dfile" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" capture="environment" class="input py-2.5" @change="file = $event.target.files[0]"></div>
                <label class="flex items-start gap-3 text-sm text-muted"><input v-model="consent" type="checkbox" class="mt-0.5 size-4 accent-forest-700" required><span>I agree that {{ req.partner?.name }} may store and use this document to process my request.</span></label>
                <p v-if="upErr" class="error">{{ upErr }}</p>
                <button class="btn btn-gold" :disabled="uploading || !file || !consent"><Loader2 v-if="uploading" class="size-4 animate-spin" />Upload</button>
            </form>
        </Modal>
    </div>
</template>
