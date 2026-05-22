export const MODE_TOP_DOWN = 'top_down';
export const MODE_BOTTOM_UP = 'bottom_up';

export function defaultBudgetInputs() {
    return {
        calculation_mode: MODE_TOP_DOWN,
        horizon_months: 12,
        breakeven_month: 6,
        cash_zero_month: 12,
        target_dividends_month: 12,
        target_dividends_amount: 250_000,
        owner_investment: 300_000,
        manager_count: 3,
        margin_per_manager: null,
        use_db_margin_per_manager: false,
    };
}

export function normalizeBudgetInputs(raw) {
    const defaults = defaultBudgetInputs();
    const mode = raw?.calculation_mode === MODE_BOTTOM_UP ? MODE_BOTTOM_UP : MODE_TOP_DOWN;
    const horizon = Math.max(6, Math.min(36, Number(raw?.horizon_months ?? defaults.horizon_months) || defaults.horizon_months));
    const breakevenMonth = Math.max(1, Math.min(horizon, Number(raw?.breakeven_month ?? defaults.breakeven_month) || defaults.breakeven_month));
    const cashZeroMonth = Math.max(
        breakevenMonth,
        Math.min(
            horizon,
            Number(raw?.cash_zero_month ?? defaults.cash_zero_month ?? breakevenMonth) || defaults.cash_zero_month || breakevenMonth,
        ),
    );
    const targetMonth = Math.max(
        cashZeroMonth,
        Math.min(horizon, Number(raw?.target_dividends_month ?? defaults.target_dividends_month) || defaults.target_dividends_month),
    );

    const marginRaw = raw?.margin_per_manager;
    const marginPerManager = marginRaw === null || marginRaw === undefined || marginRaw === ''
        ? null
        : Math.max(0, Number(marginRaw) || 0);

    return {
        calculation_mode: mode,
        horizon_months: horizon,
        breakeven_month: breakevenMonth,
        cash_zero_month: cashZeroMonth,
        target_dividends_month: targetMonth,
        target_dividends_amount: Math.max(0, Number(raw?.target_dividends_amount ?? defaults.target_dividends_amount) || 0),
        owner_investment: Math.max(0, Number(raw?.owner_investment ?? defaults.owner_investment) || 0),
        manager_count: Math.max(1, Math.min(100, Number(raw?.manager_count ?? defaults.manager_count) || defaults.manager_count)),
        margin_per_manager: marginPerManager,
        use_db_margin_per_manager: Boolean(raw?.use_db_margin_per_manager),
    };
}

export function articleAppliesToMonth(month, article) {
    const ramp = article.ramp_months;

    return ramp === null || ramp === undefined || month <= Number(ramp);
}

export function monthlyFixedOpex(month, opexArticles) {
    let total = 0;

    for (const article of opexArticles) {
        if (!articleAppliesToMonth(month, article)) {
            continue;
        }

        if (article.cost_type === 'percent_of_margin') {
            continue;
        }

        total += Number(article.amount_monthly) || 0;
    }

    return total;
}

export function monthlyPercentRate(month, opexArticles) {
    let rate = 0;

    for (const article of opexArticles) {
        if (!articleAppliesToMonth(month, article)) {
            continue;
        }

        if (article.cost_type !== 'percent_of_margin') {
            continue;
        }

        rate += Math.max(0, Number(article.percent_of_margin) || 0) / 100;
    }

    return Math.min(0.99, rate);
}

export function monthlyOpex(month, opexArticles, margin = 0) {
    const fixed = monthlyFixedOpex(month, opexArticles);

    return fixed + margin * monthlyPercentRate(month, opexArticles);
}

export function marginForTargetNet(month, opexArticles, targetNet) {
    const fixed = monthlyFixedOpex(month, opexArticles);
    const rate = monthlyPercentRate(month, opexArticles);

    if (rate >= 0.99) {
        return 0;
    }

    return (fixed + targetNet) / (1 - rate);
}

/**
 * @param {list<{month: number, margin: number}>} knots
 */
