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

/**
 * Полная ISO-дата с «нормальным» годом.
 * Нужна, потому что при наборе года в <input type="date"> Chrome временно
 * отдаёт 0002 / 0020 / 0202 — на них нельзя валидировать порядок дат.
 */
export function isPlausibleActualIsoDate(value) {
    const day = toIsoDateDay(value);

    if (!/^\d{4}-\d{2}-\d{2}$/.test(day)) {
        return false;
    }

    const year = Number(day.slice(0, 4));
    const currentYear = new Date().getFullYear();

    return year >= 2000 && year <= currentYear;
}

export function clampActualDateToToday(value) {
    const normalized = toIsoDateDay(value);

    if (normalized === '') {
        return '';
    }

    // Не трогаем промежуточные значения при наборе года с клавиатуры.
    if (!isPlausibleActualIsoDate(normalized)) {
        return normalized;
    }

    const today = todayIsoDate();

    return normalized > today ? today : normalized;
}

/**
 * True when both dates present and loading calendar day is after unloading.
 * Same day is allowed. Incomplete / typing-in-progress dates → false.
 */
export function isActualLoadingAfterUnloading(loading, unloading) {
    if (!isPlausibleActualIsoDate(loading) || !isPlausibleActualIsoDate(unloading)) {
        return false;
    }

    const loadingDay = toIsoDateDay(loading);
    const unloadingDay = toIsoDateDay(unloading);

    return loadingDay > unloadingDay;
}
