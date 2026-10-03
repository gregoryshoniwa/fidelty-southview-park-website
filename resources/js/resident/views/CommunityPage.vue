<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { toast } from 'vue-sonner';
import { Bell, BellOff, MapPin, Phone, Flag, Info } from 'lucide-vue-next';
import { api, fmt } from '@/shared/api.js';
import Skeleton from '@/shared/Skeleton.vue';
const route = useRoute(); const p = ref(null); const busy = ref(false);
onMounted(async () => { p.value = (await api('/pages/' + route.params.slug)).data; });
async function toggle() {
    busy.value = true;
    try { const r = await api(`/pages/${p.value.slug}/follow`, { method: p.value.following ? 'DELETE' : 'POST' }); p.value.following = r.following; p.value.followers += r.following ? 1 : -1; toast.success(r.following ? 'Following ' + p.value.name : 'Unfollowed'); }
    finally { busy.value = false; }
}
async function report(post) { await api(`/posts/${post.id}/report`, { method: 'POST' }); toast.success('Reported to the Secretary. Thank you.'); }
</script>
<template>
    <Skeleton v-if="!p" />
    <div v-else class="flex flex-col gap-5">
        <section class="relative overflow-hidden rounded-[14px] bg-forest-900 p-6 text-cream">
            <img :src="p.cover || '/images/covers/' + p.type + '.webp'" alt="" class="absolute inset-0 size-full object-cover opacity-25">
            <div class="relative"><p class="eyebrow text-gold-500">{{ p.type }} · {{ p.verified ? 'Partner page' : 'Public listing' }}</p><h2 class="mt-1 font-serif text-3xl font-bold">{{ p.name }}</h2><p v-if="p.tagline" class="mt-1 text-cream/80">{{ p.tagline }}</p>
                <button class="btn mt-4" :class="p.following ? 'btn-ghost' : 'btn-gold'" :disabled="busy" @click="toggle"><component :is="p.following ? BellOff : Bell" class="size-4" />{{ p.following ? 'Following' : 'Follow' }} · {{ p.followers }}</button></div>
        </section>
        <p v-if="!p.verified" class="flex gap-2 rounded-[10px] border border-line bg-white p-4 text-sm text-muted"><Info class="size-4 shrink-0 text-forest-700" />This is a public listing from public sources. It is not yet a partner of the association.</p>
        <div class="card flex flex-col gap-2 p-5 text-sm">
            <p v-if="p.description" class="whitespace-pre-line text-[15px] text-ink">{{ p.description }}</p>
            <p v-if="p.address" class="flex gap-2 text-muted"><MapPin class="size-4" />{{ p.address }}</p>
            <p v-if="p.phone" class="flex gap-2 text-muted"><Phone class="size-4" /><a :href="'tel:' + p.phone" class="underline">{{ p.phone }}</a></p>
        </div>
        <section v-if="p.posts.length"><h3 class="eyebrow mb-3 text-muted">Updates</h3>
            <article v-for="post in p.posts" :key="post.id" class="card mb-3 p-5"><div class="flex justify-between text-xs text-muted"><span>{{ fmt.date(post.at) }}</span><button class="flex items-center gap-1 hover:text-danger" @click="report(post)"><Flag class="size-3.5" />Report</button></div><p class="mt-2 whitespace-pre-line">{{ post.body }}</p></article>
        </section>
    </div>
</template>
