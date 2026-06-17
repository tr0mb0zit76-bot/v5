/**
 * Клиентская оценка лимита размера (согласована с config/documents.php и DocumentPageEstimator на бэкенде).
 * Для PDF считаем страницы по фрагменту файла; для DOCX/XLS без разбора ZIP — осторожная оценка по fallback_pages.
 *
 * @param {File} file
 * @param {Record<string, number|string>} limits from Inertia `document_upload_limits`
 * @returns {Promise<{ pages: number, maxBytes: number }>}
 */
export async function computeDocumentUploadBudget(file, limits) {
    const bpp = Number(limits.bytes_per_page) || 600 * 1024;
    const cap = Math.max(1, Number(limits.max_pages_cap) || 200);
    const fallback = Math.max(1, Number(limits.fallback_pages_unknown) || 12);
    const imgPages = Math.max(1, Number(limits.image_placeholder_pages) || 18);
    const ext = (file.name.split('.').pop() || '').toLowerCase();

    if (ext === 'pdf') {
        const serverBudget = await fetchServerPdfUploadBudget(file, limits);
        if (serverBudget) {
            return {
                pages: serverBudget.pages,
                maxBytes: serverBudget.maxBytes,
                policyMaxBytes: serverBudget.policyMaxBytes ?? serverBudget.maxBytes,
                serverMaxBytes: serverBudget.serverMaxBytes ?? (Number(limits.absolute_max_bytes) || 0),
            };
        }
    }

    let pages = 1;
    if (ext === 'pdf') {
        const headB = Math.max(256_000, Number(limits.pdf_head_scan_bytes) || 4 * 1024 * 1024);
        const tailB = Math.max(256_000, Number(limits.pdf_tail_scan_bytes) || 4 * 1024 * 1024);
        const blob = await readPdfSlicesAsBinaryString(file, headB, tailB);
        pages = estimatePdfPagesFromBinaryString(blob);
    } else if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)) {
        pages = imgPages;
    } else {
        pages = fallback;
    }

    pages = Math.max(1, Math.min(pages, cap));
    const policyMaxBytes = Math.max(1, Number(limits.policy_max_bytes) || cap * bpp);
    const serverMaxBytes = Math.max(
        1,
        Number(limits.server_upload_max_bytes) || Number(limits.absolute_max_bytes) || policyMaxBytes,
    );
    const maxBytes = Math.min(pages * bpp, policyMaxBytes);

    return { pages, maxBytes, policyMaxBytes, serverMaxBytes };
}

/**
 * @param {File} file
 * @param {Record<string, number|string>} limits
 * @returns {Promise<{ pages: number, maxBytes: number, exceeds: boolean, overAbsolute: boolean, canOptimize: boolean }>}
 */
export async function assessDocumentUploadBudget(file, limits) {
    const serverMaxBytes =
        Number(limits.server_upload_max_bytes) || Number(limits.absolute_max_bytes) || 0;
    const { pages, maxBytes } = await computeDocumentUploadBudget(file, limits);
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    const overAbsolute = serverMaxBytes > 0 && file.size > serverMaxBytes;
    const overPageBudget = file.size > maxBytes;
    const exceeds = overAbsolute || overPageBudget;
    const optimizeEnabled = Boolean(limits.optimize_enabled);
    const canOptimize = exceeds && ext === 'pdf' && optimizeEnabled;

    return {
        pages,
        maxBytes,
        exceeds,
        overAbsolute,
        canOptimize,
    };
}

/**
 * @param {File} file
 * @param {Record<string, number|string>} limits
 * @returns {Promise<File|null>}
 */
