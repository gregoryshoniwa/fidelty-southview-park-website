<script setup>
import { ref, onMounted, watch } from 'vue';
import { Megaphone, Pin } from 'lucide-vue-next';
import { api, fmt } from '@/shared/api.js';
import Skeleton from '@/shared/Skeleton.vue';
import EmptyState from '@/shared/EmptyState.vue';
const cats = { '': 'All', urgent: 'Urgent', services: 'Services', deeds: 'Deeds', finance: 'Finance', events: 'Events', security: 'Security' };
const cat = ref(''); const items = ref(null);
async function load() { items.value = null; items.value = (await api('/notices' + (cat.value ? '?category=' + cat.value : ''))).data; }
onMounted(load); watch(cat, load);
</script>
<template>
    <div class="flex flex-col gap-4">
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1"><button v-for="(l, k) in cats" :key="k" class="btn btn-sm shrink-0" :class="cat === k ? 'btn-green' : 'btn-outline'" @click="cat = k">{{ l }}</button></div>
        <Skeleton v-if="!items" />
        <EmptyState v-else-if="!items.length" :icon="Megaphone" title="No notices here yet" />
        <div v-else class="card divide-y divide-line">
            <a v-for="n in items" :key="n.slug" :href="'/notices/' + n.slug" class="flex flex-col gap-1 p-4 hover:bg-cream" :class="{ 'bg-cream': n.sponsored }">
                <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider" :class="n.sponsored ? 'text-muted' : 'text-gold-600'"><Pin v-if="n.pinned" class="size-3.5" />{{ n.sponsored ? 'From the association' : n.category }} · {{ fmt.date(n.published_at) }}</span>
                <span class="font-extrabold text-forest-900">{{ n.title }}</span>
                <span v-if="n.excerpt" class="text-sm text-muted">{{ n.excerpt }}</span>
                <span v-if="n.signed_by" class="text-xs font-semibold text-muted">Signed: {{ n.signed_by }}</span>
            </a>
        </div>
    </div>
</template>
