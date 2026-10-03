<script setup>
import { LogOut } from 'lucide-vue-next';
import { usePortal } from '../store.js';
import Logo from './Logo.vue';
import NavLinks from './NavLinks.vue';

defineProps({ idPrefix: { type: String, default: 'side' } });
const emit = defineEmits(['navigate', 'switch', 'logout']);
const portal = usePortal();
</script>
<template>
    <div class="flex h-full flex-col gap-6 p-4">
        <div class="flex items-center gap-3 px-1 pt-1">
            <Logo />
            <div class="min-w-0">
                <p class="eyebrow text-cream/60">Partner portal</p>
                <p class="truncate font-serif text-lg font-bold leading-tight text-gold-400" :title="portal.partner?.name">{{ portal.partner?.name }}</p>
            </div>
        </div>

        <div v-if="portal.partners.length > 1" class="px-1">
            <label :for="idPrefix + '-partner'" class="eyebrow mb-1.5 block text-cream/60">Working for</label>
            <select :id="idPrefix + '-partner'" class="block min-h-11 w-full rounded-[6px] border border-forest-700 bg-forest-800 px-3 text-sm font-bold text-cream focus:border-gold-500 focus:outline-none"
                    :value="portal.partner?.id" @change="emit('switch', Number($event.target.value))">
                <option v-for="p in portal.partners" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
        </div>

        <NavLinks class="flex-1" @navigate="emit('navigate')" />

        <div class="border-t border-forest-700 pt-4">
            <p class="truncate px-3 text-sm font-bold text-cream">{{ portal.me?.user?.name }}</p>
            <p v-if="portal.me?.user?.role" class="truncate px-3 text-xs capitalize text-cream/60">{{ portal.me.user.role }}</p>
            <button type="button" class="mt-2 flex min-h-11 w-full items-center gap-3 rounded-[6px] px-3 text-[15px] font-bold text-cream/75 hover:bg-forest-800 hover:text-cream" @click="emit('logout')">
                <LogOut class="size-5" aria-hidden="true" /> Sign out
            </button>
        </div>
    </div>
</template>
