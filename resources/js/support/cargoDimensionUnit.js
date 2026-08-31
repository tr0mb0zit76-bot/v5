/**
 * Единицы габаритов груза (зеркало App\Support\CargoDimensionUnit).
 */

export const CARGO_DIMENSION_UNITS = [
    { value: 'm', label: 'м' },
    { value: 'cm', label: 'см' },
    { value: 'mm', label: 'мм' },
];

export function normalizeCargoDimensionUnit(unit) {
    const normalized = String(unit ?? '').trim().toLowerCase();

    return CARGO_DIMENSION_UNITS.some((row) => row.value === normalized) ? normalized : 'm';
}

export function cargoDimensionToMeters(value, unit) {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return null;
    }

    switch (normalizeCargoDimensionUnit(unit)) {
        case 'cm':
            return value / 100;
        case 'mm':
            return value / 1000;
        default:
            return value;
    }
}

export function cargoDimensionUnitLabel(unit) {
    const normalized = normalizeCargoDimensionUnit(unit);
    const found = CARGO_DIMENSION_UNITS.find((row) => row.value === normalized);

    return found?.label ?? 'м';
}
