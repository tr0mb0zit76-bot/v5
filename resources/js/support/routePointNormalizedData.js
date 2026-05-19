/**
 * Клиентская логика normalized_data.city (зеркало App\Support\RoutePointNormalizedData).
 */

export function ensureRoutePointNormalizedData(point) {
    if (!point.normalized_data || typeof point.normalized_data !== 'object') {
        point.normalized_data = {};
    }
}

export function routePointCityValue(point) {
    ensureRoutePointNormalizedData(point);

    return point.normalized_data.city ?? '';
}

export function setRoutePointCity(point, value) {
    ensureRoutePointNormalizedData(point);
    const trimmed = String(value ?? '').trim();
    point.normalized_data.city = trimmed === '' ? null : trimmed;
}

export function extractCityFromAddress(address) {
    const trimmed = String(address ?? '').trim();
    if (trimmed === '') {
        return null;
    }

    const firstSegment = trimmed.split(',')[0]?.trim() ?? '';
    if (firstSegment === '') {
        return null;
    }

    const cleaned = firstSegment.replace(/^(г\.?|город)\s+/iu, '').trim();

    return cleaned === '' ? null : cleaned;
}

/** Подставляет город из адреса, если поле города пустое. */
export function syncRoutePointCityFromAddress(point) {
    ensureRoutePointNormalizedData(point);

    if (String(point.normalized_data.city ?? '').trim() !== '') {
        return;
    }

    const extracted = extractCityFromAddress(point.address);
    if (extracted) {
        point.normalized_data.city = extracted;
    }
}
