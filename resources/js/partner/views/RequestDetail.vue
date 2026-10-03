<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ArrowLeft, BadgeCheck, Check, FileText, Download, ExternalLink, CreditCard, History, MessageSquarePlus, AlertCircle } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import StatusChip from '@/shared/StatusChip.vue';
import EmptyState from '@/shared/EmptyState.vue';
import ConfirmDialog from '@/shared/ConfirmDialog.vue';
import Thread from '@/shared/Thread.vue';
import { papi, pdownload, popen, fmt, statusLabel, STATUS_OPTIONS, ApiError } from '../http.js';
import { usePortal } from '../store.js';

const props = defineProps({ reference: String });
const portal = usePortal();

const req = ref(null);
const loading = ref(true);
const missing = ref(false);

const form = reactive({ step: 1, status: 'open', note: '' });
const errors = reactive({ step: '', status: '', note: '' });
const saving = ref(false);
const confirmOpen = ref(false);

const thread = ref(null);
const threadLoading = ref(false);
const sending = ref(false);
const firstMessage = ref('');
const firstError = ref('');

const canUpdate = computed(() => portal.has('status_updates'));
const canDocs = computed(() => portal.has('documents'));
const canMessage = computed(() => portal.has('messages'));
const steps = computed(() => req.value?.steps || []);
const isFinal = computed(() => ['closed', 'cancelled'].includes(req.value?.status));

function fill(data) {
    req.value = data;
    form.step = data.step || 1;
    form.status = data.status;
    form.note = '';
}

async function load() {
    loading.value = true;
    try {
        const res = await papi('/requests/' + encodeURIComponent(props.reference), { quiet: true });
        fill(res.data);
        if (res.data.thread && canMessage.value) loadThread(res.data.thread);
    } catch (e) {
        if (e?.status === 404) missing.value = true;
        else toast.error(e?.message || 'Could not load this request.');
    } finally {
        loading.value = false;
    }
}

async function loadThread(refId) {
    threadLoading.value = true;
    try { thread.value = (await papi('/threads/' + encodeURIComponent(refId))).data; } catch (_) { /* toast shown */ } finally { threadLoading.value = false; }
}

function requestSave() {
    if (['closed', 'cancelled'].includes(form.status) && form.status !== req.value.status) confirmOpen.value = true;
    else save();
}

async function save() {
    Object.keys(errors).forEach((k) => { errors[k] = ''; });
    saving.value = true;
    try {
        const body = { step: Number(form.step) || null, status: form.status };
        if (form.note.trim()) body.note = form.note.trim();
        const res = await papi('/requests/' + encodeURIComponent(props.reference), { method: 'PATCH', body });
        fill(res.data);
        confirmOpen.value = false;
        toast.success('Resident notified by SMS');
        portal.loadStats();
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            errors.step = e.first('step') || ''; errors.status = e.first('status') || ''; errors.note = e.first('note') || '';
        }
        confirmOpen.value = false;
    } finally {
        saving.value = false;
    }
}

async function reply(text, clear) {
    sending.value = true;
    try {
        const res = await papi('/threads/' + encodeURIComponent(thread.value.reference) + '/messages', { method: 'POST', body: { body: text } });
        thread.value = res.data;
        clear();
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) toast.error(e.first('body') || e.message);
    } finally {
        sending.value = false;
    }
}

async function startThread() {
    firstError.value = '';
    const text = firstMessage.value.trim();
    if (!text) { firstError.value = 'Write a message first.'; return; }
    sending.value = true;
    try {
        const res = await papi('/requests/' + encodeURIComponent(props.reference) + '/messages', { method: 'POST', body: { body: text } });
        firstMessage.value = '';
        req.value.thread = res.thread;
        toast.success('Message sent to the resident');
        await loadThread(res.thread);
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) firstError.value = e.first('body') || e.message;
    } finally {
        sending.value = false;
    }
}

async function getDoc(d, openTab) {
    try {
        if (openTab) await popen('/documents/' + encodeURIComponent(d.id), d.name);
        else await pdownload('/documents/' + encodeURIComponent(d.id), d.name);
    } catch (_) { /* toast shown by api() */ }
}

