<script setup>
import { ref, reactive, onMounted } from 'vue';
import { Plus, ReceiptText } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import EmptyState from '@/shared/EmptyState.vue';
import StatusChip from '@/shared/StatusChip.vue';
import Modal from '@/shared/Modal.vue';
import Field from '@/shared/Field.vue';
import { papi, fmt, ApiError } from '../http.js';
import PageHeader from '../components/PageHeader.vue';

const rows = ref([]);
const loading = ref(true);
const open = ref(false);
const saving = ref(false);
const today = () => new Date().toISOString().slice(0, 10);
const blank = () => ({ learner_ref: '', learner_name: '', parent_stand: '', description: '', amount: '', currency: 'USD', due_on: today() });
const form = reactive(blank());
const errors = reactive({});

async function load() {
    try { rows.value = (await papi('/invoices')).data || []; } catch (_) { /* toast shown */ } finally { loading.value = false; }
}

function startNew() {
    Object.assign(form, blank());
    Object.keys(errors).forEach((k) => delete errors[k]);
    open.value = true;
}

async function save() {
    Object.keys(errors).forEach((k) => delete errors[k]);
    saving.value = true;
    try {
        const body = { ...form, amount: form.amount === '' ? null : Number(form.amount), parent_stand: form.parent_stand.trim() || null };
        const res = await papi('/invoices', { method: 'POST', body });
        open.value = false;
        toast.success('Invoice created', { description: form.parent_stand ? 'If a verified parent lives on that stand, they have been notified.' : 'Add a stand number to notify a parent.' });
        load();
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            for (const k of Object.keys(e.errors)) errors[k] = e.first(k);
        }
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="animate-fade">
        <PageHeader eyebrow="Invoices" title="School invoices" text="Fee invoices for learners. Add the parent's stand number to send it straight to a verified resident.">
            <button type="button" class="btn btn-gold" @click="startNew"><Plus class="size-4" aria-hidden="true" /> New invoice</button>
        </PageHeader>

        <div v-if="loading" class="flex flex-col gap-2"><div v-for="i in 5" :key="i" class="skeleton h-12 w-full" /></div>
        <EmptyState v-else-if="!rows.length" :icon="ReceiptText" title="No invoices yet" text="Create your first invoice to bill a learner's parent." />
        <template v-else>
            <div class="card hidden overflow-x-auto md:block">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="border-b border-line bg-cream text-xs uppercase tracking-wider text-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-extrabold">Learner</th>
                            <th scope="col" class="px-4 py-3 font-extrabold">Parent</th>
                            <th scope="col" class="px-4 py-3 font-extrabold">Description</th>
                            <th scope="col" class="px-4 py-3 text-right font-extrabold">Amount</th>
                            <th scope="col" class="px-4 py-3 font-extrabold">Due</th>
                            <th scope="col" class="px-4 py-3 font-extrabold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="i in rows" :key="i.id" class="border-b border-line last:border-0">
                            <td class="px-4 py-3"><span class="block font-bold text-ink">{{ i.learner_name }}</span><span class="font-mono text-xs text-muted">{{ i.learner_ref }}</span></td>
                            <td class="px-4 py-3" :class="i.parent ? '' : 'text-muted'">{{ i.parent || 'Not linked' }}</td>
                            <td class="px-4 py-3">{{ i.description }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-bold text-ink">{{ fmt.money(i.amount, i.currency) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-muted">{{ fmt.date(i.due_on) }}</td>
                            <td class="px-4 py-3"><StatusChip :status="i.status" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ul class="flex flex-col gap-2 md:hidden">
                <li v-for="i in rows" :key="i.id" class="card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-bold text-ink">{{ i.learner_name }} <span class="font-mono text-xs font-normal text-muted">{{ i.learner_ref }}</span></p>
                            <p class="text-sm text-muted">{{ i.description }}</p>
                        </div>
                        <StatusChip :status="i.status" />
                    </div>
                    <p class="mt-2 text-sm"><strong class="text-ink">{{ fmt.money(i.amount, i.currency) }}</strong> <span class="text-muted">· due {{ fmt.date(i.due_on) }} · {{ i.parent || 'Not linked' }}</span></p>
                </li>
            </ul>
        </template>

        <Modal v-model:open="open" title="New invoice" description="The parent is notified when the stand matches a verified resident." size="lg">
            <form class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="save">
                <Field id="i-ref" v-model="form.learner_ref" label="Learner number" required :maxlength="40" :error="errors.learner_ref" />
                <Field id="i-name" v-model="form.learner_name" label="Learner name" required :maxlength="120" :error="errors.learner_name" />
                <Field id="i-stand" v-model="form.parent_stand" class="sm:col-span-2" label="Parent's stand number (optional)" :maxlength="20" help="For example 1234. Leave blank if you do not know it." :error="errors.parent_stand" />
                <Field id="i-desc" v-model="form.description" class="sm:col-span-2" label="Description" required :maxlength="200" placeholder="Term 3 tuition" :error="errors.description" />
                <Field id="i-amount" v-model="form.amount" label="Amount" type="number" inputmode="decimal" required :error="errors.amount" />
                <div>
                    <label for="i-cur" class="label">Currency<span class="text-danger" aria-hidden="true"> *</span></label>
                    <select id="i-cur" v-model="form.currency" class="input" required>
                        <option value="USD">USD</option>
                        <option value="ZWG">ZWG</option>
                    </select>
                    <p v-if="errors.currency" class="error" role="alert">{{ errors.currency }}</p>
                </div>
                <Field id="i-due" v-model="form.due_on" label="Due date" type="date" required :error="errors.due_on" />
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" class="btn btn-quiet" @click="open = false">Cancel</button>
                    <button type="submit" class="btn btn-gold" :disabled="saving">{{ saving ? 'Creating' : 'Create invoice' }}</button>
                </div>
            </form>
        </Modal>
    </div>
</template>
