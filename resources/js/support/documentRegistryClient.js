import axios from 'axios';

function relativeRoute(name, params = {}) {
    return route(name, params, false);
}

/**
 * @param {import('axios').AxiosError} error
 */
export function formatDocumentRegistryError(error) {
    if (!error.response) {
        const message = error.message ?? '';

        if (message.includes('Network Error') || error.code === 'ERR_NETWORK') {
            return 'Не удалось связаться с сервером. Проверьте интернет. Если ошибка повторяется — возможна проблема HTTPS/прокси на стороне сервера (ALPN).';
        }

        return message || 'Не удалось выполнить запрос';
    }

    const data = error.response.data ?? {};

    if (typeof data.message === 'string' && data.message !== '') {
        return data.message;
    }

    const errors = data.errors;
    if (errors && typeof errors === 'object') {
        const first = Object.values(errors).flat()[0];
        if (typeof first === 'string') {
            return first;
        }
    }

    return `Ошибка сервера (${error.response.status})`;
}

/**
 * @param {FormData} formData
 */
export async function storeDocumentRegistry(formData) {
    const { data } = await axios.post(relativeRoute('documents.store'), formData, {
        headers: { Accept: 'application/json' },
    });

    return data;
}

/**
 * @param {number} documentId
 * @param {FormData} formData
 */
export async function updateDocumentRegistry(documentId, formData) {
    const { data } = await axios.post(relativeRoute('documents.update', documentId), formData, {
        headers: { Accept: 'application/json' },
    });

    return data;
}

/**
 * @param {number} documentId
 */
export async function destroyDocumentRegistry(documentId) {
    const { data } = await axios.delete(relativeRoute('documents.destroy', documentId), {
        headers: { Accept: 'application/json' },
    });

    return data;
}
