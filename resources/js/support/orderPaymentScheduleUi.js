/** Подписи базиса для человекочитабельной сводки (нижний регистр). */
export const BASIS_SUMMARY_PHRASE = {
    fttn: 'по сканам',
    fttn_receipt: 'по сканам + квиток',
    ottn: 'по оригиналам',
    loading: 'при погрузке',
    unloading: 'при выгрузке',
};

export const PAYMENT_BASIS_OPTIONS = [
    { value: 'fttn', label: 'По сканам' },
    { value: 'fttn_receipt', label: 'По сканам + квиток' },
    { value: 'ottn', label: 'По оригиналам' },
    { value: 'loading', label: 'При погрузке' },
    { value: 'unloading', label: 'При выгрузке' },
];

export const PAYMENT_ANCHOR_OPTIONS = [
    { value: 'first_loading', label: 'Первая погрузка (план в маршруте)' },
    { value: 'last_unloading', label: 'Последняя выгрузка (план)' },
    { value: 'order_date', label: 'Дата заказа' },
    { value: 'loading_date', label: 'Дата погрузки (в заказе)' },
    { value: 'unloading_date', label: 'Дата выгрузки (в заказе)' },
];

export const PAYMENT_OFFSET_UNIT_OPTIONS = [
    { value: 'calendar_days', label: 'Календарные дни' },
    { value: 'bank_days', label: 'Банковские дни (пн–пт)' },
];

const INSTALLMENT_ANCHOR_END = {
    first_loading: 'первой погрузки',
    last_unloading: 'последней выгрузки',
    order_date: 'даты заказа',
    loading_date: 'даты погрузки (заказ)',
    unloading_date: 'даты выгрузки (заказ)',
};

export function blankPaymentSchedule() {
    return {
        has_prepayment: false,
        prepayment_ratio: 50,
        prepayment_days: 0,
        prepayment_mode: 'fttn',
        postpayment_days: 0,
        postpayment_mode: 'ottn',
    };
}

export function blankSingleInstallmentSchedule() {
    return {
        installments: [
            {
                percent: 100,
                amount: null,
                offset_days: -3,
                offset_unit: 'calendar_days',
                anchor: 'first_loading',
                basis: 'fttn',
            },
        ],
    };
}

export function blankTwoInstallmentSchedule() {
    return {
        installments: [
            { percent: 50, amount: null, offset_days: -14, offset_unit: 'bank_days', anchor: 'first_loading', basis: 'fttn' },
            { percent: 50, amount: null, offset_days: -1, offset_unit: 'calendar_days', anchor: 'first_loading', basis: 'ottn' },
        ],
    };
}

export function normalizeInstallmentRow(row = {}) {
    return {
        percent: Number(row.percent ?? 0) || 0,
        amount: row.amount !== null && row.amount !== undefined && row.amount !== '' ? Number(row.amount) : null,
        offset_days: Number(row.offset_days ?? 0),
        offset_unit: row.offset_unit === 'bank_days' ? 'bank_days' : 'calendar_days',
        anchor: row.anchor || 'first_loading',
        basis: row.basis || 'fttn',
    };
}

export function usesInstallments(schedule) {
    return Array.isArray(schedule?.installments) && schedule.installments.length > 0;
}

export function normalizePaymentSchedule(schedule = {}) {
    if (usesInstallments(schedule)) {
        return {
            installments: schedule.installments.map((r) => normalizeInstallmentRow(r)),
        };
    }

    const raw = schedule?.has_prepayment;
    const hasPrepayment = raw === true || raw === 1 || raw === '1';

    return {
        ...blankPaymentSchedule(),
        ...schedule,
        has_prepayment: hasPrepayment,
    };
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
    const firstLoad = pts.find((p) => p.type === 'loading' && p.planned_date);
    const lastUnl = [...pts].reverse().find((p) => p.type === 'unloading' && p.planned_date);
    const fl = firstLoad?.planned_date || null;
    return {
        first_loading: fl,
        last_unloading: lastUnl?.planned_date || null,
        order_date: orderDate || null,
        loading_date: fl,
        unloading_date: lastUnl?.planned_date || null,
    };
}

export function resolveInstallmentAnchorDate(anchor, ctx) {
    const a = anchor || 'first_loading';
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

/** Классический график: «50% в течение 3 кал. дн. по сканам; 50% …». */
export function classicScheduleSummaryHuman(schedule) {
    const normalized = normalizePaymentSchedule(schedule);
    const postPct = normalized.has_prepayment ? Math.max(0, 100 - Number(normalized.prepayment_ratio || 0)) : 100;
    const postDays = Number(normalized.postpayment_days || 0);
    const postBasis = basisPhrase(normalized.postpayment_mode);
    const postPart = `${formatPercentTrimZeros(postPct)}% в течение ${postDays} кал. дн. ${postBasis}`;

    if (!normalized.has_prepayment) {
        return postPart;
    }

    const prePct = Number(normalized.prepayment_ratio || 0);
    const preDays = Number(normalized.prepayment_days || 0);
    const preBasis = basisPhrase(normalized.prepayment_mode);

    return `${formatPercentTrimZeros(prePct)}% в течение ${preDays} кал. дн. ${preBasis}; ${postPart}`;
}

/** Транши: без «Транш 1:», без план-дат; базис скрывается до наступления услуги. */
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
    if (usesInstallments(schedule)) {
        return installmentScheduleSummaryHuman(schedule, totalAmount, currency, routePoints, orderDate);
    }
    return classicScheduleSummaryHuman(schedule);
}

export function syncInstallmentAmountsFromPercents(schedule, totalAmount) {
    if (!usesInstallments(schedule)) {
        return;
    }
    const total = Number(totalAmount || 0);
    const rows = schedule.installments;
    if (!total || total <= 0) {
        return;
    }
    if (rows.length === 1) {
        rows[0].percent = 100;
        rows[0].amount = Math.round(total * 100) / 100;
        return;
    }
    if (rows.length < 2) {
        return;
    }
    const p1 = Math.min(100, Math.max(0, Number(rows[0].percent || 0)));
    rows[0].percent = Math.round(p1 * 100) / 100;
    rows[1].percent = Math.round((100 - p1) * 100) / 100;
    const a1 = Math.round((total * p1) / 100 * 100) / 100;
    rows[0].amount = a1;
    rows[1].amount = Math.round((total - a1) * 100) / 100;
}

export function applyStandardScheduleShape(schedule) {
    if (!schedule || typeof schedule !== 'object') {
        return;
    }
    delete schedule.installments;
    Object.assign(schedule, blankPaymentSchedule());
}

export function applyDetailedScheduleShape(schedule, twoRows) {
    if (!schedule || typeof schedule !== 'object') {
        return;
    }
    ['has_prepayment', 'prepayment_ratio', 'prepayment_days', 'prepayment_mode', 'postpayment_days', 'postpayment_mode'].forEach((k) => {
        delete schedule[k];
    });
    const next = twoRows ? blankTwoInstallmentSchedule() : blankSingleInstallmentSchedule();
    schedule.installments = next.installments.map((r) => ({ ...r }));
}
