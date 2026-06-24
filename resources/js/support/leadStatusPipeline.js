export const LEAD_PIPELINE_STEPS = [
    { value: 'new', label: 'Новый', shortLabel: 'Новый' },
    { value: 'qualification', label: 'Сбор деталей', shortLabel: 'Детали' },
    { value: 'calculation', label: 'Просчёт', shortLabel: 'Просчёт' },
    { value: 'proposal_ready', label: 'КП готово', shortLabel: 'КП' },
    { value: 'proposal_sent', label: 'КП отправлено', shortLabel: 'Отправлено' },
    { value: 'negotiation', label: 'Переговоры', shortLabel: 'Переговоры' },
];

export const LEAD_TERMINAL_STATUS_OPTIONS = [
    { value: 'on_hold', label: 'Отложен', tone: 'muted' },
    { value: 'lost', label: 'Закрыт без сделки', tone: 'danger' },
    { value: 'won', label: 'Выигран', tone: 'success' },
];

export function leadPipelineIndex(status) {
    return LEAD_PIPELINE_STEPS.findIndex((step) => step.value === status);
}

export function hasMeaningfulLeadRoute(routePoints) {
    if (!Array.isArray(routePoints) || routePoints.length === 0) {
        return false;
    }

    return routePoints.some((point) => String(point?.address ?? '').trim() !== '');
}

export function hasMeaningfulLeadCargo(cargoItems) {
    if (!Array.isArray(cargoItems) || cargoItems.length === 0) {
        return false;
    }

    return cargoItems.some((item) => String(item?.name ?? '').trim() !== '');
}

export function resolveLeadAutoStatus(currentStatus, routePoints, cargoItems, targetPrice) {
    if (['won', 'lost', 'on_hold'].includes(currentStatus)) {
        return currentStatus;
    }

    let suggested = currentStatus;
    const order = LEAD_PIPELINE_STEPS.map((step) => step.value);
    const bump = (candidate) => {
        const currentIndex = order.indexOf(suggested);
        const candidateIndex = order.indexOf(candidate);

        if (candidateIndex > currentIndex) {
            suggested = candidate;
        }
    };

    if (hasMeaningfulLeadRoute(routePoints) && hasMeaningfulLeadCargo(cargoItems)) {
        bump('calculation');
    }

    if (targetPrice !== null && targetPrice !== undefined && targetPrice !== '') {
        bump('proposal_ready');
    }

    return suggested;
}

export function firstRoutePlannedDate(routePoints) {
    if (!Array.isArray(routePoints)) {
        return null;
    }

    const loading = routePoints.find((point) => point?.type === 'loading' && point?.planned_date);

    if (loading?.planned_date) {
        return loading.planned_date;
    }

    const withDate = routePoints.find((point) => point?.planned_date);

    return withDate?.planned_date ?? null;
}
