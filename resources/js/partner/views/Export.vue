<script setup>
import { ref } from 'vue';
import { FileDown, FileSpreadsheet } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { pdownload, STATUS_OPTIONS } from '../http.js';
import PageHeader from '../components/PageHeader.vue';

const status = ref('');
const busy = ref(false);

async function run() {
    busy.value = true;
    try {
        const qs = status.value ? '?status=' + encodeURIComponent(status.value) : '';
        const stamp = new Date().toISOString().slice(0, 10).replaceAll('-', '');
        await pdownload('/export' + qs, `requests-${status.value || 'all'}-${stamp}.csv`);
        toast.success('Export downloaded');
    } catch (_) { /* toast shown */ } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="animate-fade">
        <PageHeader eyebrow="Export" title="Download your requests" text="A CSV file you can open in Excel or Google Sheets. Exports are logged and limited to 10 an hour." />

        <section class="card max-w-xl p-5 sm:p-6">
            <div class="flex items-start gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-[8px] bg-forest-100 text-forest-700"><FileSpreadsheet class="size-6" aria-hidden="true" /></span>
                <div>
                    <h2 class="font-serif text-xl font-bold text-forest-900">Requests CSV</h2>
                    <p class="mt-1 text-sm text-muted">Columns: reference, service, resident, stand, status, step, opened, updated.</p>
                </div>
            </div>
            <form class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end" @submit.prevent="run">
                <div class="flex-1">
                    <label for="x-status" class="label">Which requests</label>
                    <select id="x-status" v-model="status" class="input">
                        <option value="">All requests</option>
                        <option v-for="o in STATUS_OPTIONS" :key="o.value" :value="o.value">{{ o.label }} only</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-gold" :disabled="busy"><FileDown class="size-4" aria-hidden="true" /> {{ busy ? 'Preparing' : 'Download CSV' }}</button>
            </form>
            <p class="help mt-4">The file contains residents' names and stand numbers. Store it securely and delete it when you no longer need it.</p>
        </section>
    </div>
</template>
