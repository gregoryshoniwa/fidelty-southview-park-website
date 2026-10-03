<script setup>
defineProps({ lines: { type: Number, default: 3 } });
</script>
<template>
    <div class="flex flex-col gap-3" aria-busy="true" aria-label="Loading">
        <div v-for="i in lines" :key="i" class="skeleton h-16 w-full" />
    </div>
</template>
