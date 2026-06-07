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
    return `${(Number(bytes) / 1024 / 1024).toFixed(2)} МиБ`;
}
