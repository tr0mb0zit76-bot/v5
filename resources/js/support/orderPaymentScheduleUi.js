/** Подписи базиса для человекочитабельной сводки (нижний регистр). */
export const BASIS_SUMMARY_PHRASE = {
    fttn: 'по сканам',
    fttn_receipt: 'по сканам + квиток',
    ottn: 'по оригиналам',
    loading: 'при погрузке',
    unloading: 'при выгрузке',
};

export const MAX_INSTALLMENTS = 10;

/** Макс. длина текста «Сводка для договора и печати» (клиент и исполнители). */
export const PAYMENT_TERMS_SUMMARY_MAX_LENGTH = 400;

export const PAYMENT_BASIS_OPTIONS = [
    { value: 'fttn', label: 'По сканам', shortLabel: 'Сканы' },
    { value: 'fttn_receipt', label: 'По сканам + квиток', shortLabel: 'Сканы+кв.' },
    { value: 'ottn', label: 'По оригиналам', shortLabel: 'Ориг.' },
    { value: 'loading', label: 'При погрузке', shortLabel: 'Погр.' },
    { value: 'unloading', label: 'При выгрузке', shortLabel: 'Выгр.' },
];

export const PAYMENT_ANCHOR_OPTIONS = [
    { value: 'first_loading', label: 'Первая погрузка (план в маршруте)', shortLabel: '1-я погр.' },
    { value: 'last_unloading', label: 'Последняя выгрузка (план)', shortLabel: 'Посл. выгр.' },
    { value: 'border_crossing', label: 'Прохождение границы (план в маршруте)', shortLabel: 'Граница' },
    { value: 'order_date', label: 'Дата заказа', shortLabel: 'Заказ' },
    { value: 'loading_date', label: 'Дата погрузки (в заказе)', shortLabel: 'Погр. (зак.)' },
    { value: 'unloading_date', label: 'Дата выгрузки (в заказе)', shortLabel: 'Выгр. (зак.)' },
];

export const PAYMENT_OFFSET_UNIT_OPTIONS = [
    { value: 'calendar_days', label: 'Календарные дни', shortLabel: 'кал.' },
    { value: 'bank_days', label: 'Банковские дни (пн–пт)', shortLabel: 'банк.' },
];

const INSTALLMENT_ANCHOR_END = {
    first_loading: 'первой погрузки',
    last_unloading: 'последней выгрузки',
    border_crossing: 'прохождения границы',
    order_date: 'даты заказа',
    loading_date: 'даты погрузки (заказ)',
    unloading_date: 'даты выгрузки (заказ)',
};

function anchorForBasis(basis) {
    const k = String(basis || 'fttn').toLowerCase();
    if (k === 'loading') {
        return 'first_loading';
    }
    if (k === 'unloading') {
        return 'last_unloading';
    }
    return 'last_unloading';
}

export function blankInstallmentRow(overrides = {}) {
    return normalizeInstallmentRow({
        percent: 100,
        amount: null,
        offset_days: 0,
        offset_unit: 'calendar_days',
        anchor: 'last_unloading',
        basis: 'ottn',
        ...overrides,
    });
}

/** @deprecated Используйте blankSingleInstallmentSchedule */
export function blankPaymentSchedule() {
    return blankSingleInstallmentSchedule();
}

export function blankSingleInstallmentSchedule() {
    return {
        installments: [blankInstallmentRow({ percent: 100 })],
    };
}

export function blankTwoInstallmentSchedule() {
    return {
        installments: [
            blankInstallmentRow({ percent: 50, offset_days: -14, offset_unit: 'bank_days', anchor: 'first_loading', basis: 'fttn' }),
            blankInstallmentRow({ percent: 50, offset_days: 0, offset_unit: 'calendar_days', anchor: 'last_unloading', basis: 'ottn' }),
        ],
    };
}

export function normalizeInstallmentRow(row = {}) {
    return {
        percent: Number(row.percent ?? 0) || 0,
        amount: row.amount !== null && row.amount !== undefined && row.amount !== '' ? Number(row.amount) : null,
        offset_days: Number(row.offset_days ?? 0),
        offset_unit: row.offset_unit === 'bank_days' ? 'bank_days' : 'calendar_days',
        anchor: row.anchor || 'last_unloading',
        basis: row.basis || 'ottn',
    };
}

export function usesInstallments(schedule) {
    return Array.isArray(schedule?.installments) && schedule.installments.length > 0;
}

const LEGACY_SCHEDULE_KEYS = [
    'has_prepayment',
    'prepayment_ratio',
    'prepayment_days',
    'prepayment_mode',
    'postpayment_days',
    'postpayment_mode',
];

function stripLegacyKeysInPlace(schedule) {
    LEGACY_SCHEDULE_KEYS.forEach((key) => {
        delete schedule[key];
    });
}

