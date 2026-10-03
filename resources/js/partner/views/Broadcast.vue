<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { Megaphone, Send } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import EmptyState from '@/shared/EmptyState.vue';
import ConfirmDialog from '@/shared/ConfirmDialog.vue';
import { papi, fmt, ApiError } from '../http.js';
import PageHeader from '../components/PageHeader.vue';

const MAX = 480;
const form = reactive({ body: '', audience: 'open_requests' });
const errors = reactive({ body: '', audience: '' });
const confirmOpen = ref(false);
const sending = ref(false);
const list = ref([]);
const loading = ref(true);

const audiences = { open_requests: 'Residents with open requests', all_clients: 'Everyone who has used your services' };
const remaining = computed(() => MAX - form.body.length);

async function load() {
    try { list.value = (await papi('/broadcasts')).data || []; } catch (_) { /* toast shown */ } finally { loading.value = false; }
}

function ask() {
    errors.body = ''; errors.audience = '';
    if (form.body.trim().length < 5) { errors.body = 'Write at least 5 characters.'; return; }
    confirmOpen.value = true;
}

async function send() {
    sending.value = true;
    try {
        const res = await papi('/broadcasts', { method: 'POST', body: { body: form.body.trim(), audience: form.audience } });
        confirmOpen.value = false;
        form.body = '';
        toast.success(`Sent to ${res.recipients} resident${res.recipients === 1 ? '' : 's'}`);
        load();
    } catch (e) {
        confirmOpen.value = false;
        if (e instanceof ApiError && e.status === 422) { errors.body = e.first('body') || ''; errors.audience = e.first('audience') || ''; }
    } finally {
        sending.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="animate-fade">
        <PageHeader eyebrow="Broadcast" title="Message your residents" text="Send a short notice to Southview residents who use your services. Each resident gets it as a notification from the association." />

        <div class="grid gap-4 lg:grid-cols-5">
            <form class="card flex flex-col gap-4 p-5 lg:col-span-2 lg:self-start" novalidate @submit.prevent="ask">
                <div>
                    <label for="b-body" class="label">Message</label>
                    <textarea id="b-body" v-model="form.body" rows="6" :maxlength="MAX" class="input py-3" placeholder="For example: Our Southview office is closed on Friday for the public holiday."
                              :aria-invalid="!!errors.body" aria-describedby="b-count" />
                    <p id="b-count" class="help flex justify-between" aria-live="polite">
                        <span>Keep it short and plain. No links.</span>
                        <span :class="remaining < 40 ? 'font-bold text-gold-600' : ''">{{ form.body.length }}/{{ MAX }}</span>
                    </p>
                    <p v-if="errors.body" class="error" role="alert">{{ errors.body }}</p>
                </div>
                <div>
                    <label for="b-aud" class="label">Send to</label>
                    <select id="b-aud" v-model="form.audience" class="input">
                        <option v-for="(label, key) in audiences" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <p v-if="errors.audience" class="error" role="alert">{{ errors.audience }}</p>
                </div>
                <button type="submit" class="btn btn-gold" :disabled="sending || !form.body.trim()"><Send class="size-4" aria-hidden="true" /> Review and send</button>
            </form>

            <section class="lg:col-span-3" aria-labelledby="past-h">
                <h2 id="past-h" class="mb-3 font-serif text-xl font-bold text-forest-900">Sent broadcasts</h2>
                <div v-if="loading" class="flex flex-col gap-2"><div v-for="i in 3" :key="i" class="skeleton h-24 w-full" /></div>
                <EmptyState v-else-if="!list.length" :icon="Megaphone" title="Nothing sent yet" text="Your past broadcasts will be listed here." />
                <ul v-else class="flex flex-col gap-2">
                    <li v-for="b in list" :key="b.id" class="card p-4">
                        <p class="whitespace-pre-line text-ink">{{ b.body }}</p>
                        <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted">
                            <span class="chip chip-grey">{{ audiences[b.audience] || b.audience }}</span>
                            <span>{{ b.recipients }} recipient{{ b.recipients === 1 ? '' : 's' }}</span>
                            <time :datetime="b.sent_at">{{ fmt.datetime(b.sent_at) }}</time>
                        </p>
                    </li>
                </ul>
            </section>
        </div>

        <ConfirmDialog v-model:open="confirmOpen" title="Send to all your residents?" :description="audiences[form.audience] + '. This cannot be undone.'" confirm-label="Send broadcast" :busy="sending" @confirm="send">
            <p class="whitespace-pre-line rounded-[8px] border border-line bg-cream p-3 text-sm text-ink">{{ form.body }}</p>
        </ConfirmDialog>
    </div>
</template>
