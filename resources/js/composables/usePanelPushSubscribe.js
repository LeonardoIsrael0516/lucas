import { ref, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { ensurePushSubscription, serializePushSubscription } from '@/utils/pushSubscription';

/**
 * Registra o Service Worker do painel e subscribe para push.
 */
export function usePanelPushSubscribe() {
    const page = usePage();
    const pushEnabled = computed(() => !!page.props.push_enabled);
    const vapidPublic = computed(() => page.props.vapid_public ?? null);
    const pushSubscribing = ref(false);
    const pushRegistered = ref(false);
    const lastPushError = ref(null);

    async function syncSubscriptionToServer(sub) {
        const payload = serializePushSubscription(sub);
        if (!payload.endpoint || !payload.keys?.p256dh || !payload.keys?.auth) return false;
        const { data } = await axios.post('/painel/push-subscribe', payload);
        return !!data?.success;
    }

    async function registerAndSubscribe() {
        lastPushError.value = null;
        pushRegistered.value = false;
        if (typeof navigator === 'undefined' || !navigator.serviceWorker) {
            lastPushError.value = 'service_worker_unavailable';
            return false;
        }

        try {
            await navigator.serviceWorker.register('/painel-sw.js', { scope: '/painel/' });
        } catch (e) {
            console.warn('Panel SW registration failed:', e);
            lastPushError.value = 'service_worker_registration_failed';
            return false;
        }

        if (!pushEnabled.value || !vapidPublic.value) {
            lastPushError.value = 'push_not_configured';
            return false;
        }
        if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
            lastPushError.value = 'notification_permission_default';
            return false;
        }
        if (typeof Notification !== 'undefined' && Notification.permission === 'denied') {
            lastPushError.value = 'notification_permission_denied';
            return false;
        }
        if (pushSubscribing.value) return false;
        if (pushRegistered.value) return true;

        pushSubscribing.value = true;
        try {
            const reg = await navigator.serviceWorker.getRegistration('/painel/');
            if (!reg) {
                lastPushError.value = 'service_worker_not_found';
                return false;
            }
            const sub = await ensurePushSubscription({
                reg,
                vapidPublic: vapidPublic.value,
                scope: '/painel/',
            });
            if (!sub) {
                lastPushError.value = 'subscription_failed';
                return false;
            }
            const synced = await syncSubscriptionToServer(sub);
            pushRegistered.value = synced;
            if (!synced) lastPushError.value = 'subscription_sync_failed';
            return synced;
        } catch (e) {
            if (e?.name === 'NotAllowedError') {
                lastPushError.value = 'notification_permission_denied';
                return false;
            }
            lastPushError.value = 'subscription_failed';
            console.warn('Panel push subscribe failed:', e);
            return false;
        } finally {
            pushSubscribing.value = false;
        }
    }

    async function checkExistingSubscription() {
        lastPushError.value = null;
        pushRegistered.value = false;
        if (typeof navigator === 'undefined' || !navigator.serviceWorker?.getRegistration) return false;
        if (typeof Notification !== 'undefined' && Notification.permission !== 'granted') return false;
        if (!pushEnabled.value || !vapidPublic.value) return false;
        try {
            await navigator.serviceWorker.register('/painel-sw.js', { scope: '/painel/' });
            const reg = await navigator.serviceWorker.getRegistration('/painel/');
            if (!reg?.pushManager) return false;
            const sub = await ensurePushSubscription({
                reg,
                vapidPublic: vapidPublic.value,
                scope: '/painel/',
            });
            if (!sub) return false;
            const synced = await syncSubscriptionToServer(sub);
            pushRegistered.value = synced;
            if (!synced) {
                lastPushError.value = 'subscription_sync_failed';
            }
            return synced;
        } catch (_) {
            return false;
        }
    }

    const notificationPermission = computed(() =>
        typeof Notification !== 'undefined' ? Notification.permission : 'default',
    );

    const isStandalone = computed(() => {
        if (typeof window === 'undefined') return false;
        return (
            window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true ||
            document.referrer.includes('android-app://')
        );
    });

    let permissionCheckInterval = null;

    onMounted(() => {
        if (isStandalone.value && notificationPermission.value === 'default') {
            permissionCheckInterval = setInterval(() => {
                if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                    if (permissionCheckInterval) {
                        clearInterval(permissionCheckInterval);
                        permissionCheckInterval = null;
                    }
                    registerAndSubscribe();
                }
            }, 1500);
            setTimeout(() => {
                if (permissionCheckInterval) {
                    clearInterval(permissionCheckInterval);
                    permissionCheckInterval = null;
                }
            }, 60000);
            return;
        }
        registerAndSubscribe();
    });

    onUnmounted(() => {
        if (permissionCheckInterval) {
            clearInterval(permissionCheckInterval);
        }
    });

    return {
        pushSubscribing,
        pushRegistered,
        lastPushError,
        notificationPermission,
        isStandalone,
        registerAndSubscribe,
        checkExistingSubscription,
    };
}
