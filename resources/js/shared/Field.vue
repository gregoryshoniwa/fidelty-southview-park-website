<script setup>
const model = defineModel();
defineProps({ label: String, id: String, type: { type: String, default: 'text' }, error: String, help: String, placeholder: String, autocomplete: String, inputmode: String, required: Boolean, rows: Number, maxlength: Number });
</script>
<template>
    <div>
        <label :for="id" class="label">{{ label }}<span v-if="required" class="text-danger" aria-hidden="true"> *</span></label>
        <textarea v-if="type === 'textarea'" :id="id" v-model="model" :rows="rows || 4" :placeholder="placeholder" :required="required" :maxlength="maxlength" class="input py-3" :aria-invalid="!!error" :aria-describedby="error ? id + '-err' : help ? id + '-help' : undefined" />
        <input v-else :id="id" v-model="model" :type="type" :placeholder="placeholder" :autocomplete="autocomplete" :inputmode="inputmode" :required="required" :maxlength="maxlength" class="input" :aria-invalid="!!error" :aria-describedby="error ? id + '-err' : help ? id + '-help' : undefined" />
        <p v-if="error" :id="id + '-err'" class="error" role="alert">{{ error }}</p>
        <p v-else-if="help" :id="id + '-help'" class="help">{{ help }}</p>
    </div>
</template>
