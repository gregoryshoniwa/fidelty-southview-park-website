<script setup>
import { computed } from 'vue';
const props = defineProps({ status: String, label: String });
const tone = computed(() => ({
    open: 'chip-green', waiting_partner: 'chip-green', approved: 'chip-green', paid: 'chip-green', answered: 'chip-green', resolved: 'chip-green', verified: 'chip-green',
    waiting_resident: 'chip-gold', waiting_payment: 'chip-gold', pending: 'chip-gold', responding: 'chip-gold', unpaid: 'chip-gold', initiated: 'chip-gold',
    failed: 'chip-red', cancelled: 'chip-red', urgent: 'chip-red',
}[props.status] || 'chip-grey'));
</script>
<template><span class="chip" :class="tone">{{ label || (status || '').replaceAll('_', ' ') }}</span></template>
