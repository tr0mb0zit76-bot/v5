import axios from 'axios';
import { getOrCreateDeviceKey } from '@/support/mobileDevice';

let registrationStarted = false;

function isNativeCapacitor() {
    return typeof window !== 'undefined'
        && window.Capacitor?.isNativePlatform?.() === true;
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
            const conversationId = action.notification?.data?.conversation_id;
            if (conversationId) {
                window.dispatchEvent(new CustomEvent('crm-mobile-open-conversation', {
                    detail: { conversationId: Number(conversationId) },
                }));
            }
        });
    } catch (error) {
        console.warn('Push notifications unavailable', error);
    }
}
