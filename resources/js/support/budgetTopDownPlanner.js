export function defaultBudgetInputs() {
    return {
        horizon_months: 12,
        breakeven_month: 6,
        target_dividends_month: 12,
        target_dividends_amount: 250_000,
        owner_investment: 300_000,
        office_monthly: 100_000,
        accounting_monthly: 200_000,
        manager_count: 3,
        manager_payroll_monthly: 75_000,
        manager_payroll_months: 3,
    };
}

export function normalizeBudgetInputs(raw) {
    const defaults = defaultBudgetInputs();
    const horizon = Math.max(6, Math.min(36, Number(raw?.horizon_months ?? defaults.horizon_months) || defaults.horizon_months));
    const breakevenMonth = Math.max(1, Math.min(horizon, Number(raw?.breakeven_month ?? defaults.breakeven_month) || defaults.breakeven_month));
    const targetMonth = Math.max(
        breakevenMonth,
        Math.min(horizon, Number(raw?.target_dividends_month ?? defaults.target_dividends_month) || defaults.target_dividends_month),
    );

    return {
        horizon_months: horizon,
        breakeven_month: breakevenMonth,
        target_dividends_month: targetMonth,
        target_dividends_amount: Math.max(0, Number(raw?.target_dividends_amount ?? defaults.target_dividends_amount) || 0),
        owner_investment: Math.max(0, Number(raw?.owner_investment ?? defaults.owner_investment) || 0),
        office_monthly: Math.max(0, Number(raw?.office_monthly ?? defaults.office_monthly) || 0),
        accounting_monthly: Math.max(0, Number(raw?.accounting_monthly ?? defaults.accounting_monthly) || 0),
        manager_count: Math.max(1, Math.min(100, Number(raw?.manager_count ?? defaults.manager_count) || defaults.manager_count)),
        manager_payroll_monthly: Math.max(0, Number(raw?.manager_payroll_monthly ?? defaults.manager_payroll_monthly) || 0),
        manager_payroll_months: Math.max(0, Math.min(horizon, Number(raw?.manager_payroll_months ?? defaults.manager_payroll_months) || 0)),
    };
}

function monthlyOpex(month, inputs) {
    let opex = inputs.office_monthly + inputs.accounting_monthly;

    if (month <= inputs.manager_payroll_months) {
        opex += inputs.manager_payroll_monthly;
    }

    return opex;
}

function interpolateMargin(month, breakevenMonth, targetMonth, marginAtBreakeven, marginAtTarget) {
    if (month <= breakevenMonth) {
        if (breakevenMonth <= 1) {
            return marginAtBreakeven;
        }

        return marginAtBreakeven * (month / breakevenMonth);
    }

    if (month <= targetMonth) {
        const span = targetMonth - breakevenMonth;

        if (span <= 0) {
            return marginAtTarget;
        }

        return marginAtBreakeven + ((marginAtTarget - marginAtBreakeven) * (month - breakevenMonth)) / span;
    }

    return marginAtTarget;
}

export function buildBudgetPlan(rawInputs) {
    const inputs = normalizeBudgetInputs(rawInputs);
    const horizon = inputs.horizon_months;
    const breakevenMonth = inputs.breakeven_month;
    const targetMonth = inputs.target_dividends_month;

    const marginAtBreakeven = monthlyOpex(breakevenMonth, inputs);
    const marginAtTarget = monthlyOpex(targetMonth, inputs) + inputs.target_dividends_amount;

    const months = [];
    let cumulative = inputs.owner_investment;
    let minCumulative = cumulative;

    for (let month = 1; month <= horizon; month += 1) {
        const margin = interpolateMargin(month, breakevenMonth, targetMonth, marginAtBreakeven, marginAtTarget);
        const opex = monthlyOpex(month, inputs);
        const net = margin - opex;
        cumulative += net;
        minCumulative = Math.min(minCumulative, cumulative);

        months.push({
            month,
            margin: Math.round(margin * 100) / 100,
            opex: Math.round(opex * 100) / 100,
            net: Math.round(net * 100) / 100,
            cumulative: Math.round(cumulative * 100) / 100,
        });
    }

    const managerCount = inputs.manager_count;

    return {
        months,
        summary: {
            required_margin_breakeven: Math.round(marginAtBreakeven * 100) / 100,
            required_margin_target: Math.round(marginAtTarget * 100) / 100,
            manager_target_x: Math.round((marginAtTarget / managerCount) * 100) / 100,
            manager_floor_y: Math.round((marginAtBreakeven / managerCount) * 100) / 100,
            owner_investment: inputs.owner_investment,
            min_cumulative: Math.round(minCumulative * 100) / 100,
            cumulative_at_horizon: Math.round(cumulative * 100) / 100,
            manager_count: managerCount,
            breakeven_month: breakevenMonth,
            target_dividends_month: targetMonth,
        },
    };
}

export function formatBudgetMoney(value) {
    const n = Number(value);

    if (!Number.isFinite(n)) {
        return '—';
    }

    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 0,
    }).format(n);
}
