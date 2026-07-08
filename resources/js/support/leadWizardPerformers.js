import { blankPerformer, normalizePerformer } from '@/support/orderPerformers.js';
import { stageLabel, stageMatches } from '@/support/orderPrintFormSlots.js';
import { toStageKey } from '@/support/orderStageKey.js';
import {
    blankLeadRoutePoint,
    normalizeRoutePointSequences,
} from '@/support/leadWizardRoute.js';

export function normalizeLeadPerformer(performer = {}) {
    const normalized = normalizePerformer(performer);
    const estimatedCost = performer?.estimated_cost;
    let parsedEstimatedCost = null;

    if (estimatedCost !== null && estimatedCost !== undefined && estimatedCost !== '') {
        const numeric = Number(estimatedCost);
        if (Number.isFinite(numeric)) {
            parsedEstimatedCost = numeric;
        }
    }

    return {
        stage: toStageKey(normalized.stage) || 'leg_1',
        contractor_id: normalized.contractor_id,
        contractor_name: normalized.contractor_name,
        estimated_cost: parsedEstimatedCost,
    };
}

export function defaultLeadPerformers() {
    return [normalizeLeadPerformer(blankPerformer('leg_1'))];
}

export function normalizeLeadPerformers(performers) {
    if (!Array.isArray(performers) || performers.length === 0) {
        return defaultLeadPerformers();
    }

    return performers.map((performer) => normalizeLeadPerformer(performer));
}

export function nextLeadLegStage(performers) {
    const maxIndex = (Array.isArray(performers) ? performers : []).reduce((acc, performer) => {
        const match = String(performer?.stage ?? '').match(/^leg_(\d+)$/i);
        if (!match) {
            return acc;
        }

        return Math.max(acc, Number.parseInt(match[1], 10));
    }, 0);

    return `leg_${maxIndex + 1}`;
}

export function syncLeadRoutePointsFromPerformers(routePoints, performers) {
    const performerStages = (Array.isArray(performers) ? performers : defaultLeadPerformers())
        .map((performer) => toStageKey(performer.stage) || 'leg_1');

    if (performerStages.length === 0) {
        return [];
    }

    const existingPoints = Array.isArray(routePoints)
        ? routePoints.map((point, index) => ({
            ...blankLeadRoutePoint(point.type ?? 'loading', Number(point.sequence ?? (index + 1)), toStageKey(point.stage ?? performerStages[0]) || performerStages[0]),
            ...point,
            stage: toStageKey(point.stage ?? performerStages[0]) || performerStages[0],
        }))
        : [];

    const nextPoints = [];

    performerStages.forEach((stage) => {
        const stagePoints = existingPoints.filter((point) => stageMatches(point.stage, stage));
        const normalizedStagePoints = stagePoints.map((point) => ({
            ...point,
            stage,
        }));

        if (!normalizedStagePoints.some((point) => point.type === 'loading')) {
            normalizedStagePoints.unshift(blankLeadRoutePoint('loading', 0, stage));
        }

        if (!normalizedStagePoints.some((point) => point.type === 'unloading')) {
            normalizedStagePoints.push(blankLeadRoutePoint('unloading', 0, stage));
        }

        nextPoints.push(...normalizedStagePoints);
    });

    return normalizeRoutePointSequences(nextPoints);
}

export function addLeadPerformer(performers, routePoints) {
    const stage = nextLeadLegStage(performers);
    const nextPerformers = [
        ...normalizeLeadPerformers(performers),
        normalizeLeadPerformer(blankPerformer(stage)),
    ];

    return {
        performers: nextPerformers,
        routePoints: syncLeadRoutePointsFromPerformers(routePoints, nextPerformers),
    };
}

export function removeLeadPerformerAt(performers, routePoints, index) {
    const normalizedPerformers = normalizeLeadPerformers(performers);

    if (normalizedPerformers.length <= 1 || index < 0 || index >= normalizedPerformers.length) {
        return {
            performers: normalizedPerformers,
            routePoints: syncLeadRoutePointsFromPerformers(routePoints, normalizedPerformers),
        };
    }

    const removedStage = normalizedPerformers[index].stage;
    const nextPerformers = normalizedPerformers.filter((_, performerIndex) => performerIndex !== index);
    const filteredRoutePoints = (Array.isArray(routePoints) ? routePoints : [])
        .filter((point) => !stageMatches(point.stage, removedStage));

    return {
        performers: nextPerformers,
        routePoints: syncLeadRoutePointsFromPerformers(filteredRoutePoints, nextPerformers),
    };
}

export function addLeadRoutePointForLeg(routePoints, stage, type) {
    const normalizedStage = toStageKey(stage) || 'leg_1';
    const points = Array.isArray(routePoints) ? [...routePoints] : [];
    const stagePoints = points
        .map((point, index) => ({ point, index }))
        .filter(({ point }) => stageMatches(point.stage, normalizedStage));

    let insertAt = points.length;

    if (stagePoints.length > 0) {
        insertAt = stagePoints[stagePoints.length - 1].index + 1;
    }

    points.splice(insertAt, 0, blankLeadRoutePoint(type, 0, normalizedStage));

    return normalizeRoutePointSequences(points);
}

export function routePointsWithIndicesForLeg(routePoints, stage) {
    const result = [];

    (Array.isArray(routePoints) ? routePoints : []).forEach((point, globalIndex) => {
        if (stageMatches(point.stage, stage)) {
            result.push({ point, globalIndex });
        }
    });

    return result.sort(
        (left, right) => Number(left.point.sequence ?? 0) - Number(right.point.sequence ?? 0),
    );
}

export { stageLabel, stageMatches };
