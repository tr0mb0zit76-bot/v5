/**
 * Фактические даты погрузки/выгрузки не могут быть в будущем.
 */
export function todayIsoDate() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function clampActualDateToToday(value) {
    const normalized = String(value ?? '').trim();

    if (normalized === '') {
        return '';
    }

    const today = todayIsoDate();

    return normalized > today ? today : normalized;
}
