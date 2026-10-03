<script setup>
defineProps({ icon: Object, title: String, text: String });
</script>
<template>
    <div class="flex flex-col items-center gap-3 rounded-[12px] border border-dashed border-line bg-white px-6 py-12 text-center">
        <span class="flex size-12 items-center justify-center rounded-full bg-forest-100 text-forest-700"><component :is="icon" class="size-6" /></span>
        <p class="font-extrabold text-forest-900">{{ title }}</p>
        <p v-if="text" class="max-w-sm text-sm text-muted">{{ text }}</p>
        <slot />
    </div>
</template>
