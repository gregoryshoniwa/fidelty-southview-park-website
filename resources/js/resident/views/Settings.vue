<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import { Download, Trash2, LogOut, ShieldCheck } from 'lucide-vue-next';
import { api, download } from '@/shared/api.js';
import Field from '@/shared/Field.vue';
import ConfirmDialog from '@/shared/ConfirmDialog.vue';
import { useAuth } from '../store.js';
const auth = useAuth(); const router = useRouter();
const form = ref({ name: auth.user.name, email: auth.user.email || '', locale: auth.user.locale, notification_prefs: { sms: true, push: true, ...(auth.user.notification_prefs || {}) } });
const busy = ref(false); const errors = ref({}); const del = ref(false); const reason = ref('');
async function save() {
    busy.value = true; errors.value = {};
    try { const r = await api('/me', { method: 'PATCH', body: { ...form.value, email: form.value.email || null } }); auth.set(r.user); toast.success('Saved'); }
    catch (e) { errors.value = e.errors || {}; } finally { busy.value = false; }
}
async function requestDelete() { const r = await api('/me/delete-request', { method: 'POST', body: { reason: reason.value } }); del.value = false; toast.success('Request ' + r.reference + ' sent. We complete deletions within 30 days.'); }
async function logout() { await auth.logout(); router.replace('/login'); }
</script>
<template>
    <div class="mx-auto flex max-w-lg flex-col gap-5">
        <form class="card flex flex-col gap-4 p-6" @submit.prevent="save">
            <h2 class="font-serif text-xl font-bold text-forest-900">Profile</h2>
            <Field id="sname" v-model="form.name" label="Name" required :error="errors.name?.[0]" />
            <Field id="semail" v-model="form.email" label="Email (optional)" type="email" autocomplete="email" :error="errors.email?.[0]" />
            <p class="text-sm text-muted">Phone: {{ auth.user.phone_masked }}<span v-if="auth.verified"> · Stand {{ auth.user.resident.stand }}</span></p>
            <div><label for="slocale" class="label">Language</label><select id="slocale" v-model="form.locale" class="input"><option value="en">English</option><option value="sn">Shona</option><option value="nd">Ndebele</option></select></div>
            <fieldset class="flex flex-col gap-2"><legend class="label">Notifications</legend>
                <label class="flex items-center gap-3 text-sm"><input v-model="form.notification_prefs.sms" type="checkbox" class="size-4 accent-forest-700">SMS for updates on my requests and messages</label>
                <label class="flex items-center gap-3 text-sm"><input v-model="form.notification_prefs.push" type="checkbox" class="size-4 accent-forest-700">In-app notifications</label>
            </fieldset>
            <button class="btn btn-gold self-start" :disabled="busy">Save</button>
        </form>
        <section class="card flex flex-col gap-3 p-6">
            <h2 class="flex items-center gap-2 font-serif text-xl font-bold text-forest-900"><ShieldCheck class="size-5 text-forest-700" />Your data</h2>
            <p class="text-sm text-muted">You can download everything we hold about you, or ask us to delete it.</p>
            <div class="flex flex-wrap gap-2"><button class="btn btn-outline btn-sm" @click="download('/me/export', 'my-southview-data.json')"><Download class="size-4" />Download my data</button><button class="btn btn-sm border-[1.5px] border-danger text-danger hover:bg-red-50" @click="del = true"><Trash2 class="size-4" />Delete my account</button></div>
        </section>
        <button class="btn btn-quiet" @click="logout"><LogOut class="size-4" />Sign out</button>
        <ConfirmDialog v-model:open="del" title="Delete your account?" description="We delete your documents and anonymise your records within 30 days. Your stand will no longer be verified." confirm-label="Request deletion" danger @confirm="requestDelete">
            <label for="dreason" class="label">Reason (optional)</label><textarea id="dreason" v-model="reason" maxlength="500" rows="3" class="input py-3" />
        </ConfirmDialog>
    </div>
</template>