function assignRoundedNumber(target, key, value) {
    const next = Math.round(value * 100) / 100;

    if (Number(target[key]) !== next) {
        target[key] = next;
    }
}

/** Нормализует график на месте, без подмены ссылки на installments. */
export function applyInstallmentScheduleInPlace(schedule = {}) {
    if (!schedule || typeof schedule !== 'object') {
        return;
    }

    if (!usesInstallments(schedule)) {
        const converted = legacyToInstallments(schedule);
        stripLegacyKeysInPlace(schedule);
        schedule.installments = converted.installments.map((row) => normalizeInstallmentRow(row));

        return;
    }

    stripLegacyKeysInPlace(schedule);
    schedule.installments = schedule.installments.map((row) => normalizeInstallmentRow(row));
}

export function legacyToInstallments(schedule = {}) {
    const raw = schedule?.has_prepayment;
    const hasPrepayment = raw === true || raw === 1 || raw === '1';
    const installments = [];

    if (hasPrepayment) {
        const preRatio = Math.min(100, Math.max(0, Number(schedule.prepayment_ratio || 0)));
        if (preRatio > 0) {
            installments.push(
                blankInstallmentRow({
                    percent: preRatio,
                    offset_days: Number(schedule.prepayment_days || 0),
                    basis: schedule.prepayment_mode || 'fttn',
                    anchor: anchorForBasis(schedule.prepayment_mode || 'fttn'),
                }),
            );
        }
    }

    const postPct = hasPrepayment ? Math.max(0, 100 - Number(schedule.prepayment_ratio || 0)) : 100;
    if (postPct > 0) {
        installments.push(
            blankInstallmentRow({
                percent: postPct,
                offset_days: Number(schedule.postpayment_days || 0),
                basis: schedule.postpayment_mode || 'ottn',
                anchor: anchorForBasis(schedule.postpayment_mode || 'ottn'),
            }),
        );
    }

    if (installments.length === 0) {
        installments.push(blankInstallmentRow());
    }

    return { installments };
}

export function ensureInstallmentSchedule(schedule = {}) {
    if (usesInstallments(schedule)) {
        return stripLegacyKeys(schedule);
    }

    return legacyToInstallments(schedule);
}

function stripLegacyKeys(schedule) {
    const next = { ...schedule, installments: [...(schedule.installments || [])].map((r) => normalizeInstallmentRow(r)) };
    LEGACY_SCHEDULE_KEYS.forEach((key) => {
        delete next[key];
    });

    return next;
}

export function normalizePaymentSchedule(schedule = {}) {
    return ensureInstallmentSchedule(schedule);
}

export function parseLocalDate(iso) {
    if (!iso || String(iso).trim() === '') {
        return null;
    }
    const d = new Date(`${String(iso).slice(0, 10)}T00:00:00`);
    return Number.isNaN(d.getTime()) ? null : d;
}

function isWeekdayDate(d) {
    const w = d.getDay();
    return w >= 1 && w <= 5;
}

function addBankDaysJs(date, offsetDays) {
    const d = new Date(date.getTime());
    let remaining = Math.abs(offsetDays);
    const step = offsetDays >= 0 ? 1 : -1;
    while (remaining > 0) {
        d.setDate(d.getDate() + step);
        if (isWeekdayDate(d)) {
            remaining -= 1;
        }
    }
    return d;
}

function addCalendarDaysJs(date, offsetDays) {
    const d = new Date(date.getTime());
    d.setDate(d.getDate() + offsetDays);
    return d;
}

function shiftInstallmentDate(anchorDate, offsetDays, unit) {
    if (!anchorDate) {
        return null;
    }
    if (unit === 'bank_days') {
        return addBankDaysJs(anchorDate, offsetDays);
    }
    return addCalendarDaysJs(anchorDate, offsetDays);
}

export function installmentContextDatesFromRoute(routePoints, orderDate) {
    const pts = Array.isArray(routePoints) ? routePoints : [];
    const firstLoad = pts.find((p) => p.type === 'loading' && (p.actual_date || p.planned_date));
    const lastUnl = [...pts].reverse().find((p) => p.type === 'unloading' && (p.actual_date || p.planned_date));
    const firstBorder = pts.find((p) => p.type === 'border_crossing' && (p.actual_date || p.planned_date));
    const fl = firstLoad?.actual_date || firstLoad?.planned_date || null;
    const ul = lastUnl?.actual_date || lastUnl?.planned_date || null;
    return {
        first_loading: fl,
        last_unloading: ul,
        border_crossing: firstBorder?.actual_date || firstBorder?.planned_date || null,
        order_date: orderDate || null,
        loading_date: fl,
        unloading_date: ul,
    };
}

