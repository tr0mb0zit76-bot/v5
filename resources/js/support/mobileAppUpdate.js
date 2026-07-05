import axios from 'axios';

function nativeAppInfo() {
    const bridge = window.TrakloApp;

    if (!bridge || typeof bridge.getVersionCode !== 'function') {
        return null;
    }

    return {
        versionCode: Number(bridge.getVersionCode()) || 0,
        versionName: typeof bridge.getVersionName === 'function' ? bridge.getVersionName() : '',
    };
}

export async function checkMobileAppUpdate() {
    const app = nativeAppInfo();

    if (!app || app.versionCode <= 0) {
        return null;
    }

    const { data } = await axios.get(route('mobile.app-update'), {
        headers: { Accept: 'application/json' },
        params: {
            version_code: app.versionCode,
            version_name: app.versionName,
        },
    });

    if (data.update_available !== true && data.required !== true) {
        return null;
    }

    return {
        ...data,
        installed_version_code: app.versionCode,
        installed_version_name: app.versionName,
    };
}
