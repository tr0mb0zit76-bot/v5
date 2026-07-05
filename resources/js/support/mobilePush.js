import axios from 'axios';
import { getOrCreateDeviceKey } from '@/support/mobileDevice';
import { dispatchMobilePushNavigation } from '@/support/mobilePushNavigation.js';

let registrationStarted = false;

function isNativeCapacitor() {
    return typeof window !== 'undefined'
        && window.Capacitor?.isNativePlatform?.() === true;
}

function extractPushData(notification) {
    return notification?.data ?? notification?.notification?.data ?? {};
}

function consumeNativePendingPushAction() {
    if (typeof window === 'undefined' || typeof window.TrakloApp?.consumePendingPushAction !== 'function') {
        return;
    }

    try {
        const raw = window.TrakloApp.consumePendingPushAction();
        if (!raw) {
            return;
        }

        const payload = JSON.parse(raw);
        dispatchMobilePushNavigation(payload?.data ?? {});
    } catch (error) {
        console.warn('Failed to consume pending push action', error);
    }
}

export async function registerMobilePushIfAvailable({ enabled = false } = {}) {
    if (!enabled || !isNativeCapacitor() || registrationStarted) {
        return;
    }

    registrationStarted = true;

    try {
        const { PushNotifications } = await import('@capacitor/push-notifications');

        const permission = await PushNotifications.requestPermissions();
        if (permission.receive !== 'granted') {
            return;
        }

        await PushNotifications.register();

        PushNotifications.addListener('registration', async (event) => {
            const token = event.value;
            if (!token) {
                return;
            }

            await axios.post(route('mobile.device.fcm-token'), {
                device_key: getOrCreateDeviceKey(),
                fcm_token: token,
            });
        });

        PushNotifications.addListener('registrationError', (error) => {
            console.warn('FCM registration failed', error);
        });

        PushNotifications.addListener('pushNotificationReceived', () => {
            window.dispatchEvent(new CustomEvent('crm-mobile-push-received'));
        });

        PushNotifications.addListener('pushNotificationActionPerformed', (action) => {
            dispatchMobilePushNavigation(extractPushData(action.notification));
        });

        consumeNativePendingPushAction();
        window.addEventListener('focus', consumeNativePendingPushAction);
    } catch (error) {
        console.warn('Push notifications unavailable', error);
    }
}
