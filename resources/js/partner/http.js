import { api, fmt, ApiError } from '@/shared/api.js';

const KEY = 'fspra.partner';

export function getPartnerId() {
    try { return localStorage.getItem(KEY) || ''; } catch (_) { return ''; }
}

export function setPartnerId(id) {
    try {
        if (id) localStorage.setItem(KEY, String(id));
        else localStorage.removeItem(KEY);
    } catch (_) { /* storage unavailable */ }
}

function partnerHeaders(extra = {}) {
    const id = getPartnerId();
    return id ? { 'X-Partner': id, ...extra } : extra;
}

/** api() for the partner portal: every call carries the chosen partner in X-Partner. */
export async function papi(path, opts = {}) {
    try {
        return await api('/partner' + path, { ...opts, headers: partnerHeaders(opts.headers) });
    } catch (e) {
        // Session expired mid-use: send the user back to sign in (the /me guard handles first load).
        if (e instanceof ApiError && e.status === 401 && path !== '/me') {
            import('./router.js').then(({ default: router }) => {
                if (router.currentRoute.value.name !== 'login') router.replace({ name: 'login', query: { next: router.currentRoute.value.fullPath } });
            });
        }
        throw e;
    }
}

/** Downloads a file from the partner API (keeps the X-Partner header, unlike shared download()). */
export async function pdownload(path, filename) {
    const res = await papi(path, { raw: true });
    const disposition = res.headers.get('Content-Disposition') || '';
    const match = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
    const name = filename || (match ? decodeURIComponent(match[1]) : 'download');
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = Object.assign(document.createElement('a'), { href: url, download: name });
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
}

/** Opens a document in a new tab (falls back to download if the popup is blocked). */
export async function popen(path, filename) {
    const win = window.open('', '_blank');
    try {
        const res = await papi(path, { raw: true });
        const url = URL.createObjectURL(await res.blob());
        if (win) { win.location.href = url; } else {
            const a = Object.assign(document.createElement('a'), { href: url, download: filename || 'document' });
            document.body.appendChild(a); a.click(); a.remove();
        }
        setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (e) {
        win?.close();
        throw e;
    }
}

export const STATUS_OPTIONS = [
    { value: 'open', label: 'Open' },
    { value: 'waiting_resident', label: 'Waiting on resident' },
    { value: 'waiting_partner', label: 'With partner' },
    { value: 'waiting_payment', label: 'Waiting on payment' },
    { value: 'approved', label: 'Approved' },
    { value: 'closed', label: 'Closed' },
    { value: 'cancelled', label: 'Cancelled' },
];

export const statusLabel = (s) => STATUS_OPTIONS.find((o) => o.value === s)?.label || (s || '').replaceAll('_', ' ');

export { fmt, ApiError };
