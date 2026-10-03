<script setup>
import { ref, nextTick, watch, onMounted } from 'vue';
import { Send } from 'lucide-vue-next';
import { fmt } from './api.js';

const props = defineProps({ messages: { type: Array, default: () => [] }, busy: Boolean, closed: Boolean, placeholder: { type: String, default: 'Write a message' } });
const emit = defineEmits(['send']);
const body = ref('');
const list = ref(null);

function scroll() { nextTick(() => { if (list.value) list.value.scrollTop = list.value.scrollHeight; }); }
watch(() => props.messages.length, scroll);
onMounted(scroll);

function send() {
    const text = body.value.trim();
    if (!text || props.busy) return;
    emit('send', text, () => { body.value = ''; });
}
</script>
<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <div ref="list" class="flex flex-1 flex-col gap-3 overflow-y-auto bg-cream px-4 py-4 sm:px-5" role="log" aria-live="polite">
            <div v-for="m in messages" :key="m.id" class="flex flex-col" :class="m.mine ? 'items-end' : 'items-start'">
                <div class="max-w-[85%] whitespace-pre-line rounded-[10px] px-3.5 py-2.5 text-[15px] leading-relaxed"
                     :class="m.mine ? 'rounded-br-[2px] bg-forest-900 text-cream' : 'rounded-bl-[2px] border border-line bg-white text-ink'">
                    <span class="mb-1 block text-[11px] font-extrabold" :class="m.mine ? 'text-gold-400' : 'text-muted'">{{ m.from_label }} · {{ fmt.datetime(m.at) }}</span>
                    {{ m.body }}
                </div>
            </div>
            <p v-if="!messages.length" class="m-auto text-sm text-muted">No messages yet.</p>
        </div>
        <form v-if="!closed" class="flex items-end gap-2 border-t border-line bg-white p-3" @submit.prevent="send">
            <label for="thread-body" class="sr-only">Message</label>
            <textarea id="thread-body" v-model="body" rows="1" maxlength="2000" :placeholder="placeholder" class="input min-h-11 flex-1 resize-none py-2.5"
                      @keydown.enter.exact.prevent="send" />
            <button type="submit" class="btn btn-gold min-h-11 px-4" :disabled="busy || !body.trim()" aria-label="Send"><Send class="size-4" /></button>
        </form>
        <p v-else class="border-t border-line bg-white p-4 text-center text-sm text-muted">This conversation is closed.</p>
    </div>
</template>
