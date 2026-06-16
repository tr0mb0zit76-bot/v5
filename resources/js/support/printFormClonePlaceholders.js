const CLONE_ROW_PREFIXES = [
    'cargo_row_',
    'route_row_',
    'route_point_row_',
    'cp_basic_terms_row_',
    'dp_basic_terms_row_',
];

/** Плейсхолдеры cloneRow — заполняются PhpWord, не через сопоставление с полем заказа. */
export function isPrintFormCloneRowPlaceholder(name) {
    const normalized = String(name ?? '').trim().toLowerCase();

    if (normalized === '') {
        return false;
    }

    const base = normalized.replace(/#\d+$/u, '');

    return CLONE_ROW_PREFIXES.some((prefix) => base.startsWith(prefix));
}

/** Устаревшие фиксированные слоты cargo.line_N_* — заменены таблицей ${cargo_row_*}. */
export function isLegacyFixedCargoLinePlaceholder(name) {
    const normalized = String(name ?? '').trim().toLowerCase();

    return /^cargo\.line_\d+_(text|summary|name)$/u.test(normalized)
        || /^gruz_\d+$/u.test(normalized)
        || /^cargo_name\d+$/u.test(normalized);
}

export function isVerificationQrImagePlaceholder(name) {
    const normalized = String(name ?? '').trim().toLowerCase();
    const base = normalized.replace(/#\d+$/u, '');

    return base === 'document_verification_qr';
}

export function shouldHideFromVariableMapping(name) {
    return isPrintFormCloneRowPlaceholder(name)
        || isLegacyFixedCargoLinePlaceholder(name)
        || isVerificationQrImagePlaceholder(name);
}
