<script setup>
import { Toaster } from 'vue-sonner';
</script>
<template>
    <RouterView />
    <Toaster position="top-right" rich-colors close-button />
</template>
