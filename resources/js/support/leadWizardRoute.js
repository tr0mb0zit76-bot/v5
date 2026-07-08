import {
    extractCityFromAddress,
    routePointCityValue,
    setRoutePointCity,
    syncRoutePointCityFromAddress,
} from '@/support/routePointNormalizedData.js';

export function blankLeadRoutePoint(type = 'loading', sequence = 1, stage = 'leg_1') {
    return {
        type,
        stage,
        sequence,
        address: '',
        normalized_data: {},
        planned_date: '',
        planned_time_from: '',
        planned_time_to: '',
        contact_person: '',
        contact_phone: '',
        sender_name: '',
        sender_contact: '',
        sender_phone: '',
        recipient_name: '',
        recipient_contact: '',
        recipient_phone: '',
    };
}

export function defaultLeadRoutePoints() {
    return [
        blankLeadRoutePoint('loading', 1, 'leg_1'),
        blankLeadRoutePoint('unloading', 2, 'leg_1'),
    ];
}

export function normalizeLeadRoutePoint(raw = {}, index = 0) {
    const stage = String(raw.stage ?? 'leg_1').trim() || 'leg_1';
    const base = blankLeadRoutePoint(raw.type ?? 'loading', Number(raw.sequence ?? (index + 1)), stage);

    return {
        ...base,
        ...raw,
        stage,
        sequence: Number(raw.sequence ?? (index + 1)),
        normalized_data: raw.normalized_data && typeof raw.normalized_data === 'object' ? raw.normalized_data : {},
        planned_date: raw.planned_date ?? '',
        planned_time_from: raw.planned_time_from ?? '',
        planned_time_to: raw.planned_time_to ?? '',
    };
}

export function normalizeLeadRoutePoints(points) {
    if (!Array.isArray(points) || points.length === 0) {
        return defaultLeadRoutePoints();
    }

    return points.map((point, index) => normalizeLeadRoutePoint(point, index));
}

export function routePointTimeBlockHeading(type) {
    if (type === 'loading') {
        return 'Время загрузки';
    }

    return 'Время выгрузки';
}

export function routePointOrdinal(points, index) {
    const currentPoint = points[index];

    return points
        .slice(0, index + 1)
        .filter((point) => point.type === currentPoint?.type)
        .length;
}

export function routePointTitle(points, point, index) {
    const ordinal = routePointOrdinal(points, index);

    if (point.type === 'loading') {
        return `Погрузка ${ordinal}`;
    }

    return `Выгрузка ${ordinal}`;
}

export function routePointCombinedContact(point) {
    if (point.type === 'loading') {
        return point.sender_contact || point.sender_phone || point.contact_person || point.contact_phone || '';
    }

    if (point.type === 'unloading') {
        return point.recipient_contact || point.recipient_phone || point.contact_person || point.contact_phone || '';
    }

    return point.contact_person || point.contact_phone || '';
}

export function setRoutePointCombinedContact(point, value) {
    const normalizedValue = String(value ?? '').trim();
    point.contact_person = normalizedValue;
    point.contact_phone = '';

    if (point.type === 'loading') {
        point.sender_contact = normalizedValue;
        point.sender_phone = '';
    }

    if (point.type === 'unloading') {
        point.recipient_contact = normalizedValue;
        point.recipient_phone = '';
    }
}

export function leadRouteChainLabel(points) {
    const labels = (points ?? [])
        .map((point) => {
            const city = routePointCityValue(point) || extractCityFromAddress(point.address) || String(point.address ?? '').trim();

            return city === '' ? null : city;
        })
        .filter(Boolean);

    if (labels.length === 0) {
        return 'Укажите точки маршрута: погрузка и выгрузка.';
    }

    return labels.join(' → ');
}

export function normalizeRoutePointSequences(points) {
    return points.map((point, index) => ({
        ...point,
        sequence: index + 1,
    }));
}

export function countLeadRoutePointsByType(points, type) {
    return (points ?? []).filter((point) => point.type === type).length;
}

export function canRemoveLeadRoutePoint(points, index) {
    if (!Array.isArray(points) || points.length <= 1) {
        return false;
    }

    const point = points[index];
    if (!point) {
        return false;
    }

    const sameTypeOnLeg = points.filter(
        (candidate) => candidate.type === point.type && String(candidate.stage ?? 'leg_1') === String(point.stage ?? 'leg_1'),
    ).length;

    if (point.type === 'loading' && sameTypeOnLeg <= 1) {
        return false;
    }

    if (point.type === 'unloading' && sameTypeOnLeg <= 1) {
        return false;
    }

    return true;
}

export function addLeadRoutePoint(points, type) {
    const next = [...points];
    next.push(blankLeadRoutePoint(type, next.length + 1));

    return normalizeRoutePointSequences(next);
}

export function addLeadRoutePointAfter(points, index) {
    const point = points[index];
    if (!point) {
        return points;
    }

    const next = [...points];
    next.splice(index + 1, 0, blankLeadRoutePoint(point.type, index + 2, point.stage ?? 'leg_1'));

    return normalizeRoutePointSequences(next);
}

export function removeLeadRoutePoint(points, index) {
    if (!canRemoveLeadRoutePoint(points, index)) {
        return points;
    }

    const next = [...points];
    next.splice(index, 1);

    return normalizeRoutePointSequences(next);
}

export {
    routePointCityValue,
    setRoutePointCity,
    syncRoutePointCityFromAddress,
};