function marginAtMonthWithKnots(month, knots) {
    let prev = { month: 0, margin: 0 };

    for (const knot of knots) {
        if (month <= knot.month) {
            const span = knot.month - prev.month;

            if (span <= 0) {
                return knot.margin;
            }

            const t = (month - prev.month) / span;

            return prev.margin + t * (knot.margin - prev.margin);
        }

        prev = knot;
    }

    return knots[knots.length - 1]?.margin ?? 0;
}

/**
 * @param {list<{month: number, margin: number}>} knots
 */
function simulateCumulativeThroughMonth(throughMonth, inputs, opexArticles, knots) {
    let cumulative = inputs.owner_investment;

    for (let month = 1; month <= throughMonth; month += 1) {
        const margin = marginAtMonthWithKnots(month, knots);
        cumulative += margin - monthlyOpex(month, opexArticles, margin);
    }

    return cumulative;
}

function resolveMarginAtCashZero(inputs, opexArticles, operatingMonth, cashZeroMonth, targetMonth, marginAtOperating, marginAtTarget) {
    const floor = Math.max(
        marginAtOperating,
        marginForTargetNet(cashZeroMonth, opexArticles, 0),
    );

    if (cashZeroMonth <= operatingMonth) {
        return floor;
    }

    let low = floor;
    let high = Math.max(marginAtTarget, floor * 2, marginForTargetNet(cashZeroMonth, opexArticles, inputs.target_dividends_amount));

    for (let i = 0; i < 64; i += 1) {
        const mid = (low + high) / 2;
        const knots = [
            { month: operatingMonth, margin: marginAtOperating },
            { month: cashZeroMonth, margin: mid },
            { month: targetMonth, margin: marginAtTarget },
        ];
        const cumulative = simulateCumulativeThroughMonth(cashZeroMonth, inputs, opexArticles, knots);

        if (cumulative >= 0) {
            high = mid;
        } else {
            low = mid;
        }
    }

    return Math.max(floor, high);
}

function resolveMarginPerManager(inputs, dbBenchmark) {
    if (inputs.use_db_margin_per_manager && dbBenchmark) {
        const fromDb = dbBenchmark.margin_per_manager_avg;

        if (fromDb !== null && fromDb > 0) {
            return fromDb;
        }

        const company = dbBenchmark.company_margin_monthly_avg;

        if (company !== null && company > 0) {
            return company / inputs.manager_count;
        }
    }

    if (inputs.margin_per_manager !== null && inputs.margin_per_manager > 0) {
        return inputs.margin_per_manager;
    }

    return 0;
}

/** Оценка месяца кассовой безубыточности, если за горизонтом остаток ещё < 0, но месячный поток > 0. */
export function estimateCashBreakevenMonth(months) {
    if (!months?.length) {
        return null;
    }

    const last = months[months.length - 1];

    if (last.cumulative >= 0 || last.net <= 0) {
        return null;
    }

    return last.month + Math.ceil(-last.cumulative / last.net);
}

function managerPlanFromMonths(months, managerCount, planMilestoneMonth = null, targetMonth = null, cashZeroPlanMonth = null) {
    let firstOperating = null;
    let firstCash = null;
    let wasInDeficit = false;

    for (const row of months) {
        if (firstOperating === null && row.net >= 0) {
            firstOperating = row.month;
        }

        if (row.cumulative < 0) {
            wasInDeficit = true;
        }

        if (firstCash === null && wasInDeficit && row.cumulative >= 0) {
            firstCash = row.month;
        }
    }

    const rows = months.map((row) => {
        const tags = [];

        if (planMilestoneMonth !== null && row.month === planMilestoneMonth) {
            tags.push('plan_milestone');
        }

        if (firstOperating !== null && row.month === firstOperating) {
            tags.push('operating_be');
        }

        if (firstCash !== null && row.month === firstCash) {
            tags.push('cash_be');
        }

        if (targetMonth !== null && row.month === targetMonth) {
            tags.push('target');
        }

        return {
            month: row.month,
            margin_per_manager: row.margin_per_manager,
            margin_company: row.margin,
            net_company: row.net,
            cumulative: row.cumulative,
            tags,
        };
    });

    const last = months[months.length - 1];
    const yMonth = firstCash ?? firstOperating;
    const yRow = yMonth !== null
        ? months.find((m) => m.month === yMonth)
        : months[0];

    return {
        rows,
        x: last ? Math.round(last.margin_per_manager * 100) / 100 : 0,
        y: yRow ? Math.round(yRow.margin_per_manager * 100) / 100 : 0,
        manager_count: managerCount,
        breakeven_month_operating: firstOperating,
        breakeven_month_cash: firstCash,
    };
}

