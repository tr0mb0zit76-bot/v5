/**
 * Ключ плеча для API (leg_N), как на бэкенде.
 */
export function toStageKey(stage) {
    const value = String(stage ?? '').trim();
    if (value === '') {
        return 'leg_1';
    }

    const legMatch = value.match(/^leg_(\d+)$/i);
    if (legMatch) {
        return `leg_${legMatch[1]}`;
    }

    const labelMatch = value.match(/^Плечо\s+(\d+)$/i);
    if (labelMatch) {
        return `leg_${labelMatch[1]}`;
    }

    if (/^\d+$/.test(value)) {
        return `leg_${value}`;
    }

    return value;
}
