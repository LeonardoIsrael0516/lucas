/**
 * Utilitários compartilhados para Web Push (painel e área de membros).
 */

export function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

/**
 * Compara subscription.applicationServerKey com a chave VAPID pública atual.
 */
export function subscriptionMatchesVapid(subscription, vapidPublic) {
    if (!subscription || !vapidPublic) {
        return false;
    }
    const key = subscription.options?.applicationServerKey;
    if (!key) {
        return false;
    }
    const existing = key instanceof Uint8Array ? key : new Uint8Array(key);
    const expected = urlBase64ToUint8Array(vapidPublic);
    if (existing.length !== expected.length) {
        return false;
    }
    for (let i = 0; i < existing.length; i++) {
        if (existing[i] !== expected[i]) {
            return false;
        }
    }
    return true;
}

export function vapidStorageKey(scope) {
    const safe = String(scope || 'default').replace(/[^a-zA-Z0-9_-]/g, '_');
    return `getfy_vapid_${safe}`;
}

/**
 * Garante subscription compatível com vapidPublic; re-inscreve se necessário.
 *
 * @param {Object} opts
 * @param {ServiceWorkerRegistration} opts.reg
 * @param {string} opts.vapidPublic
 * @param {string} [opts.scope] storage key suffix
 * @returns {Promise<PushSubscription|null>}
 */
export async function ensurePushSubscription({ reg, vapidPublic, scope = 'default' }) {
    if (!reg?.pushManager || !vapidPublic) {
        return null;
    }
    const storageKey = vapidStorageKey(scope);
    let existing = await reg.pushManager.getSubscription();
    const storedVapid = typeof localStorage !== 'undefined' ? localStorage.getItem(storageKey) : null;

    const needsResubscribe =
        !existing ||
        !subscriptionMatchesVapid(existing, vapidPublic) ||
        (storedVapid && storedVapid !== vapidPublic);

    if (needsResubscribe && existing) {
        try {
            await existing.unsubscribe();
        } catch (_) {
            /* ignore */
        }
        existing = null;
    }

    if (!existing) {
        existing = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidPublic),
        });
    }

    try {
        localStorage.setItem(storageKey, vapidPublic);
    } catch (_) {
        /* ignore */
    }

    return existing;
}

export function serializePushSubscription(sub) {
    const p256dh = sub?.getKey?.('p256dh');
    const auth = sub?.getKey?.('auth');
    return {
        endpoint: sub?.endpoint,
        keys: {
            p256dh: p256dh ? btoa(String.fromCharCode.apply(null, new Uint8Array(p256dh))) : '',
            auth: auth ? btoa(String.fromCharCode.apply(null, new Uint8Array(auth))) : '',
        },
    };
}
