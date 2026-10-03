<script setup>
defineProps({ eyebrow: String, title: String, text: String });
</script>
<template>
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p v-if="eyebrow" class="eyebrow text-gold-600">{{ eyebrow }}</p>
            <h1 class="mt-1 font-serif text-3xl font-bold text-forest-900 sm:text-[34px]">{{ title }}</h1>
            <p v-if="text" class="mt-1 max-w-2xl text-sm text-muted">{{ text }}</p>
        </div>
        <div v-if="$slots.default" class="flex flex-wrap gap-2"><slot /></div>
    </header>
</template>
