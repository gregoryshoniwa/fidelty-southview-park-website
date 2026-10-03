<script setup>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { toast } from 'vue-sonner';
import { Lock } from 'lucide-vue-next';
import { api } from '@/shared/api.js';
import Field from '@/shared/Field.vue';
const router = useRouter(); const route = useRoute();
const cats = { general: 'General', deeds: 'Title deeds', payments: 'Payments', security: 'Security', water: 'Water and roads', complaint: 'Complaint', suggestion: 'Suggestion' };
const category = ref(cats[route.query.category] ? route.query.category : 'general');
const subject = ref(typeof route.query.subject === 'string' ? route.query.subject.slice(0, 120) : ''); const body = ref(''); const busy = ref(false); const errors = ref({});
async function send() {
    busy.value = true; errors.value = {};
    try { const r = await api('/threads', { method: 'POST', body: { subject: subject.value, category: category.value, body: body.value } }); toast.success('Sent. Your reference is ' + r.data.reference); router.replace('/inbox/' + r.data.reference); }
    catch (e) { errors.value = e.errors || {}; } finally { busy.value = false; }
}
</script>
<template>
    <form class="card mx-auto flex max-w-lg flex-col gap-4 p-6" @submit.prevent="send">
        <p class="flex items-center gap-2 rounded-[8px] bg-forest-100 p-3 text-sm text-forest-900"><Lock class="size-4" />Only the committee can read this. Other residents never see it.</p>
        <div><label for="cat" class="label">Topic</label><select id="cat" v-model="category" class="input"><option v-for="(l, k) in cats" :key="k" :value="k">{{ l }}</option></select></div>
        <Field id="subj" v-model="subject" label="Subject" :maxlength="120" required :error="errors.subject?.[0]" />
        <Field id="body" v-model="body" type="textarea" label="Your message" :rows="6" :maxlength="2000" required :error="errors.body?.[0]" help="Please do not include your ID number." />
        <button class="btn btn-gold" :disabled="busy">Send to the committee</button>
    </form>
</template>
