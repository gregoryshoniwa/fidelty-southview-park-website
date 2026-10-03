<script setup>
import { Compass } from 'lucide-vue-next';
import EmptyState from '@/shared/EmptyState.vue';
</script>
<template>
    <div class="animate-fade py-10">
        <EmptyState :icon="Compass" title="Page not found" text="That page does not exist in the partner portal. Check the address or go back to your queue.">
            <RouterLink to="/" class="btn btn-gold btn-sm">Go to the queue</RouterLink>
        </EmptyState>
    </div>
</template>
