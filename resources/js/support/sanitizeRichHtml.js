import DOMPurify from 'dompurify';

/**
 * Санитизация HTML из i18n / rich-текстов публичной витрины.
 */
export function sanitizeRichHtml(html) {
    return DOMPurify.sanitize(String(html ?? ''), { USE_PROFILES: { html: true } });
}
