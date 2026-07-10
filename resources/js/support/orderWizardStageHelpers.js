export function stageLabel(stage) {
    const match = String(stage ?? '').match(/^leg_(\d+)$/);

    if (match) {
        return `Плечо ${match[1]}`;
    }

    return String(stage ?? '');
}

export function toStageKey(label) {
    const match = String(label ?? '').match(/^Плечо (\d+)$/);

    if (match) {
        return `leg_${match[1]}`;
    }

    return String(label ?? '');
}

export function stageMatches(left, right) {
    return toStageKey(left) === toStageKey(right);
}
