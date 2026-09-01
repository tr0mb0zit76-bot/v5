/** Палитра для автоматической раскраски позиций груза на сцене и в списках. */
export const CARGO_ITEM_COLOR_PALETTE = [
    '#8b5cf6',
    '#22c55e',
    '#f97316',
    '#06b6d4',
    '#f43f5e',
    '#eab308',
    '#a78bfa',
    '#14b8a6',
    '#fb7185',
    '#3b82f6',
    '#84cc16',
    '#d946ef',
    '#0ea5e9',
    '#f59e0b',
    '#10b981',
    '#6366f1',
];

export function cargoItemColorForIndex(index) {
    const palette = CARGO_ITEM_COLOR_PALETTE;

    if (palette.length === 0) {
        return '#60a5fa';
    }

    const normalized = Number(index) || 0;

    return palette[((normalized % palette.length) + palette.length) % palette.length];
}

export function flatCargoItemIndex(groups, groupIndex, itemIndex) {
    let index = 0;

    for (let groupPointer = 0; groupPointer < groupIndex; groupPointer += 1) {
        index += (groups[groupPointer]?.items ?? []).length;
    }

    return index + itemIndex;
}
