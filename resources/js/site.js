// Public site: Alpine CSP build (no eval, works under a strict Content-Security-Policy).
import Alpine from '@alpinejs/csp';

Alpine.data('page', () => ({
    subscribeOpen: false,
    openSubscribe() { this.subscribeOpen = true; document.body.style.overflow = 'hidden'; },
    closeSubscribe() { this.subscribeOpen = false; document.body.style.overflow = ''; },
}));

Alpine.data('nav', () => ({
    open: false,
    get closed() { return !this.open; },
    get openStr() { return this.open ? 'true' : 'false'; },
    toggle() { this.open = !this.open; },
}));

Alpine.data('toasts', () => ({
    items: [],
    init() {
        const initial = this.$el.dataset.initial;
        if (initial) { try { this.push(JSON.parse(initial)); } catch (_) {} }
        window.addEventListener('toast', (e) => this.push(e.detail));
    },
    push(t) {
        const id = Date.now() + Math.random();
        const dot = { success: 'bg-forest-500', error: 'bg-danger', info: 'bg-gold-500' }[t.type || 'info'];
        this.items.push({ id, message: t.message, dot });
        setTimeout(() => { this.items = this.items.filter((i) => i.id !== id); }, 5000);
    },
    dismiss(e) {
        const id = Number(e.currentTarget.dataset.id);
        this.items = this.items.filter((i) => i.id !== id);
    },
}));

Alpine.start();

// Count-up for the proof counters (respects reduced motion).
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
if (!reduce && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
        entries.forEach((en) => {
            if (!en.isIntersecting) return;
            const el = en.target; const end = Number(el.dataset.count); io.unobserve(el);
            if (!end) return;
            const t0 = performance.now(); const dur = 900;
            const step = (t) => { const p = Math.min(1, (t - t0) / dur); el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3))).toLocaleString(); if (p < 1) requestAnimationFrame(step); };
            requestAnimationFrame(step);
        });
    }, { threshold: 0.4 });
    document.querySelectorAll('[data-count]').forEach((el) => io.observe(el));
}

// Ad impressions: count when 50% visible for 1 second (IAB/MRC standard).
const adEls = document.querySelectorAll('[data-ad]');
if (adEls.length && 'IntersectionObserver' in window) {
    const seen = new Set(); const timers = new Map(); const queue = new Set();
    const flush = () => {
        if (!queue.size) return;
        const ids = [...queue].map(Number); queue.clear();
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch('/api/ads/impressions', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token || '', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ ids }), keepalive: true, credentials: 'same-origin' }).catch(() => {});
    };
    const io = new IntersectionObserver((entries) => entries.forEach((en) => {
        const id = en.target.dataset.ad;
        if (en.intersectionRatio >= 0.5 && !seen.has(id)) {
            timers.set(id, setTimeout(() => { seen.add(id); queue.add(id); }, 1000));
        } else { clearTimeout(timers.get(id)); }
    }), { threshold: [0, 0.5] });
    adEls.forEach((el) => io.observe(el));
    setInterval(flush, 5000); window.addEventListener('pagehide', flush);
}

// Hero video: only on fast connections, wide screens, and when motion is allowed.
const video = document.querySelector('[data-hero-video]');
if (video) {
    const conn = navigator.connection || {};
    const slow = conn.saveData || /(^|-)2g|3g/.test(conn.effectiveType || '');
    if (!reduce && !slow && window.innerWidth >= 768) {
        video.src = video.dataset.src; video.classList.remove('hidden'); video.play().catch(() => {});
    }
}

// Assistant: load the chat widget only when asked for (keeps first load small).
const launch = document.getElementById('assistant-launch');
if (launch) {
    launch.addEventListener('click', async () => {
        launch.disabled = true;
        const { mountAssistant } = await import('./assistant/mount.js');
        mountAssistant(document.getElementById('assistant-root'), { signedIn: launch.parentElement.dataset.signedIn === '1', open: true });
        launch.remove();
    }, { once: true });
}

// Service worker for the installable app (offline shell).
if ('serviceWorker' in navigator && location.protocol === 'https:') {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
