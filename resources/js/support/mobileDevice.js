const STORAGE_KEY = 'crm_mobile_device_key';

function createUuidV4() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = Math.floor(Math.random() * 16);
        const value = char === 'x' ? random : ((random & 0x3) | 0x8);

        return value.toString(16);
    });
}

export function getOrCreateDeviceKey() {
    try {
        const existing = localStorage.getItem(STORAGE_KEY);
        if (existing && existing.length === 36) {
            return existing;
        }

        const next = createUuidV4();
        localStorage.setItem(STORAGE_KEY, next);

        return next;
    } catch {
        return createUuidV4();
    }
}

export function getDeviceName() {
    if (typeof navigator === 'undefined') {
        return 'Mobile device';
    }

    const platform = navigator.userAgentData?.platform ?? navigator.platform ?? 'Mobile';
    const vendor = navigator.vendor ? ` · ${navigator.vendor}` : '';

    return `${platform}${vendor}`.trim();
}