function buildTopDownPlan(inputs, opexArticles) {
    const horizon = inputs.horizon_months;
    const operatingMonth = inputs.breakeven_month;
    const cashZeroMonth = inputs.cash_zero_month;
    const targetMonth = inputs.target_dividends_month;
    const managerCount = inputs.manager_count;

    let marginAtOperating = marginForTargetNet(operatingMonth, opexArticles, 0);
    let marginAtTarget = marginForTargetNet(targetMonth, opexArticles, inputs.target_dividends_amount);
    let marginAtCash = resolveMarginAtCashZero(
        inputs,
        opexArticles,
        operatingMonth,
        cashZeroMonth,
        targetMonth,
        marginAtOperating,
        marginAtTarget,
    );

    marginAtCash = Math.max(marginAtOperating, marginAtCash);
    marginAtTarget = Math.max(marginAtCash, marginAtTarget);

    const rampKnots = [
        { month: operatingMonth, margin: marginAtOperating },
        { month: cashZeroMonth, margin: marginAtCash },
        { month: targetMonth, margin: marginAtTarget },
    ];

    const months = [];
    let cumulative = inputs.owner_investment;
    let minCumulative = cumulative;

    for (let month = 1; month <= horizon; month += 1) {
        const margin = marginAtMonthWithKnots(month, rampKnots);
        const opex = monthlyOpex(month, opexArticles, margin);
        const net = margin - opex;
        cumulative += net;
        minCumulative = Math.min(minCumulative, cumulative);

        months.push({
            month,
            margin: Math.round(margin * 100) / 100,
            margin_per_manager: Math.round((margin / managerCount) * 100) / 100,
            opex: Math.round(opex * 100) / 100,
            opex_fixed: Math.round(monthlyFixedOpex(month, opexArticles) * 100) / 100,
            opex_percent: Math.round((opex - monthlyFixedOpex(month, opexArticles)) * 100) / 100,
            net: Math.round(net * 100) / 100,
            cumulative: Math.round(cumulative * 100) / 100,
        });
    }

    const managerPlan = managerPlanFromMonths(months, managerCount, operatingMonth, targetMonth, cashZeroMonth);
    const cashEstimated = managerPlan.breakeven_month_cash === null
        ? estimateCashBreakevenMonth(months)
        : null;

    return {
        mode: MODE_TOP_DOWN,
        months,
        manager_plan: managerPlan,
        summary: {
            calculation_mode: MODE_TOP_DOWN,
            required_margin_breakeven: Math.round(marginAtOperating * 100) / 100,
            required_margin_cash_zero: Math.round(marginAtCash * 100) / 100,
            required_margin_target: Math.round(marginAtTarget * 100) / 100,
            manager_target_x: Math.round((marginAtTarget / managerCount) * 100) / 100,
            manager_floor_y: managerPlan.y,
            breakeven_month_operating: managerPlan.breakeven_month_operating,
            breakeven_month_cash: managerPlan.breakeven_month_cash,
            breakeven_month_cash_estimated: cashEstimated,
            plan_milestone_month: operatingMonth,
            cash_zero_month: cashZeroMonth,
            dividends_feasible_month: targetMonth,
            breakeven_month: managerPlan.breakeven_month_cash,
            target_dividends_month: targetMonth,
            target_dividends_amount: inputs.target_dividends_amount,
            owner_investment: inputs.owner_investment,
            min_cumulative: Math.round(minCumulative * 100) / 100,
            cumulative_at_horizon: Math.round(cumulative * 100) / 100,
            manager_count: managerCount,
            margin_per_manager_used: null,
            company_margin_monthly: null,
        },
    };
}

