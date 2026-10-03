<script setup>
import { computed, watch } from 'vue';
import { useRoute, useRouter, RouterView, RouterLink } from 'vue-router';
import { Toaster } from 'vue-sonner';
import { House, CreditCard, MessageSquare, Users, Bell, Settings, LogOut, Landmark, FileText, Vote, Megaphone, ShieldAlert, GraduationCap, ArrowLeft } from 'lucide-vue-next';
import { useAuth } from './store.js';
import AssistantWidget from '@/assistant/AssistantWidget.vue';

const auth = useAuth();
const route = useRoute();
const router = useRouter();
const chrome = computed(() => auth.signedIn && !['login', 'login-email'].includes(route.name));
const isRoot = computed(() => ['home', 'pay', 'inbox', 'community', 'notices'].includes(route.name));

const tabs = [
    { to: '/', label: 'Home', icon: House, name: 'home' },
    { to: '/pay', label: 'Pay', icon: CreditCard, name: 'pay' },
    { to: '/inbox', label: 'Messages', icon: MessageSquare, name: 'inbox' },
    { to: '/community', label: 'Community', icon: Users, name: 'community' },
    { to: '/settings', label: 'Settings', icon: Settings, name: 'settings' },
];
const side = [
    { to: '/', label: 'My Home', icon: House },
    { to: '/deed', label: 'Title deed', icon: Landmark },
    { to: '/agreement', label: 'My Agreement', icon: FileText },
    { to: '/pay', label: 'Pay bills', icon: CreditCard },
    { to: '/inbox', label: 'Messages', icon: MessageSquare },
    { to: '/notices', label: 'Notices', icon: Megaphone },
    { to: '/polls', label: 'Polls', icon: Vote },
    { to: '/community', label: 'Community', icon: Users },
    { to: '/schools', label: 'Schools', icon: GraduationCap },
    { to: '/security', label: 'Security', icon: ShieldAlert },
    { to: '/settings', label: 'Settings', icon: Settings },
];

function active(to) { return to === '/' ? route.path === '/' : route.path.startsWith(to); }
async function logout() { await auth.logout(); router.replace({ name: 'login' }); }
watch(() => route.fullPath, () => { if (auth.signedIn) auth.refreshUnread(); });
</script>

<template>
    <Toaster position="top-center" rich-colors close-button />
    <div v-if="!chrome" class="min-h-dvh"><RouterView /></div>
    <div v-else class="min-h-dvh lg:flex">
        <aside class="hidden w-64 shrink-0 flex-col bg-forest-900 px-4 py-6 text-cream lg:sticky lg:top-0 lg:flex lg:h-dvh">
            <a href="/" class="mb-6 flex items-center gap-3 px-2">
                <img src="/images/logo-96.webp" width="40" height="40" alt="" class="size-10 rounded-[8px] bg-white">
                <span class="leading-tight"><span class="block font-serif text-[15px] font-bold">Southview Park</span><span class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-gold-500">Residents</span></span>
            </a>
            <nav class="flex flex-1 flex-col gap-1 overflow-y-auto" aria-label="App">
                <RouterLink v-for="l in side" :key="l.to" :to="l.to" class="flex min-h-11 items-center gap-3 rounded-[6px] px-3 text-sm font-semibold transition"
                    :class="active(l.to) ? 'bg-gold-500/15 text-cream' : 'text-cream/75 hover:bg-white/5 hover:text-cream'" :aria-current="active(l.to) ? 'page' : undefined">
                    <component :is="l.icon" class="size-[18px]" />{{ l.label }}
                </RouterLink>
            </nav>
            <button class="mt-4 flex min-h-11 items-center gap-3 rounded-[6px] px-3 text-sm font-semibold text-cream/70 hover:bg-white/5" @click="logout"><LogOut class="size-[18px]" />Sign out</button>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col pb-20 lg:pb-0">
            <header class="sticky top-0 z-30 border-b border-line bg-white/90 backdrop-blur">
                <div class="mx-auto flex h-14 max-w-4xl items-center gap-3 px-4">
                    <button v-if="!isRoot" class="-ml-2 inline-flex size-10 items-center justify-center rounded-[6px] text-forest-900 hover:bg-sand" aria-label="Back" @click="router.back()"><ArrowLeft class="size-5" /></button>
                    <img v-else src="/images/logo-96.webp" width="32" height="32" alt="" class="size-8 rounded-[6px] lg:hidden">
                    <h1 class="flex-1 truncate font-serif text-lg font-bold text-forest-900">{{ route.meta.title }}</h1>
                    <RouterLink to="/notifications" class="relative inline-flex size-10 items-center justify-center rounded-[6px] text-forest-900 hover:bg-sand" :aria-label="`Notifications, ${auth.unread} unread`">
                        <Bell class="size-5" />
                        <span v-if="auth.unread" class="absolute right-1.5 top-1.5 flex min-w-4 items-center justify-center rounded-full bg-gold-500 px-1 text-[10px] font-extrabold text-forest-900">{{ auth.unread > 9 ? '9+' : auth.unread }}</span>
                    </RouterLink>
                </div>
            </header>
            <main id="main" class="mx-auto w-full max-w-4xl flex-1 px-4 py-5 sm:py-8">
                <RouterView v-slot="{ Component }">
                    <Transition name="page" mode="out-in"><component :is="Component" :key="route.fullPath" /></Transition>
                </RouterView>
            </main>
        </div>

        <nav class="fixed inset-x-0 bottom-0 z-40 flex border-t border-line bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="Tabs">
            <RouterLink v-for="t in tabs" :key="t.to" :to="t.to" class="flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 text-[11px] font-bold"
                :class="active(t.to) ? 'text-forest-700' : 'text-muted'" :aria-current="active(t.to) ? 'page' : undefined">
                <component :is="t.icon" class="size-[22px]" :stroke-width="active(t.to) ? 2.4 : 2" />{{ t.label }}
            </RouterLink>
        </nav>
    </div>
    <AssistantWidget :signed-in="auth.signedIn" :bottom-offset="chrome ? 76 : 16" />
</template>

<style>
.page-enter-active, .page-leave-active { transition: opacity .16s ease, transform .16s ease; }
.page-enter-from { opacity: 0; transform: translateY(6px); }
.page-leave-to { opacity: 0; }
</style>
