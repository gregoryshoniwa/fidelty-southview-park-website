<script setup>
import { ref } from 'vue';
defineProps({ size: { type: String, default: 'size-11' } });
const failed = ref(false);
</script>
<template>
    <span class="flex shrink-0 items-center justify-center overflow-hidden rounded-[8px] bg-white" :class="size">
        <img v-if="!failed" src="/images/logo-96.webp" alt="Fidelity Southview Park Residents Association" width="96" height="96" class="size-full object-contain" @error="failed = true" />
        <span v-else class="font-serif text-sm font-bold text-forest-900" aria-label="Fidelity Southview Park Residents Association">SP</span>
    </span>
</template>
