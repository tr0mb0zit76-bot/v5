import axios from 'axios';

/**
 * @param {File} file
 * @returns {Promise<{
 *   pdf_base64: string,
 *   original_bytes: number,
 *   optimized_bytes: number,
 *   method: string,
 *   warnings: string[],
 *   max_bytes: number,
 *   within_budget: boolean,
 * }>}
 */
export async function optimizeDocumentPdf(file) {
    const body = new FormData();
    body.append('file', file);

    const { data } = await axios.post(route('documents.optimize-pdf'), body, {
        headers: { Accept: 'application/json' },
    });

    return data;
}

/**
 * @param {string} base64
 * @param {string} originalName
 * @returns {File}
 */
export function fileFromOptimizedPdfBase64(base64, originalName) {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
    }

    const baseName = (originalName || 'document.pdf').replace(/\.pdf$/i, '');
    const name = `${baseName}-optimized.pdf`;

    return new File([bytes], name, { type: 'application/pdf' });
}

/**
 * @param {number} bytes
 */
export function formatFileSizeMb(bytes) {
    return formatFileSizeHuman(bytes);
}

/**
 * @param {number} bytes
 */
export function formatFileSizeHuman(bytes) {
    const n = Number(bytes);
    if (!Number.isFinite(n) || n < 0) {
        return '—';
    }
    if (n >= 1024 * 1024) {
        return `${(n / 1024 / 1024).toFixed(n >= 10 * 1024 * 1024 ? 1 : 2)} МиБ`;
    }
    if (n >= 1024) {
        return `${Math.round(n / 1024)} КиБ`;
    }

    return `${Math.round(n)} байт`;
}
