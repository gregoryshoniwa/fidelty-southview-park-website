<script setup>
import { ref, onMounted, watch } from 'vue';
import { BadgeCheck } from 'lucide-vue-next';
import { api } from '@/shared/api.js';
import Skeleton from '@/shared/Skeleton.vue';
const type = ref(''); const items = ref(null);
async function load() { items.value = null; items.value = (await api('/pages' + (type.value ? '?type=' + type.value : ''))).data; }
onMounted(load); watch(type, load);
</script>
<template>
    <div class="flex flex-col gap-4">
        <div class="flex gap-2"><button v-for="(l, k) in { '': 'All', business: 'Businesses', church: 'Churches', school: 'Schools' }" :key="k" class="btn btn-sm" :class="type === k ? 'btn-green' : 'btn-outline'" @click="type = k">{{ l }}</button></div>
        <Skeleton v-if="!items" />
        <div v-else class="grid gap-3 sm:grid-cols-2">
            <RouterLink v-for="p in items" :key="p.slug" :to="'/community/' + p.slug" class="card card-hover flex gap-3 overflow-hidden p-3">
                <img :src="p.cover || '/images/covers/' + p.type + '.webp'" alt="" class="size-20 shrink-0 rounded-[8px] object-cover" loading="lazy">
                <span class="min-w-0"><span class="flex items-center gap-1.5 text-[11px] font-extrabold uppercase tracking-wider text-muted">{{ p.type }}<BadgeCheck v-if="p.verified" class="size-3.5 text-forest-700" /></span><span class="block truncate font-extrabold text-forest-900">{{ p.name }}</span><span class="line-clamp-2 text-xs text-muted">{{ p.tagline }}</span><span class="mt-1 block text-xs font-semibold" :class="p.following ? 'text-forest-700' : 'text-muted'">{{ p.following ? 'Following' : p.followers + ' followers' }}</span></span>
            </RouterLink>
        </div>
    </div>
</template>
