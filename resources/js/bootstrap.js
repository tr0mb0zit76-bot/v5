import axios from 'axios';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

function readCsrfFromMeta() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function readXsrfFromCookie() {
    const row = document.cookie.split('; ').find((p) => p.startsWith('XSRF-TOKEN='));
    if (!row) {
        return '';
    }
    const raw = row.slice('XSRF-TOKEN='.length);
    try {
        return decodeURIComponent(raw);
    } catch {
        return raw;
    }
}

function applyAxiosCsrfHeaders(config) {
    const metaToken = readCsrfFromMeta();
    if (metaToken !== '') {
        config.headers['X-CSRF-TOKEN'] = metaToken;
    }
    const xsrf = readXsrfFromCookie();
    if (xsrf !== '') {
        config.headers['X-XSRF-TOKEN'] = xsrf;
    }
}

const initialToken = readCsrfFromMeta();
if (initialToken !== '') {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = initialToken;
} else {
    console.error('CSRF token not found');
}

window.axios.interceptors.request.use((config) => {
    applyAxiosCsrfHeaders(config);

    return config;
});