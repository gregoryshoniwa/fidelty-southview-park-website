<script setup>
import Modal from './Modal.vue';
const open = defineModel('open', { type: Boolean, default: false });
const props = defineProps({ title: String, description: String, confirmLabel: { type: String, default: 'Confirm' }, danger: Boolean, busy: Boolean });
const emit = defineEmits(['confirm']);
</script>
<template>
    <Modal v-model:open="open" :title="title" :description="description" size="sm">
        <slot />
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="btn btn-quiet" @click="open = false">Cancel</button>
            <button type="button" class="btn" :class="danger ? 'bg-danger text-white hover:bg-red-700' : 'btn-gold'" :disabled="busy" @click="emit('confirm')">{{ confirmLabel }}</button>
        </div>
    </Modal>
</template>
