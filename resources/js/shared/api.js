import { toast } from 'vue-sonner';

function xsrf() {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

export class ApiError extends Error {
    constructor(status, data) {
        super(data?.message || 'Request failed');
        this.status = status;
        this.data = data || {};
        this.errors = data?.errors || {};
        this.code = data?.code;
    }
    first(field) { return this.errors?.[field]?.[0]; }
}

let csrfReady = null;
async function ensureCsrf() {
    if (xsrf()) return;
    csrfReady ??= fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
    await csrfReady;
}

/** Same-origin JSON client using Sanctum cookie auth. */
export async function api(path, { method = 'GET', body, headers = {}, raw = false, quiet = false } = {}) {
    if (method !== 'GET') await ensureCsrf();
    const isForm = body instanceof FormData;
    const res = await fetch('/api' + path, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrf(),
            ...(body && !isForm ? { 'Content-Type': 'application/json' } : {}),
            ...headers,
        },
        body: body ? (isForm ? body : JSON.stringify(body)) : undefined,
    });
    if (raw && res.ok) return res;
    let data = null;
    if (res.status !== 204) { try { data = await res.json(); } catch (_) {} }
    if (!res.ok) {
        const err = new ApiError(res.status, data);
        if (!quiet && res.status !== 422 && res.status !== 401) {
            toast.error(res.status === 429 ? 'Too many attempts. Wait a minute and try again.' : err.message);
        }
        throw err;
    }
    return data;
}

export async function download(path, filename) {
    const res = await api(path, { raw: true });
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = Object.assign(document.createElement('a'), { href: url, download: filename });
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
}

export const fmt = {
    date: (iso) => iso ? new Date(iso).toLocaleDateString('en-ZW', { day: 'numeric', month: 'short', year: 'numeric' }) : '',
    datetime: (iso) => iso ? new Date(iso).toLocaleString('en-ZW', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '',
    ago(iso) {
        if (!iso) return '';
        const s = (Date.now() - new Date(iso)) / 1000;
        if (s < 60) return 'just now';
        if (s < 3600) return Math.floor(s / 60) + ' min ago';
        if (s < 86400) return Math.floor(s / 3600) + ' h ago';
        if (s < 604800) return Math.floor(s / 86400) + ' d ago';
        return fmt.date(iso);
    },
    money: (amount, currency = 'USD') => (currency === 'USD' ? 'US$' : currency + ' ') + Number(amount || 0).toFixed(2),
    size: (b) => b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.ceil(b / 1024) + ' KB',
};
