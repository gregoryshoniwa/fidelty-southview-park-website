<script setup>
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
const props = defineProps({ meta: Object });
const emit = defineEmits(['page']);
</script>
<template>
    <nav v-if="meta && meta.last_page > 1" class="mt-4 flex items-center justify-between gap-3" aria-label="Pagination">
        <p class="text-sm text-muted">Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} requests</p>
        <div class="flex gap-2">
            <button type="button" class="btn btn-sm btn-outline" :disabled="meta.current_page <= 1" @click="emit('page', meta.current_page - 1)">
                <ChevronLeft class="size-4" aria-hidden="true" /> Previous
            </button>
            <button type="button" class="btn btn-sm btn-outline" :disabled="meta.current_page >= meta.last_page" @click="emit('page', meta.current_page + 1)">
                Next <ChevronRight class="size-4" aria-hidden="true" />
            </button>
        </div>
    </nav>
</template>
