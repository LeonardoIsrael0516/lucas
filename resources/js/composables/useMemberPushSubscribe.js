import { ref, computed, unref } from 'vue';
import { ensurePushSubscription, serializePushSubscription } from '@/utils/pushSubscription';

/**
 * Push da área de membros (PWA por curso / domínio próprio).
 *
 * @param {Object} opts
 * @param {import('vue').MaybeRefOrGetter<boolean>} opts.pushEnabled
 * @param {import('vue').MaybeRefOrGetter<string|null>} opts.vapidPublic
 * @param {import('vue').MaybeRefOrGetter<string>} opts.baseUrl
 * @param {import('vue').MaybeRefOrGetter<string>} opts.slug
 */
export function useMemberPushSubscribe({ pushEnabled, vapidPublic, baseUrl, slug }) {
    const pushSubscribing = ref(false);
    const pushRegistered = ref(false);
    const lastPushError = ref(null);

    const isStandalonePwa = computed(() => {
        if (typeof window === 'undefined') return false;
        if (window.matchMedia('(display-mode: standalone)').matches) return true;
        return !!window.navigator.standalone;
    });

    const canRegisterPush = computed(() => {
        const enabled = unref(pushEnabled);
        const vapid = unref(vapidPublic);
        return Boolean(
            enabled &&
                vapid &&
                typeof window !== 'undefined' &&
                typeof navigator !== 'undefined' &&
                'serviceWorker' in navigator &&
                'PushManager' in window,
        );
    });

    const notificationPermission = computed(() =>
        typeof Notification !== 'undefined' ? Notification.permission : 'default',
    );

    const scopeUrl = computed(() => {
        const base = String(unref(baseUrl) || '').trim();
        if (!base) return null;
        return base.endsWith('/') ? base : `${base}/`;
    });

    const dismissedKey = computed(
        () => `member_push_prompt_dismissed_${unref(slug) || 'default'}${isStandalonePwa.value ? '_standalone' : ''}`,
    );

    const notificationBannerDismissedKey = computed(
        () => `member_notification_prompt_dismissed_${unref(slug) || 'default'}`,
    );

    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    }

    async function syncSubscriptionToServer(sub) {
        const scope = scopeUrl.value;
        if (!scope) return false;
        const body = serializePushSubscription(sub);
        if (!body.endpoint || !body.keys?.p256dh || !body.keys?.auth) {
            return false;
        }
        const res = await fetch(`${scope}push-subscribe`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data?.success) {
            throw new Error(data?.message || 'Não foi possível sincronizar a inscrição de notificações.');
        }
        return true;
    }

    async function registerAndSubscribe({ requestPermission = false } = {}) {
        lastPushError.value = null;
        if (!canRegisterPush.value) {
            lastPushError.value = 'push_not_configured';
            return false;
        }
        if (typeof Notification !== 'undefined') {
            if (Notification.permission === 'denied') {
                lastPushError.value = 'notification_permission_denied';
                return false;
            }
            if (requestPermission && Notification.permission === 'default') {
                const result = await Notification.requestPermission();
                if (result !== 'granted') {
                    lastPushError.value = 'notification_permission_denied';
                    return false;
                }
            } else if (Notification.permission === 'default') {
                lastPushError.value = 'notification_permission_default';
                return false;
            }
        }

        const scope = scopeUrl.value;
        if (!scope || pushSubscribing.value) {
            return false;
        }

        pushSubscribing.value = true;
        try {
            const reg = await navigator.serviceWorker.register(`${scope}sw.js`, { scope });
            const sub = await ensurePushSubscription({
                reg,
                vapidPublic: unref(vapidPublic),
                scope,
            });
            if (!sub) {
                lastPushError.value = 'subscription_failed';
                return false;
            }
            await syncSubscriptionToServer(sub);
            pushRegistered.value = true;
            return true;
        } catch (e) {
            if (e?.name === 'NotAllowedError') {
                lastPushError.value = 'notification_permission_denied';
                markAutoPromptDismissed();
                return false;
            }
            lastPushError.value = 'subscription_failed';
            console.warn('Member push subscribe failed:', e);
            return false;
        } finally {
            pushSubscribing.value = false;
        }
    }

    async function checkExistingSubscription() {
        if (!canRegisterPush.value || typeof navigator === 'undefined' || !navigator.serviceWorker) {
            return false;
        }
        if (typeof Notification !== 'undefined' && Notification.permission !== 'granted') {
            pushRegistered.value = false;
            return false;
        }
        const scope = scopeUrl.value;
        if (!scope) return false;
        try {
            await navigator.serviceWorker.register(`${scope}sw.js`, { scope });
            const reg = await navigator.serviceWorker.getRegistration(scope);
            if (!reg?.pushManager) return false;
            const sub = await ensurePushSubscription({
                reg,
                vapidPublic: unref(vapidPublic),
                scope,
            });
            if (!sub) return false;
            await syncSubscriptionToServer(sub);
            pushRegistered.value = true;
            return true;
        } catch (e) {
            console.warn('MemberArea push sync failed:', e);
            pushRegistered.value = false;
            return false;
        }
    }

    function markAutoPromptDismissed() {
        try {
            localStorage.setItem(dismissedKey.value, Date.now().toString());
        } catch (_) {
            /* ignore */
        }
    }

    function wasNotificationBannerDismissedRecently() {
        try {
            const raw = localStorage.getItem(notificationBannerDismissedKey.value);
            if (!raw) return false;
            const age = Date.now() - parseInt(raw, 10);
            return age < 7 * 24 * 60 * 60 * 1000;
        } catch {
            return false;
        }
    }

    function dismissNotificationBanner() {
        try {
            localStorage.setItem(notificationBannerDismissedKey.value, Date.now().toString());
        } catch (_) {
            /* ignore */
        }
    }

    function shouldShowNotificationBanner() {
        if (!canRegisterPush.value || pushRegistered.value) return false;
        if (typeof Notification === 'undefined' || Notification.permission !== 'default') {
            return false;
        }
        return !wasNotificationBannerDismissedRecently();
    }

    function shouldAutoPromptPush() {
        if (!canRegisterPush.value || pushRegistered.value || pushSubscribing.value) return false;
        if (typeof Notification === 'undefined' || Notification.permission === 'denied') return false;
        if (shouldShowNotificationBanner()) return false;
        try {
            const dismissed = localStorage.getItem(dismissedKey.value);
            if (dismissed) {
                const age = Date.now() - parseInt(dismissed, 10);
                if (age < 24 * 60 * 60 * 1000) return false;
            }
        } catch (_) {
            /* ignore */
        }
        return Notification.permission === 'granted';
    }

    return {
        pushSubscribing,
        pushRegistered,
        lastPushError,
        canRegisterPush,
        isStandalonePwa,
        notificationPermission,
        registerAndSubscribe,
        checkExistingSubscription,
        shouldShowNotificationBanner,
        shouldAutoPromptPush,
        dismissNotificationBanner,
        markAutoPromptDismissed,
    };
}
