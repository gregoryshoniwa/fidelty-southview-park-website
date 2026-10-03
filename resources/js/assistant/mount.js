import { createApp, h } from 'vue';
import AssistantWidget from './AssistantWidget.vue';

export function mountAssistant(el, { signedIn, open }) {
    const host = document.createElement('div');
    el.appendChild(host);
    createApp({ render: () => h(AssistantWidget, { signedIn, startOpen: open }) }).mount(host);
}
