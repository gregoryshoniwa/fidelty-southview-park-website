<script setup>
import { ref, computed } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';

const model = defineModel();
const props = defineProps({ label: String, id: String, type: { type: String, default: 'text' }, error: String, help: String, placeholder: String, autocomplete: String, inputmode: String, required: Boolean, rows: Number, maxlength: Number });

// Password fields get a show/hide button.
const revealed = ref(false);
const isPassword = computed(() => props.type === 'password');
const inputType = computed(() => (isPassword.value && revealed.value ? 'text' : props.type));
</script>
<template>
    <div>
        <label :for="id" class="label">{{ label }}<span v-if="required" class="text-danger" aria-hidden="true"> *</span></label>
        <textarea v-if="type === 'textarea'" :id="id" v-model="model" :rows="rows || 4" :placeholder="placeholder" :required="required" :maxlength="maxlength" class="input py-3" :aria-invalid="!!error" :aria-describedby="error ? id + '-err' : help ? id + '-help' : undefined" />
        <div v-else class="relative">
            <input :id="id" v-model="model" :type="inputType" :placeholder="placeholder" :autocomplete="autocomplete" :inputmode="inputmode" :required="required" :maxlength="maxlength" class="input" :class="{ 'pr-12': isPassword }" :aria-invalid="!!error" :aria-describedby="error ? id + '-err' : help ? id + '-help' : undefined" />
            <button v-if="isPassword" type="button" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-[8px] text-muted hover:text-forest-700 focus-visible:text-forest-700"
                    :aria-label="revealed ? 'Hide password' : 'Show password'" :aria-pressed="revealed" :title="revealed ? 'Hide password' : 'Show password'" @click="revealed = !revealed">
                <EyeOff v-if="revealed" class="size-5" aria-hidden="true" /><Eye v-else class="size-5" aria-hidden="true" />
            </button>
        </div>
        <p v-if="error" :id="id + '-err'" class="error" role="alert">{{ error }}</p>
        <p v-else-if="help" :id="id + '-help'" class="help">{{ help }}</p>
    </div>
</template>
