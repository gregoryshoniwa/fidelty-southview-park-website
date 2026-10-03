import { createApp } from 'vue';
import { createPinia } from 'pinia';
import 'vue-sonner/style.css';
import App from './App.vue';
import { router } from './router.js';

const el = document.getElementById('app');
const app = createApp(App);
app.provide('paymentsLive', el.dataset.paymentsLive === '1');
app.provide('env', el.dataset.env);
app.use(createPinia()).use(router).mount(el);

if ('serviceWorker' in navigator && location.protocol === 'https:') {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}
