export const DOWNTIME_PERIOD_HOUR = 'hour';
export const DOWNTIME_PERIOD_DAY = 'day';

export const downtimePeriodOptions = [
    { value: DOWNTIME_PERIOD_HOUR, label: 'в час' },
    { value: DOWNTIME_PERIOD_DAY, label: 'в сутки' },
];

export function blankPartyNormsPenalties() {
    return {
        miss_amount: null,
        miss_currency: 'RUB',
        downtime_amount: null,
        downtime_currency: 'RUB',
        downtime_period: DOWNTIME_PERIOD_DAY,
        fine_amount: null,
        fine_currency: 'RUB',
        penalty_terms: '',
        norm_loading_hours: null,
        norm_customs_hours: null,
        norm_unloading_hours: null,
    };
}

export function normalizePartyNormsPenalties(raw) {
    const base = blankPartyNormsPenalties();
    if (!raw || typeof raw !== 'object') {
        return base;
    }

    const toNum = (v) => {
        if (v === null || v === undefined || v === '') {
            return null;
        }
        const n = Number(v);

        return Number.isFinite(n) ? n : null;
    };

    const periodRaw = String(raw.downtime_period ?? base.downtime_period).toLowerCase();
    const downtimePeriod = periodRaw === DOWNTIME_PERIOD_HOUR || periodRaw === 'час' || periodRaw === 'ч'
        ? DOWNTIME_PERIOD_HOUR
        : DOWNTIME_PERIOD_DAY;

    return {
        ...base,
        miss_amount: toNum(raw.miss_amount),
        miss_currency: String(raw.miss_currency ?? base.miss_currency).slice(0, 3) || base.miss_currency,
        downtime_amount: toNum(raw.downtime_amount),
        downtime_currency: String(raw.downtime_currency ?? base.downtime_currency).slice(0, 3) || base.downtime_currency,
        downtime_period: downtimePeriod,
        fine_amount: toNum(raw.fine_amount),
        fine_currency: String(raw.fine_currency ?? base.fine_currency).slice(0, 3) || base.fine_currency,
        penalty_terms: String(raw.penalty_terms ?? '').slice(0, 2000),
        norm_loading_hours: toNum(raw.norm_loading_hours),
        norm_customs_hours: toNum(raw.norm_customs_hours),
        norm_unloading_hours: toNum(raw.norm_unloading_hours),
    };
}

export function hasNormsPenaltiesContent(raw) {
    const row = normalizePartyNormsPenalties(raw);

    return (
        row.miss_amount != null
        || row.downtime_amount != null
        || row.fine_amount != null
        || String(row.penalty_terms || '').trim() !== ''
        || row.norm_loading_hours != null
        || row.norm_customs_hours != null
        || row.norm_unloading_hours != null
    );
}
