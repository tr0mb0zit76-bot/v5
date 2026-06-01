import axios from 'axios';
import { coerceHttpsUrl } from '@/support/inertiaHttpsVisit.js';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

window.axios.interceptors.request.use((config) => {
    if (typeof config.url === 'string') {
        config.url = coerceHttpsUrl(config.url);
    }

    return config;
});

const token = document.querySelector('meta[name="csrf-token"]');

if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    console.error('CSRF token not found');
}