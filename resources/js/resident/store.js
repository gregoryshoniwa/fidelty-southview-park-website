import { defineStore } from 'pinia';
import { api } from '@/shared/api.js';

export const useAuth = defineStore('auth', {
    state: () => ({ user: null, loaded: false, unread: 0 }),
    getters: {
        signedIn: (s) => !!s.user,
        verified: (s) => s.user?.resident?.verification_status === 'verified',
        firstName: (s) => (s.user?.name || '').split(' ')[0],
    },
    actions: {
        async load(force = false) {
            if (this.loaded && !force) return this.user;
            try { this.user = (await api('/me', { quiet: true })).user; } catch { this.user = null; }
            this.loaded = true;
            if (this.user) this.refreshUnread();
            return this.user;
        },
        async refreshUnread() {
            try { this.unread = (await api('/notifications', { quiet: true })).unread; } catch {}
        },
        set(user) { this.user = user; this.loaded = true; },
        async logout() {
            try { await api('/auth/logout', { method: 'POST' }); } finally { this.user = null; }
            import('./firebase.js').then((m) => m.firebaseSignOut()).catch(() => {});
        },
    },
});
