import { createRouter, createWebHistory } from 'vue-router';
import { toast } from 'vue-sonner';
import { usePortal } from './store.js';

const routes = [
    { path: '/login', name: 'login', component: () => import('./views/Login.vue'), meta: { guest: true, title: 'Sign in' } },
    {
        path: '/',
        component: () => import('./components/Layout.vue'),
        children: [
            { path: '', name: 'queue', component: () => import('./views/Queue.vue'), meta: { title: 'Queue' } },
            { path: 'requests/:ref', name: 'request', component: () => import('./views/RequestDetail.vue'), props: (r) => ({ reference: r.params.ref }), meta: { module: 'queue', title: 'Request' } },
            { path: 'messages/:ref?', name: 'messages', component: () => import('./views/Messages.vue'), props: (r) => ({ reference: r.params.ref || '' }), meta: { module: 'messages', title: 'Messages' } },
            { path: 'broadcast', name: 'broadcast', component: () => import('./views/Broadcast.vue'), meta: { module: 'broadcast', title: 'Broadcast' } },
            { path: 'settlements', name: 'settlements', component: () => import('./views/Settlements.vue'), meta: { module: 'settlements', title: 'Settlements' } },
            { path: 'invoices', name: 'invoices', component: () => import('./views/Invoices.vue'), meta: { module: 'invoices', title: 'Invoices' } },
            { path: 'incidents', name: 'incidents', component: () => import('./views/Incidents.vue'), meta: { module: 'incidents', title: 'Incidents' } },
            { path: 'export', name: 'export', component: () => import('./views/Export.vue'), meta: { module: 'export', title: 'Export' } },
            { path: ':pathMatch(.*)*', name: 'not-found', component: () => import('./views/NotFound.vue'), meta: { title: 'Not found' } },
        ],
    },
];

const router = createRouter({
    history: createWebHistory('/partner/'),
    routes,
    scrollBehavior: (to, from, saved) => saved || { top: 0 },
});

router.beforeEach(async (to) => {
    const portal = usePortal();
    if (to.meta.guest) return true;
    try {
        await portal.load();
    } catch (e) {
        if (e?.status === 403) toast.error(e.message || 'Your account has no active partner access.');
        return { name: 'login', query: to.fullPath !== '/' ? { next: to.fullPath } : {} };
    }
    if (to.meta.module && !portal.has(to.meta.module)) return { name: 'queue' };
    return true;
});

router.afterEach((to) => {
    document.title = (to.meta.title ? to.meta.title + ' | ' : '') + 'Partner portal | Southview Park Residents';
});

export default router;
