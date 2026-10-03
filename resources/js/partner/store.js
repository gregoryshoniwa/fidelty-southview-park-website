import { defineStore } from 'pinia';
import { papi, setPartnerId } from './http.js';
import { api } from '@/shared/api.js';

export const usePortal = defineStore('portal', {
    state: () => ({ me: null, stats: null, loading: false }),
    getters: {
        partner: (s) => s.me?.partner || null,
        partners: (s) => s.me?.partners || [],
        modules: (s) => s.me?.partner?.modules || [],
    },
    actions: {
        has(module) { return !module || this.modules.includes(module); },
        async load(force = false) {
            if (this.me && !force) return this.me;
            this.loading = true;
            try {
                const me = await papi('/me', { quiet: true });
                this.me = me;
                if (me?.partner?.id) setPartnerId(me.partner.id);
                return me;
            } finally {
                this.loading = false;
            }
        },
        async loadStats() {
            try { this.stats = await papi('/stats', { quiet: true }); } catch (_) { /* non-critical */ }
            return this.stats;
        },
        async switchTo(id) {
            setPartnerId(id);
            this.stats = null;
            await this.load(true);
            await this.loadStats();
        },
        async logout() {
            try { await api('/auth/logout', { method: 'POST', quiet: true }); } catch (_) { /* already signed out */ }
            setPartnerId(null);
            this.$reset();
        },
    },
});
