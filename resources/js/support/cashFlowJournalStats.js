/**
 * Агрегаты графика оплат из строк журнала (те же данные, что в CashFlowGrid).
 */

export function defaultCashFlowStats() {
    return {
        periods: {
            today: { incoming: 0, outgoing: 0 },
            week: { incoming: 0, outgoing: 0 },
            month: { incoming: 0, outgoing: 0 },
        },
        receivables: { total: 0, pending: 0, overdue: 0 },
        payables: { total: 0, pending: 0, overdue: 0 },
    };
}

export function cashFlowRowEffectiveAmount(row) {
    const amount = Number(row?.amount ?? 0);
    const remaining = row?.remaining_amount;

    if (remaining === null || remaining === undefined || remaining === '') {
        return amount;
    }

    const remainingNumber = Number(remaining);

    if (!Number.isFinite(remainingNumber) || remainingNumber <= 0) {
        if (row?.status === 'paid' || row?.status === 'cancelled') {
            return 0;
        }

        return amount;
    }

    return remainingNumber;
}

export function cashFlowRowDisplayAmount(row) {
    if (row?.amount_due !== undefined && row?.amount_due !== null && row?.amount_due !== '') {
        const amountDue = Number(row.amount_due);

        if (Number.isFinite(amountDue)) {
            return amountDue;
        }
    }

    return cashFlowRowEffectiveAmount(row);
}

export function cashFlowRowStatusLabel(row) {
    if (row?.is_partially_settled) {
        return 'Частично оплачено';
    }

    if (isCashFlowRowOverdue(row)) {
        return 'Просрочено';
    }

    const labels = {
        pending: 'По плану',
        paid: 'Оплачено',
        overdue: 'Просрочено',
        cancelled: 'Отменено',
    };

    return labels[row?.status] || row?.status || '';
}

export function cashFlowTodayIso(referenceDate = new Date()) {
    return referenceDate.toISOString().slice(0, 10);
}

export function isCashFlowRowOverdue(row, todayIso = cashFlowTodayIso()) {
    if (row?.status === 'overdue') {
        return true;
    }

    if (row?.status !== 'pending' || !row?.planned_date) {
        return false;
    }

    return String(row.planned_date).slice(0, 10) < todayIso;
}

export function isCashFlowRowPendingOnPlan(row, todayIso = cashFlowTodayIso()) {
    return row?.status === 'pending' && !isCashFlowRowOverdue(row, todayIso);
}

function addDaysIso(isoDate, days) {
    const date = new Date(`${isoDate}T12:00:00`);
    date.setDate(date.getDate() + days);

    return date.toISOString().slice(0, 10);
}

function monthStartIso(referenceDate) {
    const year = referenceDate.getFullYear();
    const month = String(referenceDate.getMonth() + 1).padStart(2, '0');

    return `${year}-${month}-01`;
}

function monthEndIso(referenceDate) {
    const date = new Date(referenceDate.getFullYear(), referenceDate.getMonth() + 1, 0);

    return date.toISOString().slice(0, 10);
}

function plannedDateIso(row) {
    return row?.planned_date ? String(row.planned_date).slice(0, 10) : null;
}

function isIncomingRow(row) {
    return row?.direction === 'Нам';
}

/**
 * @param {Array<Record<string, unknown>>|null|undefined} rows
 * @param {Date} [referenceDate]
 */
export function summarizeCashFlowJournal(rows, referenceDate = new Date()) {
    const stats = defaultCashFlowStats();
    const today = cashFlowTodayIso(referenceDate);
    const weekEnd = addDaysIso(today, 6);
    const monthStart = monthStartIso(referenceDate);
    const monthEnd = monthEndIso(referenceDate);

    for (const row of rows ?? []) {
        const amount = cashFlowRowDisplayAmount(row);

        if (!Number.isFinite(amount) || amount <= 0) {
            continue;
        }

        const incoming = isIncomingRow(row);
        const planned = plannedDateIso(row);
        const overdue = isCashFlowRowOverdue(row, today);
        const pendingOnPlan = isCashFlowRowPendingOnPlan(row, today);
        const bucket = incoming ? stats.receivables : stats.payables;

        bucket.total += amount;

        if (overdue) {
            bucket.overdue += amount;
        } else if (pendingOnPlan) {
            bucket.pending += amount;
        }

        if (planned === today) {
            if (incoming) {
                stats.periods.today.incoming += amount;
            } else {
                stats.periods.today.outgoing += amount;
            }
        }

        if (planned && planned >= today && planned <= weekEnd) {
            if (incoming) {
                stats.periods.week.incoming += amount;
            } else {
                stats.periods.week.outgoing += amount;
            }
        }

        if (planned && planned >= monthStart && planned <= monthEnd) {
            if (incoming) {
                stats.periods.month.incoming += amount;
            } else {
                stats.periods.month.outgoing += amount;
            }
        }
    }

    return stats;
}