function buildBottomUpPlan(inputs, opexArticles, dbBenchmark) {
    const horizon = inputs.horizon_months;
    const managerCount = inputs.manager_count;
    const marginPerManager = resolveMarginPerManager(inputs, dbBenchmark);
    const companyMargin = marginPerManager * managerCount;

    const months = [];
    let cumulative = inputs.owner_investment;
    let minCumulative = cumulative;
    let breakevenOperating = null;
    let dividendsMonth = null;

    for (let month = 1; month <= horizon; month += 1) {
        const margin = companyMargin;
        const opex = monthlyOpex(month, opexArticles, margin);
        const net = margin - opex;
        cumulative += net;
        minCumulative = Math.min(minCumulative, cumulative);

        if (breakevenOperating === null && net >= 0) {
            breakevenOperating = month;
        }

        if (dividendsMonth === null && net >= inputs.target_dividends_amount) {
            dividendsMonth = month;
        }

        months.push({
            month,
            margin: Math.round(margin * 100) / 100,
            margin_per_manager: Math.round(marginPerManager * 100) / 100,
            opex: Math.round(opex * 100) / 100,
            opex_fixed: Math.round(monthlyFixedOpex(month, opexArticles) * 100) / 100,
            opex_percent: Math.round((opex - monthlyFixedOpex(month, opexArticles)) * 100) / 100,
            net: Math.round(net * 100) / 100,
            cumulative: Math.round(cumulative * 100) / 100,
        });
    }

    const managerPlan = managerPlanFromMonths(months, managerCount, null, dividendsMonth);
    const cashMonth = managerPlan.breakeven_month_cash;
    const beMonth = cashMonth ?? breakevenOperating ?? inputs.breakeven_month;
    const marginRequiredAtBe = marginForTargetNet(beMonth, opexArticles, 0);
    const cashEstimated = cashMonth === null ? estimateCashBreakevenMonth(months) : null;

    return {
        mode: MODE_BOTTOM_UP,
        months,
        manager_plan: managerPlan,
        summary: {
            calculation_mode: MODE_BOTTOM_UP,
            margin_per_manager_used: Math.round(marginPerManager * 100) / 100,
            company_margin_monthly: Math.round(companyMargin * 100) / 100,
            breakeven_month_operating: managerPlan.breakeven_month_operating ?? breakevenOperating,
            breakeven_month_cash: cashMonth,
            breakeven_month_cash_estimated: cashEstimated,
            plan_milestone_month: null,
            dividends_feasible_month: dividendsMonth,
            required_margin_breakeven: Math.round(marginRequiredAtBe * 100) / 100,
            manager_floor_y: managerPlan.y,
            manager_target_x: Math.round(marginPerManager * 100) / 100,
            breakeven_month: cashMonth,
            target_dividends_month: inputs.target_dividends_month,
            target_dividends_amount: inputs.target_dividends_amount,
            owner_investment: inputs.owner_investment,
            min_cumulative: Math.round(minCumulative * 100) / 100,
            cumulative_at_horizon: Math.round(cumulative * 100) / 100,
            manager_count: managerCount,
        },
    };
}

export function buildBudgetPlan(rawInputs, opexArticles = [], dbBenchmark = null) {
    const inputs = normalizeBudgetInputs(rawInputs);

    return inputs.calculation_mode === MODE_BOTTOM_UP
        ? buildBottomUpPlan(inputs, opexArticles, dbBenchmark)
        : buildTopDownPlan(inputs, opexArticles);
}

/** План «цели → нужная маржа» (рампа). */
export function buildGoalsToMarginPlan(rawInputs, opexArticles = [], dbBenchmark = null) {
    return buildBudgetPlan(
        { ...normalizeBudgetInputs(rawInputs), calculation_mode: MODE_TOP_DOWN },
        opexArticles,
        dbBenchmark,
    );
}

/** Прогноз «стабильная маржа → когда цели» (плато). */
export function buildMarginToTimelinePlan(rawInputs, opexArticles = [], dbBenchmark = null, marginPerManager) {
    const base = normalizeBudgetInputs(rawInputs);

    return buildBudgetPlan(
        {
            ...base,
            calculation_mode: MODE_BOTTOM_UP,
            margin_per_manager: Math.max(0, Number(marginPerManager) || 0),
            use_db_margin_per_manager: false,
        },
        opexArticles,
        dbBenchmark,
    );
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

// Обратная совместимость импорта
export { buildBudgetPlan as buildBudgetPlanFromInputs };
