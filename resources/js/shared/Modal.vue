<script setup>
import { DialogRoot, DialogPortal, DialogOverlay, DialogContent, DialogTitle, DialogDescription, DialogClose } from 'reka-ui';
import { X } from 'lucide-vue-next';

const open = defineModel('open', { type: Boolean, default: false });
defineProps({ title: String, description: String, size: { type: String, default: 'md' } });
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-[70] bg-forest-950/60 backdrop-blur-sm animate-fade" />
            <DialogContent
                class="fixed inset-x-0 bottom-0 z-[71] max-h-[92dvh] overflow-y-auto rounded-t-[16px] bg-white p-6 shadow-2xl animate-rise focus:outline-none sm:inset-auto sm:left-1/2 sm:top-1/2 sm:w-full sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-[14px]"
                :class="{ 'sm:max-w-md': size === 'md', 'sm:max-w-xl': size === 'lg', 'sm:max-w-sm': size === 'sm' }"
            >
                <DialogClose class="absolute right-3 top-3 inline-flex size-9 items-center justify-center rounded-[6px] text-muted hover:bg-sand" aria-label="Close">
                    <X class="size-5" />
                </DialogClose>
                <DialogTitle class="pr-10 font-serif text-2xl font-bold text-forest-900">{{ title }}</DialogTitle>
                <DialogDescription v-if="description" class="mt-1 text-sm text-muted">{{ description }}</DialogDescription>
                <div class="mt-5"><slot /></div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
