// Southview Park: minimal offline shell. Never caches API responses or documents.
const CACHE = 'southview-v1';
const SHELL = ['/offline.html', '/images/logo-96.webp', '/favicon.ico'];
self.addEventListener('install', (e) => { e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL))); self.skipWaiting(); });
self.addEventListener('activate', (e) => { e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k !== CACHE).map((k) => caches.delete(k))))); self.clients.claim(); });
self.addEventListener('fetch', (e) => {
    const req = e.request; const url = new URL(req.url);
    if (req.method !== 'GET' || url.origin !== location.origin || url.pathname.startsWith('/api') || url.pathname.startsWith('/admin')) return;
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/')) {
        e.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => { if (res.ok) { const copy = res.clone(); caches.open(CACHE).then((c) => c.put(req, copy)); } return res; })));
        return;
    }
    if (req.mode === 'navigate') e.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
});