function eventText(e) {
    const p = e.payload || {};
    switch (e.type) {
        case 'opened': return 'Request opened' + (p.service ? ' for ' + p.service : '');
        case 'status_change': {
            const to = p.to || {};
            const label = to.step && steps.value[to.step - 1] ? `step ${to.step}: ${steps.value[to.step - 1]}` : '';
            return 'Updated to ' + statusLabel(to.status) + (label ? ', ' + label : '');
        }
        case 'document_added': return 'Document added' + (p.kind ? ' (' + String(p.kind).replaceAll('_', ' ') + ')' : '');
        case 'payment': return 'Payment received: ' + fmt.money(p.amount, p.currency);
        default: return String(e.type || 'Update').replaceAll('_', ' ');
    }
}
const actorLabel = (a) => ({ resident: 'Resident', partner_user: 'Your team', system: 'System', committee: 'Committee' }[a] || a || '');

onMounted(load);
</script>

<template>
    <div class="animate-fade">
        <RouterLink to="/" class="btn btn-quiet btn-sm -ml-3 mb-3"><ArrowLeft class="size-4" aria-hidden="true" /> Back to queue</RouterLink>

        <div v-if="loading" class="flex flex-col gap-4">
            <div class="skeleton h-24 w-full" />
            <div class="grid gap-4 lg:grid-cols-3"><div class="skeleton h-80 lg:col-span-2" /><div class="skeleton h-80" /></div>
        </div>

        <EmptyState v-else-if="missing" :icon="AlertCircle" title="Request not found" text="It may belong to another organisation, or the reference is wrong.">
            <RouterLink to="/" class="btn btn-outline btn-sm">Back to queue</RouterLink>
        </EmptyState>

        <template v-else-if="req">
            <header class="card p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="eyebrow text-gold-600">{{ req.service?.name }}</p>
                        <h1 class="mt-1 break-all font-mono text-2xl font-bold text-forest-900 sm:text-3xl">{{ req.reference }}</h1>
                        <p class="mt-1 text-sm text-muted">Opened {{ fmt.date(req.created_at) }} · updated {{ fmt.ago(req.updated_at) }}</p>
                    </div>
                    <StatusChip :status="req.status" :label="statusLabel(req.status)" />
                </div>
                <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-line pt-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="eyebrow text-[10px] text-muted">Resident</dt>
                        <dd class="mt-1 font-bold text-ink">{{ req.resident?.name }}</dd>
                    </div>
                    <div>
                        <dt class="eyebrow text-[10px] text-muted">Stand</dt>
                        <dd class="mt-1 font-bold text-ink">{{ req.resident?.stand || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="eyebrow text-[10px] text-muted">Verification</dt>
                        <dd class="mt-1">
                            <span v-if="req.resident?.verified" class="chip chip-green"><BadgeCheck class="size-3.5" aria-hidden="true" /> Verified</span>
                            <span v-else class="chip chip-gold">Not verified</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="eyebrow text-[10px] text-muted">National ID</dt>
                        <dd class="mt-1 font-mono font-bold text-ink">{{ req.resident?.id_last4 ? '•••• ' + req.resident.id_last4 : '-' }}</dd>
                    </div>
                </dl>
            </header>

            <div class="mt-4 grid gap-4 lg:grid-cols-3">
                <div class="flex min-w-0 flex-col gap-4 lg:col-span-2">
                    <!-- Steps -->
                    <section class="card p-5" aria-labelledby="steps-h">
                        <h2 id="steps-h" class="font-serif text-xl font-bold text-forest-900">Progress</h2>
                        <ol v-if="steps.length" class="mt-4 flex flex-col">
                            <li v-for="(label, i) in steps" :key="i" class="relative flex gap-3 pb-5 last:pb-0" :aria-current="i + 1 === req.step ? 'step' : undefined">
                                <span v-if="i < steps.length - 1" class="absolute left-[13px] top-7 h-[calc(100%-1.75rem)] w-0.5" :class="i + 1 < req.step ? 'bg-forest-500' : 'bg-line'" aria-hidden="true" />
                                <span class="relative z-10 flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-extrabold"
                                      :class="i + 1 < req.step || (i + 1 === req.step && isFinal) ? 'bg-forest-700 text-cream' : i + 1 === req.step ? 'bg-gold-500 text-ink ring-4 ring-gold-100' : 'border-2 border-line bg-white text-muted'">
                                    <Check v-if="i + 1 < req.step || (i + 1 === req.step && isFinal)" class="size-4" aria-hidden="true" />
                                    <template v-else>{{ i + 1 }}</template>
                                </span>
                                <div class="pt-0.5">
                                    <p class="font-bold" :class="i + 1 <= req.step ? 'text-ink' : 'text-muted'">{{ label }}</p>
                                    <p v-if="i + 1 === req.step" class="text-xs text-gold-600">Current step</p>
                                </div>
                            </li>
                        </ol>
                        <p v-else class="mt-2 text-sm text-muted">This service has no defined steps.</p>
                    </section>

                    <!-- Documents -->
                    <section class="card p-5" aria-labelledby="docs-h">
                        <h2 id="docs-h" class="font-serif text-xl font-bold text-forest-900">Documents</h2>
                        <ul v-if="req.documents?.length" class="mt-3 divide-y divide-line">
                            <li v-for="d in req.documents" :key="d.id" class="flex flex-wrap items-center gap-3 py-3">
                                <FileText class="size-5 shrink-0 text-forest-700" aria-hidden="true" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-bold text-ink">{{ d.kind_label }}</p>
                                    <p class="truncate text-xs text-muted">{{ d.name }} · {{ fmt.size(d.size) }} · {{ fmt.date(d.uploaded_at) }}</p>
                                </div>
                                <div v-if="canDocs" class="flex gap-1">
                                    <button type="button" class="btn btn-quiet btn-sm" :aria-label="'Open ' + d.name" @click="getDoc(d, true)"><ExternalLink class="size-4" aria-hidden="true" /><span class="hidden sm:inline">Open</span></button>
                                    <button type="button" class="btn btn-outline btn-sm" :aria-label="'Download ' + d.name" @click="getDoc(d, false)"><Download class="size-4" aria-hidden="true" /><span class="hidden sm:inline">Download</span></button>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="mt-2 text-sm text-muted">No documents uploaded yet.</p>
                        <p v-if="req.documents?.length && !canDocs" class="help">The document viewer is not enabled for your organisation.</p>
                    </section>

                    <!-- Payments -->
                    <section class="card p-5" aria-labelledby="pay-h">
                        <h2 id="pay-h" class="font-serif text-xl font-bold text-forest-900">Payments</h2>
                        <ul v-if="req.payments?.length" class="mt-3 divide-y divide-line">
                            <li v-for="p in req.payments" :key="p.id" class="flex flex-wrap items-center gap-3 py-3">
                                <CreditCard class="size-5 shrink-0 text-forest-700" aria-hidden="true" />
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-ink">{{ fmt.money(p.amount, p.currency) }} <span class="font-normal text-muted">· {{ p.biller_label }}</span></p>
                                    <p class="text-xs text-muted">{{ p.reference }} · {{ fmt.datetime(p.paid_at || p.created_at) }}</p>
                                </div>
                                <StatusChip :status="p.status" />
                            </li>
                        </ul>
                        <p v-else class="mt-2 text-sm text-muted">No payments linked to this request.</p>
                    </section>

                    <!-- Events -->
                    <section class="card p-5" aria-labelledby="events-h">
                        <h2 id="events-h" class="flex items-center gap-2 font-serif text-xl font-bold text-forest-900"><History class="size-5 text-gold-600" aria-hidden="true" /> Activity</h2>
                        <ul v-if="req.events?.length" class="mt-3 flex flex-col gap-3">
                            <li v-for="(e, i) in [...req.events].reverse()" :key="i" class="border-l-2 border-line pl-3">
                                <p class="text-sm font-semibold text-ink">{{ eventText(e) }}</p>
                                <p v-if="e.payload?.note" class="mt-0.5 whitespace-pre-line text-sm text-muted">"{{ e.payload.note }}"</p>
                                <p class="text-xs text-muted">{{ actorLabel(e.actor) }} · <time :datetime="e.at">{{ fmt.datetime(e.at) }}</time></p>
                            </li>
                        </ul>
                        <p v-else class="mt-2 text-sm text-muted">No activity yet.</p>
                    </section>
                </div>

                <!-- Right column -->
                <div class="flex min-w-0 flex-col gap-4">
                    <section v-if="canUpdate" class="card p-5 lg:sticky lg:top-6" aria-labelledby="update-h">
                        <h2 id="update-h" class="font-serif text-xl font-bold text-forest-900">Update status</h2>
                        <p class="mt-1 text-sm text-muted">The resident gets an SMS with the step and your note.</p>
                        <form class="mt-4 flex flex-col gap-4" @submit.prevent="requestSave">
                            <div v-if="steps.length">
                                <label for="u-step" class="label">Step</label>
                                <select id="u-step" v-model.number="form.step" class="input">
                                    <option v-for="(label, i) in steps" :key="i" :value="i + 1">{{ i + 1 }}. {{ label }}</option>
                                </select>
                                <p v-if="errors.step" class="error" role="alert">{{ errors.step }}</p>
                            </div>
                            <div>
                                <label for="u-status" class="label">Status</label>
                                <select id="u-status" v-model="form.status" class="input">
                                    <option v-for="o in STATUS_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                                <p v-if="errors.status" class="error" role="alert">{{ errors.status }}</p>
                            </div>
                            <div>
                                <label for="u-note" class="label">Note to resident <span class="font-normal text-muted">(optional)</span></label>
                                <textarea id="u-note" v-model="form.note" rows="4" maxlength="1000" class="input py-3" placeholder="For example: Please bring the original title deed on Tuesday." aria-describedby="u-note-help" />
                                <p id="u-note-help" class="help">{{ form.note.length }}/1000 characters</p>
                                <p v-if="errors.note" class="error" role="alert">{{ errors.note }}</p>
                            </div>
                            <button type="submit" class="btn btn-gold w-full" :disabled="saving">{{ saving ? 'Saving' : 'Save and notify resident' }}</button>
                        </form>
                    </section>
                    <section v-else class="card p-5 text-sm text-muted">Status updates are not enabled for your organisation.</section>
                </div>
            </div>

            <!-- Thread -->
            <section v-if="canMessage" class="card mt-4 overflow-hidden" aria-labelledby="thread-h">
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <h2 id="thread-h" class="font-serif text-xl font-bold text-forest-900">Messages with {{ req.resident?.name }}</h2>
                    <RouterLink v-if="req.thread" :to="{ name: 'messages', params: { ref: req.thread } }" class="text-sm font-bold text-forest-700 underline decoration-gold-500 underline-offset-2">Open in Messages</RouterLink>
                </div>
                <div v-if="req.thread" class="flex h-[460px] flex-col">
                    <div v-if="threadLoading && !thread" class="p-5"><div class="skeleton h-40 w-full" /></div>
                    <Thread v-else-if="thread" :messages="thread.messages || []" :busy="sending" :closed="thread.status === 'closed'" placeholder="Reply to the resident" @send="reply" />
                </div>
                <form v-else class="flex flex-col gap-3 p-5" @submit.prevent="startThread">
                    <label for="first-msg" class="label">Start a conversation</label>
                    <textarea id="first-msg" v-model="firstMessage" rows="3" maxlength="2000" class="input py-3" placeholder="Ask the resident a question about this request"
                              :aria-invalid="!!firstError" :aria-describedby="firstError ? 'first-msg-err' : undefined" />
                    <p v-if="firstError" id="first-msg-err" class="error" role="alert">{{ firstError }}</p>
                    <button type="submit" class="btn btn-green self-start" :disabled="sending || !firstMessage.trim()">
                        <MessageSquarePlus class="size-4" aria-hidden="true" /> {{ sending ? 'Sending' : 'Send message' }}
                    </button>
                </form>
            </section>

            <ConfirmDialog v-model:open="confirmOpen" :title="form.status === 'closed' ? 'Close this request?' : 'Cancel this request?'"
                           :description="`${req.reference} will be marked ${statusLabel(form.status).toLowerCase()} and the resident will be told by SMS.`"
                           :confirm-label="form.status === 'closed' ? 'Close request' : 'Cancel request'" :danger="form.status === 'cancelled'" :busy="saving" @confirm="save" />
        </template>
    </div>
</template>
