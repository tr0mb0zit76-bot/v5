/**
 * @param {string[]} selectedEmails
 * @param {string} [extraRaw]
 * @returns {string[]}
 */
export function mergeMailRecipientEmails(selectedEmails = [], extraRaw = '') {
    const fromPicker = (selectedEmails ?? [])
        .map((email) => String(email || '').trim().toLowerCase())
        .filter(Boolean);

    const fromExtra = String(extraRaw || '')
        .split(/[,;]/)
        .map((part) => part.trim().toLowerCase())
        .filter(Boolean);

    return [...new Set([...fromPicker, ...fromExtra])];
}
