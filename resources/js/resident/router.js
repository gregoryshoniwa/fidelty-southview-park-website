import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from './store.js';

const v = (name) => () => import(`./views/${name}.vue`);

export const router = createRouter({
    history: createWebHistory('/app/'),
    scrollBehavior: () => ({ top: 0 }),
    routes: [
        { path: '/login', name: 'login', component: v('Login'), meta: { guest: true, title: 'Sign in' } },
        { path: '/login/email', name: 'login-email', component: v('Login'), meta: { guest: true, title: 'Signing in' } },
        { path: '/', name: 'home', component: v('Home'), meta: { auth: true, title: 'My Home' } },
        { path: '/verify', name: 'verify', component: v('Verify'), meta: { auth: true, title: 'Verify my stand' } },
        { path: '/agreement', name: 'agreement', component: v('Agreement'), meta: { auth: true, verified: true, title: 'My Agreement' } },
        { path: '/deed', name: 'deed', component: v('Deed'), meta: { auth: true, verified: true, title: 'Title deed' } },
        { path: '/requests', name: 'requests', component: v('Requests'), meta: { auth: true, verified: true, title: 'My requests' } },
        { path: '/requests/:ref', name: 'request', component: v('RequestDetail'), meta: { auth: true, verified: true, title: 'Request' } },
        { path: '/services/:slug', name: 'service', component: v('ServiceApply'), meta: { auth: true, verified: true, title: 'Apply' } },
        { path: '/pay', name: 'pay', component: v('Pay'), meta: { auth: true, verified: true, title: 'Pay bills' } },
        { path: '/receipts', name: 'receipts', component: v('Receipts'), meta: { auth: true, verified: true, title: 'Receipts' } },
        { path: '/inbox', name: 'inbox', component: v('Inbox'), meta: { auth: true, title: 'Messages' } },
        { path: '/inbox/new', name: 'inbox-new', component: v('InboxNew'), meta: { auth: true, title: 'Write to the committee' } },
        { path: '/inbox/:ref', name: 'thread', component: v('ThreadView'), meta: { auth: true, title: 'Conversation' } },
        { path: '/notices', name: 'notices', component: v('Notices'), meta: { auth: true, title: 'Notices' } },
        { path: '/notifications', name: 'notifications', component: v('Notifications'), meta: { auth: true, title: 'Notifications' } },
        { path: '/polls', name: 'polls', component: v('Polls'), meta: { auth: true, verified: true, title: 'Polls' } },
        { path: '/community', name: 'community', component: v('Community'), meta: { auth: true, title: 'Community' } },
        { path: '/community/:slug', name: 'page', component: v('CommunityPage'), meta: { auth: true, title: 'Community' } },
        { path: '/schools', name: 'schools', component: v('Schools'), meta: { auth: true, verified: true, title: 'Schools' } },
        { path: '/security', name: 'security', component: v('Security'), meta: { auth: true, verified: true, title: 'Safety and security' } },
        { path: '/settings', name: 'settings', component: v('Settings'), meta: { auth: true, title: 'Settings' } },
        { path: '/:pathMatch(.*)*', name: 'missing', component: v('NotFound'), meta: { title: 'Not found' } },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuth();
    await auth.load();
    if (to.meta.auth && !auth.signedIn) return { name: 'login', query: { next: to.fullPath } };
    if (to.meta.guest && auth.signedIn) return { name: 'home' };
    if (to.meta.verified && !auth.verified) return { name: 'verify', query: { next: to.fullPath } };
});

router.afterEach((to) => {
    document.title = (to.meta.title ? to.meta.title + ' | ' : '') + 'My Southview';
});
