/**
 * Убирает preview=1 из URL черновика, чтобы открыть тот же endpoint в режиме скачивания.
 *
 * @param {string} embedUrl
 * @returns {string}
 */
export function docxDownloadUrlFromEmbed(embedUrl) {
    const raw = embedUrl?.trim() ?? '';
    if (raw === '') {
        return '';
    }

    try {
        const u = new URL(raw, typeof window !== 'undefined' ? window.location.href : 'http://localhost');
        u.searchParams.delete('preview');

        return u.toString();
    } catch {
        return raw;
    }
}