export function resolveInstallmentAnchorDate(anchor, ctx) {
    const a = anchor || 'last_unloading';
    if (a === 'first_loading') {
        return parseLocalDate(ctx.first_loading) || parseLocalDate(ctx.order_date);
    }
    if (a === 'last_unloading') {
        return parseLocalDate(ctx.last_unloading) || parseLocalDate(ctx.unloading_date);
    }
    if (a === 'order_date') {
        return parseLocalDate(ctx.order_date);
    }
    if (a === 'loading_date') {
        return parseLocalDate(ctx.loading_date) || parseLocalDate(ctx.first_loading);
    }
    if (a === 'unloading_date') {
        return parseLocalDate(ctx.unloading_date) || parseLocalDate(ctx.last_unloading);
    }
    if (a === 'border_crossing') {
        return parseLocalDate(ctx.border_crossing);
    }
    return parseLocalDate(ctx.first_loading);
}

export function formatRuMoney(amount, currency) {
    const n = Number(amount || 0);
    const parts = n.toFixed(2).split('.');
    const intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return `${intPart},${parts[1]} ${currency || 'RUB'}`;
}

export function formatRuDateFromDate(d) {
    if (!d) {
        return '';
    }
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}.${mm}.${yyyy}`;
}

export function installmentOffsetPhrase(row) {
    const offset = Number(row.offset_days ?? 0);
    const unitShort = row.offset_unit === 'bank_days' ? 'банк.' : 'календ.';
    const anchorHuman = INSTALLMENT_ANCHOR_END[row.anchor] || row.anchor || 'якоря';
    const abs = Math.abs(offset);
    const unitWord = 'дн';
    if (offset === 0) {
        return `в день якоря (${anchorHuman})`;
    }
    if (offset < 0) {
        return `за ${abs} ${unitShort} ${unitWord} до ${anchorHuman}`;
    }
    return `через ${abs} ${unitShort} ${unitWord} после ${anchorHuman}`;
}

export function plannedDateForInstallmentRow(row, routePoints, orderDate) {
    const ctx = installmentContextDatesFromRoute(routePoints, orderDate);
    const anchor = resolveInstallmentAnchorDate(row.anchor, ctx);
    return shiftInstallmentDate(anchor, Number(row.offset_days ?? 0), row.offset_unit || 'calendar_days');
}

export function formatPercentTrimZeros(pct) {
    const n = Number(pct || 0);
    if (!Number.isFinite(n)) {
        return '0';
    }
    const s = n.toFixed(2);
    if (s.endsWith('.00')) {
        return String(Math.round(n));
    }
    return s.replace(/0+$/, '').replace(/\.$/, '');
}

function dateOnlyString(d) {
    if (!d) {
        return null;
    }
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

export function installmentShouldOmitBasis(row, routePoints, orderDate) {
    if (Number(row.offset_days ?? 0) < 0) {
        return true;
    }
    const planned = plannedDateForInstallmentRow(row, routePoints, orderDate);
    const ctx = installmentContextDatesFromRoute(routePoints, orderDate);
    const lastUnl = parseLocalDate(ctx.last_unloading);
    if (planned && lastUnl) {
        const p = dateOnlyString(planned);
        const u = dateOnlyString(lastUnl);
        if (p && u && p < u) {
            return true;
        }
    }
    return false;
}

function basisPhrase(mode) {
    const k = String(mode || 'fttn').toLowerCase();
    return BASIS_SUMMARY_PHRASE[k] ?? k;
}

export function installmentScheduleSummaryHuman(schedule, totalAmount, currency, routePoints, orderDate) {
    const rows = Array.isArray(schedule?.installments) ? schedule.installments : [];
    if (rows.length === 0) {
        return '';
    }
    const total = Number(totalAmount || 0);
    const cur = currency || 'RUB';
    const parts = [];
    rows.forEach((row) => {
        const pctStr = formatPercentTrimZeros(row.percent);
        const amt = Number(row.amount || 0);
        const phrase = installmentOffsetPhrase(row);
        const omitBasis = installmentShouldOmitBasis(row, routePoints, orderDate);
        let part = '';
        if (total > 0) {
            part = `${pctStr}% (${formatRuMoney(amt, cur)}), ${phrase}`;
        } else {
            part = `${pctStr}%, ${phrase}`;
        }
        if (!omitBasis) {
            part += `, ${basisPhrase(row.basis)}`;
        }
        parts.push(part);
    });
    return parts.join('; ');
}

export function paymentScheduleSummaryHuman(schedule, totalAmount, currency, routePoints, orderDate) {
    const normalized = ensureInstallmentSchedule(schedule);
    return installmentScheduleSummaryHuman(normalized, totalAmount, currency, routePoints, orderDate);
}

export function syncInstallmentAmountsFromPercents(schedule, totalAmount) {
    if (!usesInstallments(schedule)) {
        applyInstallmentScheduleInPlace(schedule);
    }

    if (!usesInstallments(schedule)) {
        return;
    }

    const total = Number(totalAmount || 0);
    const rows = schedule.installments;

    if (!total || total <= 0) {
        return;
    }

    if (rows.length === 1) {
        assignRoundedNumber(rows[0], 'percent', 100);
        assignRoundedNumber(rows[0], 'amount', total);

        return;
    }

    let allocated = 0;
    let percentSum = 0;

    for (let i = 0; i < rows.length; i++) {
        const isLast = i === rows.length - 1;
        if (isLast) {
            assignRoundedNumber(rows[i], 'amount', total - allocated);
            assignRoundedNumber(rows[i], 'percent', 100 - percentSum);
            break;
        }

        const pct = Math.min(100, Math.max(0, Number(rows[i].percent || 0)));
        const amt = (total * pct) / 100;
        assignRoundedNumber(rows[i], 'percent', pct);
        assignRoundedNumber(rows[i], 'amount', amt);
        allocated += rows[i].amount;
        percentSum += rows[i].percent;
    }
}

export function rebalanceInstallmentPercents(schedule, changedIndex) {
    if (!usesInstallments(schedule) || schedule.installments.length < 2) {
        return;
    }

    const rows = schedule.installments;
    const changed = Math.min(rows.length - 1, Math.max(0, changedIndex));
    const pct = Math.min(100, Math.max(0, Number(rows[changed].percent || 0)));
    rows[changed].percent = Math.round(pct * 100) / 100;

    const others = rows.length - 1;
    const remainder = Math.max(0, 100 - rows[changed].percent);
    const each = Math.round((remainder / others) * 100) / 100;
    let distributed = 0;

    rows.forEach((row, index) => {
        if (index === changed) {
            return;
        }
        const isLastOther = index === rows.length - 1 || (changed === rows.length - 1 && index === rows.length - 2);
        if (isLastOther && index !== changed) {
            row.percent = Math.round((100 - rows[changed].percent - distributed) * 100) / 100;
        } else {
            row.percent = each;
            distributed += each;
        }
    });
}

export function rebalanceInstallmentAmounts(schedule, changedIndex, totalAmount) {
    const total = Number(totalAmount || 0);
    if (!usesInstallments(schedule) || schedule.installments.length < 2 || !total) {
        syncInstallmentAmountsFromPercents(schedule, totalAmount);
        return;
    }

    const rows = schedule.installments;
    const changed = Math.min(rows.length - 1, Math.max(0, changedIndex));
    const amt = Math.min(total, Math.max(0, Number(rows[changed].amount || 0)));
    rows[changed].amount = Math.round(amt * 100) / 100;
    rows[changed].percent = Math.round((100 * rows[changed].amount) / total * 100) / 100;

    let allocated = rows[changed].amount;
    const others = rows.filter((_, i) => i !== changed);
    const each = Math.round(((total - allocated) / others.length) * 100) / 100;

    rows.forEach((row, index) => {
        if (index === changed) {
            return;
        }
        const isLast = index === rows.length - 1;
        if (isLast) {
            row.amount = Math.round((total - allocated) * 100) / 100;
        } else {
            row.amount = each;
            allocated += each;
        }
        row.percent = Math.round((100 * row.amount) / total * 100) / 100;
    });
}

export function addInstallmentRow(schedule) {
    applyInstallmentScheduleInPlace(schedule);

    if (schedule.installments.length >= MAX_INSTALLMENTS) {
        return false;
    }

    const rows = schedule.installments;
    const defaultPercent = rows.length === 0 ? 100 : Math.round((100 / (rows.length + 1)) * 100) / 100;
    rows.push(blankInstallmentRow({ percent: defaultPercent }));
    rebalanceInstallmentPercents(schedule, rows.length - 1);
    stripLegacyKeysInPlace(schedule);

    return true;
}

export function removeInstallmentRow(schedule, index) {
    if (!usesInstallments(schedule) || schedule.installments.length <= 1) {
        return false;
    }

    schedule.installments.splice(index, 1);
    if (schedule.installments.length === 1) {
        schedule.installments[0].percent = 100;
    } else {
        rebalanceInstallmentPercents(schedule, 0);
    }
    return true;
}

/** @deprecated Все графики хранятся как installments */
export function applyStandardScheduleShape(schedule) {
    Object.assign(schedule, blankSingleInstallmentSchedule());
}

export function applyDetailedScheduleShape(schedule) {
    if (!usesInstallments(schedule)) {
        Object.assign(schedule, blankSingleInstallmentSchedule());
    }
}
