/**
 * Фактические даты погрузки/выгрузки не могут быть в будущем.
 * Сравнение — только по календарному дню (день в день загрузка/выгрузка разрешены).
 */

export function todayIsoDate() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

/** YYYY-MM-DD from date / datetime strings; empty if blank. */
export function toIsoDateDay(value) {
    const normalized = String(value ?? '').trim();

    if (normalized === '') {
        return '';
    }

    const day = normalized.slice(0, 10);

    return /^\d{4}-\d{2}-\d{2}$/.test(day) ? day : normalized;
}

export function clampActualDateToToday(value) {
    const normalized = toIsoDateDay(value);

    if (normalized === '') {
        return '';
    }

    const today = todayIsoDate();

    return normalized > today ? today : normalized;
}

/**
 * True when both dates present and loading calendar day is after unloading.
 * Same day is allowed.
 */
export function isActualLoadingAfterUnloading(loading, unloading) {
    const loadingDay = toIsoDateDay(loading);
    const unloadingDay = toIsoDateDay(unloading);

    if (!loadingDay || !unloadingDay) {
        return false;
    }

    return loadingDay > unloadingDay;
}