export async function warnIfDocumentExceedsBudget(file, limits) {
    if (!file || !limits?.absolute_max_bytes) {
        return file;
    }

    const budget = await assessDocumentUploadBudget(file, limits);

    if (!budget.exceeds) {
        return file;
    }

    if (budget.canOptimize) {
        return file;
    }

    const abs = Number(limits.absolute_max_bytes);
    if (budget.overAbsolute) {
        const maxMb = (abs / 1024 / 1024).toFixed(1);
        const curMb = (file.size / 1024 / 1024).toFixed(2);
        window.alert(
            `Файл слишком большой для загрузки на сервер (${curMb} МиБ). Абсолютный предел сейчас около ${maxMb} МиБ. Уменьшите размер или разбейте документ.`,
        );

        return null;
    }

    const kb = Math.round(Number(limits.bytes_per_page) / 1024) || 600;
    const curMb = (file.size / 1024 / 1024).toFixed(2);
    const limMb = (budget.maxBytes / 1024 / 1024).toFixed(2);
    window.alert(
        `Размер файла (${curMb} МиБ), скорее всего, превысит лимит для загрузки (расчётно до ${limMb} МиБ: ~${kb} КиБ × ${budget.pages} стр.). Сожмите PDF или уменьшите число страниц.`,
    );

    return null;
}

function uint8ToBinaryString(u8) {
    const chunk = 0x8000;
    let s = '';
    for (let i = 0; i < u8.length; i += chunk) {
        s += String.fromCharCode.apply(null, u8.subarray(i, Math.min(u8.length, i + chunk)));
    }

    return s;
}

async function readPdfSlicesAsBinaryString(file, headBytes, tailBytes) {
    const size = file.size;
    const headN = Math.min(size, headBytes);
    const head = new Uint8Array(await file.slice(0, headN).arrayBuffer());
    let tail = new Uint8Array(0);
    if (size > headN) {
        const tN = Math.min(size, tailBytes);
        const start = Math.max(0, size - tN);
        tail = new Uint8Array(await file.slice(start, size).arrayBuffer());
    }
    const merged = new Uint8Array(head.length + tail.length);
    merged.set(head, 0);
    merged.set(tail, head.length);

    return uint8ToBinaryString(merged);
}

function estimatePdfPagesFromBinaryString(content) {
    const rePage = /\/Type\s*\/Page\b(?!\w)/g;
    const m = content.match(rePage);
    const fromPageObjects = m ? m.length : 0;

    let fromCount = 0;
    const reCount = /\/Count\s+(\d+)/g;
    let cm;
    while ((cm = reCount.exec(content)) !== null) {
        const n = parseInt(cm[1], 10) || 0;
        fromCount = Math.max(fromCount, n);
    }

    const mediaBoxes = content.match(/\/MediaBox\s*\[/g);
    const fromMediaBox = mediaBoxes ? mediaBoxes.length : 0;

    return Math.max(1, fromPageObjects, fromCount, fromMediaBox);
}

/**
 * @param {File} file
 * @param {Record<string, number|string>} limits
 * @returns {Promise<{ pages: number, maxBytes: number }|null>}
 */
async function fetchServerPdfUploadBudget(file, limits) {
    const url = limits.estimate_budget_url;
    if (!url || typeof url !== 'string') {
        return null;
    }

    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: formData,
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return null;
        }

        const data = await response.json();
        const pages = Math.max(1, Number(data.pages) || 1);
        const maxBytes = Math.max(1, Number(data.max_bytes) || 0);
        const policyMaxBytes = Math.max(1, Number(data.policy_max_bytes) || maxBytes);
        const serverMaxBytes = Math.max(1, Number(data.server_max_bytes) || Number(limits.absolute_max_bytes) || 0);

        if (maxBytes <= 0) {
            return null;
        }

        return { pages, maxBytes, policyMaxBytes, serverMaxBytes };
    } catch {
        return null;
    }
}

/**
 * @param {Record<string, number|string>} limits
 * @param {{ enabled?: boolean }} documentOptimize
 */
export function mergeDocumentUploadLimits(limits, documentOptimize = {}) {
    return {
        ...limits,
        optimize_enabled: Boolean(documentOptimize?.enabled),
    };
}
