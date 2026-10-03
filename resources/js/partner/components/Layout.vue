<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { DialogRoot, DialogPortal, DialogOverlay, DialogContent, DialogTitle, DialogDescription, DialogClose } from 'reka-ui';
import { Menu, X } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { usePortal } from '../store.js';
import SidebarBody from './SidebarBody.vue';
import Logo from './Logo.vue';

const portal = usePortal();
const router = useRouter();
const route = useRoute();
const drawer = ref(false);
const switchKey = ref(0);
let timer = null;

async function switchPartner(id) {
    drawer.value = false;
    try {
        await portal.switchTo(id);
        switchKey.value++;
        toast.success('Now working for ' + portal.partner?.name);
        router.push('/');
    } catch (e) {
        if (e?.status === 401) router.replace('/login');
    }
}

async function logout() {
    drawer.value = false;
    await portal.logout();
    router.replace('/login');
}

watch(() => route.fullPath, () => portal.loadStats());
onMounted(() => {
    portal.loadStats();
    timer = setInterval(() => { if (document.visibilityState === 'visible') portal.loadStats(); }, 60000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <div class="min-h-dvh bg-sand lg:flex">
        <a href="#main" class="sr-only z-50 rounded-[6px] bg-gold-500 px-4 py-2 font-bold text-ink focus:not-sr-only focus:fixed focus:left-3 focus:top-3">Skip to content</a>

        <!-- Desktop sidebar -->
        <aside class="sticky top-0 hidden h-dvh w-64 shrink-0 overflow-y-auto bg-forest-900 lg:block">
            <SidebarBody id-prefix="side" @switch="switchPartner" @logout="logout" />
        </aside>

        <!-- Mobile top bar -->
        <header class="sticky top-0 z-40 flex items-center gap-3 bg-forest-900 px-4 py-2.5 lg:hidden">
            <Logo size="size-9" />
            <div class="min-w-0 flex-1">
                <p class="eyebrow text-[10px] text-cream/60">Partner portal</p>
                <p class="truncate font-serif text-base font-bold leading-tight text-gold-400">{{ portal.partner?.name }}</p>
            </div>
            <DialogRoot v-model:open="drawer">
                <button type="button" class="relative inline-flex size-11 items-center justify-center rounded-[6px] text-cream hover:bg-forest-800" aria-label="Open menu" :aria-expanded="drawer" @click="drawer = true">
                    <Menu class="size-6" aria-hidden="true" />
                    <span v-if="portal.stats?.unread_messages" class="absolute right-1.5 top-1.5 size-2.5 rounded-full bg-gold-500" aria-hidden="true" />
                </button>
                <DialogPortal>
                    <DialogOverlay class="fixed inset-0 z-[60] bg-forest-950/60 animate-fade" />
                    <DialogContent class="fixed inset-y-0 left-0 z-[61] w-[min(18rem,85vw)] overflow-y-auto bg-forest-900 shadow-2xl animate-fade focus:outline-none">
                        <DialogTitle class="sr-only">Menu</DialogTitle>
                        <DialogDescription class="sr-only">Partner portal navigation</DialogDescription>
                        <DialogClose class="absolute right-2 top-2 z-10 inline-flex size-10 items-center justify-center rounded-[6px] text-cream hover:bg-forest-800" aria-label="Close menu">
                            <X class="size-5" aria-hidden="true" />
                        </DialogClose>
                        <SidebarBody id-prefix="drawer" @navigate="drawer = false" @switch="switchPartner" @logout="logout" />
                    </DialogContent>
                </DialogPortal>
            </DialogRoot>
        </header>

        <main id="main" class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8" tabindex="-1">
            <RouterView v-slot="{ Component }">
                <component :is="Component" :key="switchKey + ':' + (route.name === 'messages' ? 'messages' : route.fullPath)" />
            </RouterView>
        </main>
    </div>
</template>
