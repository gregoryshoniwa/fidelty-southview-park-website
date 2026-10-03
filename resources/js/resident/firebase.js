// Firebase Authentication, loaded only when the resident chooses Google, email or Firebase phone.
import { api } from '@/shared/api.js';

let cfgPromise = null;
export function authConfig() {
    cfgPromise ??= api('/auth/config', { quiet: true }).catch(() => ({ firebase: null, providers: [], phone_provider: 'local' }));
    return cfgPromise;
}

let authPromise = null;
async function fb() {
    authPromise ??= (async () => {
        const cfg = await authConfig();
        if (!cfg.firebase) throw new Error('Firebase sign-in is not configured.');
        const [{ initializeApp }, auth] = await Promise.all([import('firebase/app'), import('firebase/auth')]);
        const app = initializeApp(cfg.firebase);
        const a = auth.getAuth(app);
        a.languageCode = 'en';
        return { a, auth };
    })();
    return authPromise;
}

const EMAIL_KEY = 'southview.emailForSignIn';

export async function googleToken() {
    const { a, auth } = await fb();
    const res = await auth.signInWithPopup(a, new auth.GoogleAuthProvider());
    return { token: await res.user.getIdToken(), name: res.user.displayName };
}

export async function sendEmailLink(email) {
    const { a, auth } = await fb();
    await auth.sendSignInLinkToEmail(a, email, { url: location.origin + '/app/login/email', handleCodeInApp: true });
    try { localStorage.setItem(EMAIL_KEY, email); } catch {}
}

export async function completeEmailLink(emailIfAsked) {
    const { a, auth } = await fb();
    if (!auth.isSignInWithEmailLink(a, location.href)) return null;
    let email = emailIfAsked;
    if (!email) { try { email = localStorage.getItem(EMAIL_KEY); } catch {} }
    if (!email) return { needEmail: true };
    const res = await auth.signInWithEmailLink(a, email, location.href);
    try { localStorage.removeItem(EMAIL_KEY); } catch {}
    return { token: await res.user.getIdToken(), name: res.user.displayName };
}

let verifier = null;
export async function sendPhoneCode(e164, buttonId) {
    const { a, auth } = await fb();
    verifier ??= new auth.RecaptchaVerifier(a, buttonId, { size: 'invisible' });
    return auth.signInWithPhoneNumber(a, e164, verifier); // returns confirmation; call .confirm(code)
}
export async function confirmPhoneCode(confirmation, code) {
    const res = await confirmation.confirm(code);
    return { token: await res.user.getIdToken() };
}

export async function firebaseSignOut() {
    if (!authPromise) return;
    try { const { a, auth } = await fb(); await auth.signOut(a); } catch {}
}

export function toE164(raw) {
    let d = String(raw || '').replace(/\D/g, '');
    if (d.startsWith('00')) d = d.slice(2);
    if (d.startsWith('0')) d = '263' + d.slice(1);
    if (d.length === 9 && d.startsWith('7')) d = '263' + d;
    return /^2637[1378]\d{7}$/.test(d) ? '+' + d : null;
}

export function friendlyError(e) {
    const code = e?.code || '';
    return {
        'auth/popup-closed-by-user': 'The Google window was closed before you finished.',
        'auth/popup-blocked': 'Your browser blocked the Google window. Allow pop-ups for this site and try again.',
        'auth/invalid-verification-code': 'That code is not correct.',
        'auth/code-expired': 'This code has expired. Request a new one.',
        'auth/too-many-requests': 'Too many attempts. Wait a little and try again.',
        'auth/invalid-action-code': 'This sign-in link has expired or was already used. Request a new one.',
        'auth/network-request-failed': 'No connection. Check your data and try again.',
        'auth/quota-exceeded': 'SMS codes are paused for today. Use Google or email, or try tomorrow.',
    }[code] || e?.message || 'Sign-in failed. Please try again.';
}
