<script setup>
import { ref, onMounted } from 'vue';
import { ShieldAlert, MapPin } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import EmptyState from '@/shared/EmptyState.vue';
import StatusChip from '@/shared/StatusChip.vue';
import { papi, fmt } from '../http.js';
import PageHeader from '../components/PageHeader.vue';

const rows = ref([]);
const loading = ref(true);
const busy = ref('');
const statuses = [
    { value: 'open', label: 'Open' },
    { value: 'responding', label: 'Responding' },
    { value: 'resolved', label: 'Resolved' },
];

async function load() {
    try { rows.value = (await papi('/incidents')).data || []; } catch (_) { /* toast shown */ } finally { loading.value = false; }
}

async function setStatus(i, status) {
    const before = i.status;
    if (status === before) return;
    i.status = status;
    busy.value = i.reference;
    try {
        // Backend route is being switched to bind {incident} by reference.
        await papi('/incidents/' + encodeURIComponent(i.reference), { method: 'PATCH', body: { status } });
        toast.success(`${i.reference} marked ${status}. The resident has been told.`);
    } catch (_) {
        i.status = before;
    } finally {
        busy.value = '';
    }
}

onMounted(load);
</script>

<template>
    <div class="animate-fade">
        <PageHeader eyebrow="Security" title="Incidents" text="Reports from residents you protect. Update the status so they know help is coming." />

        <div v-if="loading" class="flex flex-col gap-2"><div v-for="i in 4" :key="i" class="skeleton h-28 w-full" /></div>
        <EmptyState v-else-if="!rows.length" :icon="ShieldAlert" title="No incidents reported" text="Incidents from your subscribed residents will appear here." />
        <ul v-else class="flex flex-col gap-3">
            <li v-for="i in rows" :key="i.reference" class="card p-4 sm:p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-sm font-bold text-forest-700">{{ i.reference }}</span>
                            <StatusChip :status="i.status" />
                            <span class="chip chip-grey">{{ String(i.category || '').replaceAll('_', ' ') }}</span>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-ink">{{ i.description }}</p>
                        <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                            <span class="inline-flex items-center gap-1"><MapPin class="size-3.5" aria-hidden="true" />{{ i.location || 'No location given' }}</span>
                            <span>{{ i.resident }} · Stand {{ i.stand || '-' }}</span>
                            <time :datetime="i.at" :title="fmt.datetime(i.at)">{{ fmt.ago(i.at) }}</time>
                        </p>
                    </div>
                    <div class="sm:w-44">
                        <label :for="'inc-' + i.reference" class="label">Status</label>
                        <select :id="'inc-' + i.reference" class="input min-h-11" :value="i.status" :disabled="busy === i.reference" @change="setStatus(i, $event.target.value)">
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>
