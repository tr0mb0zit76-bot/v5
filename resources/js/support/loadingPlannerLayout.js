export const STAGING_PADDING_MM = {
    left: 4200,
    right: 1800,
    top: 900,
    bottom: 900,
};

export function buildSceneBounds(transport) {
    const left = STAGING_PADDING_MM.left;
    const right = STAGING_PADDING_MM.right;
    const top = STAGING_PADDING_MM.top;
    const bottom = STAGING_PADDING_MM.bottom;

    return {
        min_x: -left,
        min_y: -top,
        max_x: transport.length_mm + right,
        max_y: transport.width_mm + bottom,
        total_length_mm: transport.length_mm + left + right,
        total_width_mm: transport.width_mm + top + bottom,
        trailer_length_mm: transport.length_mm,
        trailer_width_mm: transport.width_mm,
    };
}

export function blockInTrailer(block, transport) {
    return block.x >= 0
        && block.y >= 0
        && block.x + block.length <= transport.length_mm
        && block.y + block.width <= transport.width_mm;
}

export function blocksOverlapXY(a, b) {
    return a.x < b.x + b.length
        && a.x + a.length > b.x
        && a.y < b.y + b.width
        && a.y + a.width > b.y;
}

export function blocksOverlap(a, b) {
    return blocksOverlapXY(a, b);
}

export function blocksOverlap3D(a, b) {
    if (!blocksOverlapXY(a, b)) {
        return false;
    }

    const aBottom = Number(a.z || 0);
    const bBottom = Number(b.z || 0);
    const aTop = aBottom + Number(a.height || a.unit_height || 0);
    const bTop = bBottom + Number(b.height || b.unit_height || 0);

    return aBottom < bTop && aTop > bBottom;
}

export function blockFitsInBounds(bounds, block) {
    return block.x >= bounds.min_x
        && block.y >= bounds.min_y
        && block.x + block.length <= bounds.max_x
        && block.y + block.width <= bounds.max_y;
}

export function placementRotationZ(placement) {
    if (placement.rotation_z !== undefined && placement.rotation_z !== null) {
        return ((Number(placement.rotation_z) % 360) + 360) % 360;
    }

    return placement.rotated ? 90 : 0;
}

export function placementRotationY(placement) {
    if (placement.rotation_y !== undefined && placement.rotation_y !== null) {
        return ((Number(placement.rotation_y) % 360) + 360) % 360;
    }

    const legacyTilt = Number(placement.tilted ?? 0);
    if (legacyTilt === 0) {
        return 0;
    }

    return legacyTilt > 0 ? 90 : 270;
}

export function footprintForItem(item, rotationZ) {
    const swap = rotationZ % 180 === 90;

    return {
        length: swap ? Number(item.width_mm) : Number(item.length_mm),
        width: swap ? Number(item.length_mm) : Number(item.width_mm),
    };
}

export function placementFromBlock(block) {
    return {
        x: Number(block.x),
        y: Number(block.y),
        z: Number(block.z || 0),
        rotation_z: block.rotation_z ?? (block.rotated ? 90 : 0),
        rotation_y: block.rotation_y ?? 0,
        rotated: Boolean(block.rotated),
        tilted: block.rotation_y ?? 0,
        locked: Boolean(block.locked),
    };
}

export function snapshotPlacementsFromBlocks(blocks) {
    const placements = {};
    for (const block of blocks) {
        placements[block.key] = placementFromBlock(block);
    }

    return placements;
}

export function verticalStackGapMm(placementGapMm = null) {
    return placementGapMm === 0 ? 0 : Number(placementGapMm ?? 0);
}

export function formatRulerMeters(mm) {
    const meters = mm / 1000;
    if (Math.abs(meters - Math.round(meters)) < 0.001) {
        return String(Math.round(meters));
    }

    return meters.toFixed(2).replace(/\.?0+$/, '');
}

export function buildLengthRulerTicks(lengthMm) {
    const ticks = [{ mm: 0, label: '0' }];
    const stepMm = 3000;

    for (let mm = stepMm; mm < lengthMm; mm += stepMm) {
        ticks.push({ mm, label: formatRulerMeters(mm) });
    }

    if (ticks[ticks.length - 1].mm !== lengthMm) {
        ticks.push({ mm: lengthMm, label: formatRulerMeters(lengthMm) });
    }

    return ticks;
}

export function buildWidthRulerTicks(widthMm) {
    const ticks = [{ mm: 0, label: '0' }];

    if (widthMm > 1000) {
        ticks.push({ mm: 1000, label: '1' });
    }

    if (ticks[ticks.length - 1].mm !== widthMm) {
        ticks.push({ mm: widthMm, label: formatRulerMeters(widthMm) });
    }

    return ticks;
}

export function buildHeightRulerTicks(heightMm) {
    const ticks = [{ mm: 0, label: '0' }];

    for (let mm = 1000; mm < heightMm; mm += 1000) {
        ticks.push({ mm, label: formatRulerMeters(mm) });
    }

    if (ticks[ticks.length - 1].mm !== heightMm) {
        ticks.push({ mm: heightMm, label: formatRulerMeters(heightMm) });
    }

    return ticks;
}

/**
 * Координаты левого нижнего угла footprint после поворота вокруг центра (x/y — угол, не центр).
 *
 * @return {{ x: number, y: number }}
 */
export function footprintPositionAfterRotation(x, y, length, width, nextLength, nextWidth) {
    const centerX = Number(x) + Number(length) / 2;
    const centerY = Number(y) + Number(width) / 2;

    return {
        x: centerX - Number(nextLength) / 2,
        y: centerY - Number(nextWidth) / 2,
    };
}

export function placementGridStep(length, width, placementGapMm = null) {
    if (placementGapMm === 0) {
        return 1;
    }

    if (placementGapMm !== null && placementGapMm > 0) {
        return placementGapMm;
    }

    return Math.max(50, Math.min(200, Math.round(Math.min(length, width) / 2)));
}

export function zoneLimits(bounds, length, width, zone) {
    return {
        xMin: zone === 'trailer' ? 0 : bounds.min_x,
        xMax: zone === 'trailer' ? bounds.trailer_length_mm - length : bounds.max_x - length,
        yMin: zone === 'trailer' ? 0 : bounds.min_y,
        yMax: zone === 'trailer' ? bounds.trailer_width_mm - width : bounds.max_y - width,
    };
}

export function buildPlacementCoordinateSets(bounds, placedBlocks, length, width, zone, placementGapMm = null) {
    const { xMin, xMax, yMin, yMax } = zoneLimits(bounds, length, width, zone);
    const xs = new Set();
    const ys = new Set();

    if (placementGapMm === 0) {
        xs.add(xMin);
        ys.add(yMin);

        for (const block of placedBlocks) {
            xs.add(Number(block.x));
            xs.add(Number(block.x) + Number(block.length));
            ys.add(Number(block.y));
            ys.add(Number(block.y) + Number(block.width));
        }
    } else {
        const step = placementGridStep(length, width, placementGapMm);

        for (let x = xMin; x <= xMax; x += step) {
            xs.add(x);
        }

        for (let y = yMin; y <= yMax; y += step) {
            ys.add(y);
        }
    }

    return {
        xs: [...xs].filter((x) => x >= xMin && x <= xMax).sort((left, right) => left - right),
        ys: [...ys].filter((y) => y >= yMin && y <= yMax).sort((left, right) => left - right),
        limits: { xMin, xMax, yMin, yMax },
    };
}

export function findNextAutoPosition(bounds, placedBlocks, length, width, zone = 'all', unitHeight = 1, placementGapMm = null) {
    const { xs, ys, limits } = buildPlacementCoordinateSets(bounds, placedBlocks, length, width, zone, placementGapMm);

    for (const y of ys) {
        for (const x of xs) {
            const candidate = { x, y, length, width, z: 0, height: unitHeight, unit_height: unitHeight };
            if (!placedBlocks.some((block) => blocksOverlap3D(candidate, block))) {
                return { x, y };
            }
        }
    }

    return null;
}

export function stackCountInColumn(item, placedBlocks, candidate) {
    return placedBlocks.filter((block) => (
        block.source_key === item.source_key && blocksOverlapXY(candidate, block)
    )).length;
}

export function maxStackForItem(item, transport) {
    const unitHeight = Number(item.height_mm || 1);

    return Math.max(1, Math.min(Number(item.max_stack || 1), Math.floor(transport.height_mm / unitHeight)));
}

export function placementAllowedForItem(item, placedBlocks, candidate, transport) {
    const unitHeight = Number(item.height_mm || 1);
    const z = Number(candidate.z || 0);

    if (z + unitHeight > transport.height_mm) {
        return false;
    }

    if (placedBlocks.some((block) => blocksOverlap3D(candidate, block))) {
        return false;
    }

    if (!item.stackable && z > 0) {
        return false;
    }

    if (item.stackable && stackCountInColumn(item, placedBlocks, candidate) >= maxStackForItem(item, transport)) {
        return false;
    }

    return true;
}

/**
 * Верхняя точка укладки при ручном переносе в кузов (кладём на стопку, а не под неё).
 */
export function findTopSupportedZForBlock(footprint, others, transport, options = {}) {
    const unitHeight = Number(footprint.unit_height ?? footprint.height ?? 1);
    const stackGapMm = verticalStackGapMm(options.placementGapMm ?? null);
    let stackTop = 0;

    for (const other of others) {
        if (!blocksOverlapXY(footprint, other)) {
            continue;
        }

        stackTop = Math.max(
            stackTop,
            Number(other.z || 0) + Number(other.unit_height || other.height || 0) + stackGapMm,
        );
    }

    if (stackTop + unitHeight > transport.height_mm) {
        return findSupportedZForBlock(footprint, others, transport, options);
    }

    const candidate = {
        x: Number(footprint.x),
        y: Number(footprint.y),
        length: Number(footprint.length),
        width: Number(footprint.width),
        z: stackTop,
        height: unitHeight,
        unit_height: unitHeight,
    };

    if (others.some((other) => blocksOverlap3D(candidate, other))) {
        return findSupportedZForBlock(footprint, others, transport, options);
    }

    return stackTop;
}

export function scoreAutoPlacement(candidate, zone) {
    return (zone === 'trailer' ? 0 : 1_000_000_000)
        + Number(candidate.z) * 1_000_000
        + Number(candidate.x) * 1_000
        + Number(candidate.y);
}

export function findBestAutoPlacement(bounds, placedBlocks, item, transport, options = {}) {
    const { placementGapMm = null, zones = ['trailer', 'all'] } = options;
    const unitHeight = Number(item.height_mm || 1);
    const variants = [{ rotationZ: 0, footprint: footprintForItem(item, 0) }];

    if (item.can_rotate && item.length_mm !== item.width_mm) {
        variants.push({
            rotationZ: 90,
            footprint: {
                length: Number(item.width_mm),
                width: Number(item.length_mm),
            },
        });
    }

    let best = null;
    let bestScore = Infinity;

    for (const variant of variants) {
        const { length, width } = variant.footprint;

        for (const zone of zones) {
            const { xs, ys } = buildPlacementCoordinateSets(bounds, placedBlocks, length, width, zone, placementGapMm);

            for (const y of ys) {
                for (const x of xs) {
                    const probe = {
                        x,
                        y,
                        length,
                        width,
                        unit_height: unitHeight,
                    };
                    const z = findSupportedZForBlock(probe, placedBlocks, transport, { placementGapMm });
                    const candidate = {
                        ...probe,
                        z,
                        height: unitHeight,
                    };

                    if (!placementAllowedForItem(item, placedBlocks, candidate, transport)) {
                        continue;
                    }

                    const score = scoreAutoPlacement(candidate, zone);

                    if (score < bestScore) {
                        bestScore = score;
                        best = {
                            x,
                            y,
                            z,
                            rotationZ: variant.rotationZ,
                            length,
                            width,
                            zone,
                        };
                    }
                }
            }
        }
    }

    return best;
}

export function columnKeyForBlock(block) {
    return `${block.source_key}:${block.x}:${block.y}:${block.length}:${block.width}`;
}

export function unitIndexFromKey(key) {
    const parts = key.split('-');
    const index = Number(parts[parts.length - 1]);

    return Number.isFinite(index) ? index : 0;
}

/**
 * Ключ порядка отрисовки в изометрии: дальние/нижние ярусы раньше, ближние и верхние — позже.
 */
export function sceneBlockPaintOrder(block) {
    return Number(block.x ?? 0)
        + Number(block.y ?? 0)
        + Number(block.z ?? 0) * 2;
}

export function sortBlocksForScenePaint(blocks) {
    return [...blocks].sort((left, right) => {
        const orderDiff = sceneBlockPaintOrder(left) - sceneBlockPaintOrder(right);
        if (orderDiff !== 0) {
            return orderDiff;
        }

        return unitIndexFromKey(left.key) - unitIndexFromKey(right.key);
    });
}

/**
 * Находит нижний допустимый Z для footprint с учётом уже занятых мест (пол, ярусы, разный размер).
 */
export function findSupportedZForBlock(footprint, others, transport, options = {}) {
    const x = Number(footprint.x);
    const y = Number(footprint.y);
    const length = Number(footprint.length);
    const width = Number(footprint.width);
    const unitHeight = Number(footprint.unit_height ?? footprint.height ?? 1);
    const stackGapMm = verticalStackGapMm(options.placementGapMm ?? null);
    const candidates = [0];

    for (const other of others) {
        if (!blocksOverlapXY({ x, y, length, width }, other)) {
            continue;
        }

        const top = Number(other.z || 0) + Number(other.unit_height || other.height || 0) + stackGapMm;
        if (top + unitHeight <= transport.height_mm) {
            candidates.push(top);
        }
    }

    const unique = [...new Set(candidates)].sort((left, right) => left - right);

    for (const z of unique) {
        const candidate = {
            x,
            y,
            length,
            width,
            z,
            height: unitHeight,
            unit_height: unitHeight,
        };

        if (!others.some((other) => blocksOverlap3D(candidate, other))) {
            return z;
        }
    }

    return unique[unique.length - 1] ?? 0;
}

/**
 * Один столбик: центр места попадает в footprint соседа (или наоборот), без «зацепа» соседних рядов.
 */
const LIFT_CLEARANCE_MM = 5;

export function blockHasCargoAbove(block, blocks, tolerance = LIFT_CLEARANCE_MM) {
    const blockTop = Number(block.z || 0) + Number(block.unit_height || block.height || 0);

    return blocks.some((other) => {
        if (other.key === block.key) {
            return false;
        }

        if (!blocksOverlapXY(block, other)) {
            return false;
        }

        return Number(other.z || 0) >= blockTop - tolerance;
    });
}

/** Маджонг: снять можно только место без груза сверху (в кузове). */
export function blockCanBeLifted(block, blocks, transport, tolerance = LIFT_CLEARANCE_MM) {
    if (!blockInTrailer(block, transport)) {
        return true;
    }

    return !blockHasCargoAbove(block, blocks, tolerance);
}

export function blocksShareStackColumn(a, b) {
    if (!blocksOverlapXY(a, b)) {
        return false;
    }

    const aCenterX = Number(a.x) + Number(a.length) / 2;
    const aCenterY = Number(a.y) + Number(a.width) / 2;
    const bCenterX = Number(b.x) + Number(b.length) / 2;
    const bCenterY = Number(b.y) + Number(b.width) / 2;

    const aCenterInB = aCenterX >= Number(b.x)
        && aCenterX <= Number(b.x) + Number(b.length)
        && aCenterY >= Number(b.y)
        && aCenterY <= Number(b.y) + Number(b.width);
    const bCenterInA = bCenterX >= Number(a.x)
        && bCenterX <= Number(a.x) + Number(a.length)
        && bCenterY >= Number(a.y)
        && bCenterY <= Number(a.y) + Number(a.width);

    return aCenterInB || bCenterInA;
}

/**
 * Номер яруса = позиция в столбике по Z (1 — пол), а не «все соседи с z ≤».
 */
export function stackTierForBlock(block, blocks, transport) {
    if (!blockInTrailer(block, transport)) {
        return 1;
    }

    const column = blocks
        .filter((other) => blockInTrailer(other, transport) && blocksShareStackColumn(block, other))
        .sort((left, right) => {
            const zDiff = Number(left.z) - Number(right.z);
            if (zDiff !== 0) {
                return zDiff;
            }

            return unitIndexFromKey(left.key) - unitIndexFromKey(right.key);
        });

    const index = column.findIndex((entry) => entry.key === block.key);

    return index >= 0 ? index + 1 : 1;
}

export function assignStackCounts(blocks, transport) {
    for (const block of blocks) {
        block.stack_count = stackTierForBlock(block, blocks, transport);
        block.height = block.unit_height;
    }
}

/**
 * «Осаживает» груз в кузове на опоры; убирает зависание после снятия нижнего места.
 */
export function settleTrailerBlocks(blocks, transport, options = {}) {
    const excludeKeys = options.excludeKeys ?? new Set();
    const freezeKeys = options.freezeKeys ?? new Set();
    const trailerBlocks = blocks.filter((block) => blockInTrailer(block, transport));

    for (let pass = 0; pass < trailerBlocks.length + 2; pass++) {
        let changed = false;

        for (const block of trailerBlocks) {
            if (excludeKeys.has(block.key) || freezeKeys.has(block.key)) {
                continue;
            }

            const others = blocks.filter((other) => other.key !== block.key);
            const nextZ = findSupportedZForBlock(block, others, transport, options);

            if (Number(block.z) !== nextZ) {
                block.z = nextZ;
                changed = true;
            }
        }

        if (!changed) {
            break;
        }
    }

    for (const block of blocks) {
        if (!blockInTrailer(block, transport)) {
            block.z = 0;
        }
    }

    assignStackCounts(blocks, transport);
}

/** @deprecated Используйте settleTrailerBlocks */
export function recompactTrailerStacks(blocks, transport, options = {}) {
    settleTrailerBlocks(blocks, transport, { excludeKeys: options.manualKeys ?? new Set() });
}

export function manualPlacementWarnings(transport, bounds, blocks) {
    const warnings = [];

    for (const block of blocks) {
        if (!blockFitsInBounds(bounds, block)) {
            warnings.push(`${block.name}: позиция выходит за пределы площадки.`);
        } else if (!blockInTrailer(block, transport) && block.locked) {
            warnings.push(`${block.name}: зафиксирован вне кузова — перенесите в прицеп.`);
        }
    }

    for (let i = 0; i < blocks.length; i++) {
        for (let j = i + 1; j < blocks.length; j++) {
            if (blocksOverlap3D(blocks[i], blocks[j])) {
                warnings.push(`${blocks[i].name} пересекается с ${blocks[j].name}.`);
            }
        }
    }

    return warnings;
}

export const ALIGN_GUIDE_TOLERANCE_MM = 25;

export function approxMm(left, right, tolerance = ALIGN_GUIDE_TOLERANCE_MM) {
    return Math.abs(Number(left) - Number(right)) <= tolerance;
}

/**
 * Подсказки выравнивания при перетаскивании: та же серия (source_key), совпадение граней / столбика.
 *
 * @param {{ key: string, source_key: string }} dragged
 * @param {Array<object>} blocks
 * @param {{ draggedZ?: number, toleranceMm?: number }} options
 */
export function computeSeriesAlignHints(dragged, x, y, length, width, blocks, options = {}) {
    const tolerance = options.toleranceMm ?? ALIGN_GUIDE_TOLERANCE_MM;
    const draggedZ = Number(options.draggedZ ?? 0);
    const edges = {
        x0: Number(x),
        x1: Number(x) + Number(length),
        y0: Number(y),
        y1: Number(y) + Number(width),
    };

    const result = {
        blocks: {},
        dragged: {
            stack: false,
            left: false,
            right: false,
            front: false,
            back: false,
        },
    };

    for (const block of blocks) {
        if (block.key === dragged.key || block.source_key !== dragged.source_key) {
            continue;
        }

        const other = {
            x0: Number(block.x),
            x1: Number(block.x) + Number(block.length),
            y0: Number(block.y),
            y1: Number(block.y) + Number(block.width),
        };

        const stack = approxMm(edges.x0, other.x0, tolerance) && approxMm(edges.y0, other.y0, tolerance);
        const below = Number(block.z || 0) < draggedZ;
        const left = approxMm(edges.x0, other.x0, tolerance);
        const right = approxMm(edges.x1, other.x1, tolerance);
        const front = approxMm(edges.y0, other.y0, tolerance);
        const back = approxMm(edges.y1, other.y1, tolerance);

        if (!stack && !left && !right && !front && !back) {
            continue;
        }

        result.blocks[block.key] = {
            stack,
            below,
            left,
            right,
            front,
            back,
        };

        if (stack && below) {
            result.dragged.stack = true;
        }

        if (left) {
            result.dragged.left = true;
        }

        if (right) {
            result.dragged.right = true;
        }

        if (front) {
            result.dragged.front = true;
        }

        if (back) {
            result.dragged.back = true;
        }
    }

    return result;
}

export function clientPointToSceneMm(clientX, clientY, deckRect, bounds, sceneRotationZDeg) {
    const centerX = deckRect.left + deckRect.width / 2;
    const centerY = deckRect.top + deckRect.height / 2;
    const dx = clientX - centerX;
    const dy = clientY - centerY;
    const rad = (-sceneRotationZDeg * Math.PI) / 180;
    const cos = Math.cos(rad);
    const sin = Math.sin(rad);
    const localX = dx * cos - dy * sin;
    const localY = dx * sin + dy * cos;
    const xMm = bounds.min_x + ((localX / deckRect.width) + 0.5) * bounds.total_length_mm;
    const yMm = bounds.min_y + ((localY / deckRect.height) + 0.5) * bounds.total_width_mm;

    return { x: xMm, y: yMm };
}

function buildBlockFromPlacement(item, blockKey, placement, transport) {
    const rotationZ = placementRotationZ(placement);
    const rotationY = placementRotationY(placement);
    const footprint = footprintForItem(item, rotationZ);
    const unitHeight = Number(item.height_mm || 1);
    let x = Number(placement.x || 0);
    let y = Number(placement.y || 0);
    let z = Number(placement.z ?? 0);

    const block = {
        key: blockKey,
        source_key: item.source_key,
        name: item.name,
        count: 1,
        stack_count: 1,
        color: item.color,
        x,
        y,
        z,
        length: footprint.length,
        width: footprint.width,
        height: unitHeight,
        unit_height: unitHeight,
        base_length: Number(item.length_mm),
        base_width: Number(item.width_mm),
        rotated: rotationZ % 180 === 90,
        rotation_z: rotationZ,
        rotation_y: rotationY,
        tilted: rotationY,
        locked: Boolean(placement.locked),
        manual: Boolean(placement.manual),
        in_trailer: false,
    };

    block.in_trailer = blockInTrailer(block, transport);
    if (!block.in_trailer) {
        block.z = 0;
    }

    return block;
}

function buildLayoutFromFrozenBase(transport, items, basePlacements, manualOverrides, options = {}) {
    const bounds = buildSceneBounds(transport);
    const blocks = [];
    let placedUnits = 0;
    let placedInTrailer = 0;
    let totalUnits = 0;
    let totalWeightKg = 0;
    let totalVolumeM3 = 0;
    let usedLengthMm = 0;

    for (const item of items) {
        const quantity = Math.max(0, Number(item.quantity || 0));
        totalUnits += quantity;
        totalWeightKg += quantity * Number(item.weight_kg || 0);
        totalVolumeM3 += quantity * item.length_mm * item.width_mm * item.height_mm / 1_000_000_000;

        for (let unitIndex = 0; unitIndex < quantity; unitIndex++) {
            const blockKey = `${item.source_key}-${unitIndex}`;
            const manual = manualOverrides[blockKey] ?? null;
            const base = basePlacements[blockKey] ?? null;

            if (!base && !manual) {
                continue;
            }

            const merged = {
                ...(base ?? {}),
                ...(manual ?? {}),
                manual: Boolean(manual),
            };

            const block = buildBlockFromPlacement(item, blockKey, merged, transport);
            blocks.push(block);
            placedUnits += 1;
            if (block.in_trailer) {
                placedInTrailer += 1;
                usedLengthMm = Math.max(usedLengthMm, block.x + block.length);
            }
        }
    }

    settleTrailerBlocks(blocks, transport, {
        excludeKeys: options.excludeSettleKeys ?? new Set(),
        freezeKeys: options.freezeSettleKeys ?? new Set(),
        placementGapMm: options.placementGapMm ?? null,
    });

    const warnings = manualPlacementWarnings(transport, bounds, blocks);
    const transportVolumeM3 = transport.length_mm * transport.width_mm * transport.height_mm / 1_000_000_000;
    const hasOverlap = warnings.some((warning) => warning.includes('пересекается'));

    return finalizeLayoutMetrics({
        blocks,
        bounds,
        warnings,
        fits: placedInTrailer === totalUnits && totalWeightKg <= transport.max_payload_kg && !hasOverlap,
        totalUnits,
        placedUnits,
        placedInTrailer,
        totalWeightKg,
        totalVolumeM3,
        usedLengthMm,
        transport,
        transportVolumeM3,
    });
}

function calculateAutoLayout(transport, items, options = {}) {
    const bounds = buildSceneBounds(transport);
    const blocks = [];
    const warnings = [];
    let placedUnits = 0;
    let placedInTrailer = 0;
    let totalUnits = 0;
    let totalWeightKg = 0;
    let totalVolumeM3 = 0;
    let usedLengthMm = 0;
    let overflow = false;
    const maxBlocks = 320;
    const placementGapMm = options.placementGapMm ?? null;

    for (const item of items) {
        const quantity = Math.max(0, Number(item.quantity || 0));
        totalUnits += quantity;
        totalWeightKg += quantity * Number(item.weight_kg || 0);
        totalVolumeM3 += quantity * item.length_mm * item.width_mm * item.height_mm / 1_000_000_000;

        let remaining = quantity;

        while (remaining > 0) {
            const blockKey = `${item.source_key}-${quantity - remaining}`;
            const placement = findBestAutoPlacement(bounds, blocks, item, transport, { placementGapMm });

            if (!placement) {
                overflow = true;
                warnings.push(`${item.name}: не удалось разместить ${remaining} шт.`);
                break;
            }

            const block = buildBlockFromPlacement(
                item,
                blockKey,
                {
                    x: placement.x,
                    y: placement.y,
                    z: placement.z,
                    rotation_z: placement.rotationZ,
                    rotation_y: 0,
                    manual: false,
                    locked: false,
                },
                transport,
            );

            if (blocks.length < maxBlocks) {
                blocks.push(block);
            }

            placedUnits += 1;
            if (block.in_trailer) {
                placedInTrailer += 1;
                usedLengthMm = Math.max(usedLengthMm, block.x + block.length);
            }

            remaining -= 1;
        }
    }

    settleTrailerBlocks(blocks, transport, {
        excludeKeys: options.excludeSettleKeys ?? new Set(),
        freezeKeys: options.freezeSettleKeys ?? new Set(),
    });

    if (totalWeightKg > transport.max_payload_kg) {
        warnings.push(`Перевес: ${formatKg(totalWeightKg)} при лимите ${formatKg(transport.max_payload_kg)}.`);
    }

    if (blocks.length >= maxBlocks && placedUnits < totalUnits) {
        warnings.push('В 3D-сцене показана часть мест, чтобы интерфейс не тормозил.');
    }

    warnings.push(...manualPlacementWarnings(transport, bounds, blocks));

    const transportVolumeM3 = transport.length_mm * transport.width_mm * transport.height_mm / 1_000_000_000;
    const hasOverlap = warnings.some((warning) => warning.includes('пересекается'));

    return finalizeLayoutMetrics({
        blocks,
        bounds,
        warnings,
        fits: !overflow && placedInTrailer === totalUnits && totalWeightKg <= transport.max_payload_kg && !hasOverlap,
        totalUnits,
        placedUnits,
        placedInTrailer,
        totalWeightKg,
        totalVolumeM3,
        usedLengthMm,
        transport,
        transportVolumeM3,
    });
}

function finalizeLayoutMetrics(payload) {
    const {
        blocks,
        bounds,
        warnings,
        fits,
        totalUnits,
        placedUnits,
        placedInTrailer,
        totalWeightKg,
        totalVolumeM3,
        usedLengthMm,
        transport,
        transportVolumeM3,
    } = payload;

    return {
        blocks,
        bounds,
        warnings: [...new Set(warnings)],
        fits,
        totalUnits,
        placedUnits,
        placedInTrailer,
        totalWeightKg,
        totalVolumeM3,
        ldm: usedLengthMm / 1000,
        freeLengthMm: Math.max(0, transport.length_mm - usedLengthMm),
        freeVolumeM3: Math.max(0, transportVolumeM3 - totalVolumeM3),
        usedVolumePercent: transportVolumeM3 > 0 ? Math.min(999, totalVolumeM3 / transportVolumeM3 * 100) : 0,
        usedPayloadPercent: transport.max_payload_kg > 0 ? Math.min(999, totalWeightKg / transport.max_payload_kg * 100) : 0,
    };
}

export function calculateLayout(transport, items, manualOverrides = {}, options = {}) {
    if (!transport) {
        return emptyLayout();
    }

    const {
        basePlacements = null,
        freezeBase = false,
        excludeSettleKeys = new Set(),
        freezeSettleKeys = new Set(),
        placementGapMm = null,
    } = options;

    if (freezeBase && basePlacements) {
        return buildLayoutFromFrozenBase(transport, items, basePlacements, manualOverrides, {
            excludeSettleKeys,
            freezeSettleKeys,
            placementGapMm,
        });
    }

    return calculateAutoLayout(transport, items, { excludeSettleKeys, freezeSettleKeys, placementGapMm });
}

const DEFAULT_MAX_VEHICLES = 10;

/**
 * @param {object} item
 * @param {object} transport
 */
export function unitFitsTransportDimensions(item, transport) {
    if (!transport || !item) {
        return false;
    }

    const length = Number(item.length_mm || 0);
    const width = Number(item.width_mm || 0);
    const height = Number(item.height_mm || 0);

    if (length <= 0 || width <= 0 || height <= 0) {
        return false;
    }

    const trailerLength = Number(transport.length_mm || 0);
    const trailerWidth = Number(transport.width_mm || 0);
    const trailerHeight = Number(transport.height_mm || 0);

    const footprints = item.can_rotate && length !== width
        ? [[length, width], [width, length]]
        : [[length, width]];

    return footprints.some(([footprintLength, footprintWidth]) => (
        footprintLength <= trailerLength
        && footprintWidth <= trailerWidth
        && height <= trailerHeight
    ));
}

/**
 * @param {Array<object>} items
 * @param {object} transport
 * @param {{ maxVehicles?: number, placementGapMm?: number|null }} options
 */
export function calculateMultiVehicleLayout(transport, items, options = {}) {
    if (!transport) {
        return emptyMultiVehicleLayout();
    }

    const maxVehicles = Math.max(1, Number(options.maxVehicles ?? DEFAULT_MAX_VEHICLES));
    const layoutOptions = {
        excludeSettleKeys: options.excludeSettleKeys ?? new Set(),
        freezeSettleKeys: options.freezeSettleKeys ?? new Set(),
        placementGapMm: options.placementGapMm ?? null,
    };

    const itemsBySource = new Map(items.map((item) => [item.source_key, item]));
    let remainingItems = cloneItemsForLayout(items);
    const trucks = [];
    const warnings = [];
    const oversizedItems = [];

    for (const item of remainingItems) {
        if (!unitFitsTransportDimensions(item, transport)) {
            oversizedItems.push(item.name || item.source_key);
        }
    }

    if (oversizedItems.length > 0) {
        const uniqueNames = [...new Set(oversizedItems)];

        return {
            fits: false,
            truckCount: 0,
            trucks: [],
            totalUnits: sumItemUnits(items),
            placedUnits: 0,
            unplacedUnits: sumItemUnits(items),
            warnings: [
                ...uniqueNames.map((name) => `${name}: габарит больше кузова — нужен другой тип транспорта (платформа / негабарит).`),
            ],
            oversizedItems: uniqueNames,
            usedPayloadPercent: 0,
            usedVolumePercent: 0,
        };
    }

    while (sumItemUnits(remainingItems) > 0 && trucks.length < maxVehicles) {
        const layout = calculateAutoLayout(transport, remainingItems, layoutOptions);
        const inTrailerBlocks = layout.blocks.filter((block) => block.in_trailer);
        const trimmed = trimTrailerBlocksToPayload(inTrailerBlocks, transport, itemsBySource);
        const placedKeys = new Set(trimmed.map((block) => block.key));

        if (placedKeys.size === 0) {
            warnings.push('Не удалось разместить оставшийся груз даже на дополнительной машине.');
            break;
        }

        const truckLayout = finalizeLayoutMetrics({
            blocks: [
                ...trimmed,
                ...layout.blocks.filter((block) => !block.in_trailer),
            ],
            bounds: layout.bounds,
            warnings: [...layout.warnings],
            fits: trimmed.length === layout.placedInTrailer,
            totalUnits: layout.totalUnits,
            placedUnits: trimmed.length,
            placedInTrailer: trimmed.length,
            totalWeightKg: trimmed.reduce((sum, block) => sum + blockWeightKg(block, itemsBySource), 0),
            totalVolumeM3: trimmed.reduce((sum, block) => {
                const item = itemsBySource.get(block.source_key);

                return sum + (Number(item?.length_mm || 0) * Number(item?.width_mm || 0) * Number(item?.height_mm || 0) / 1_000_000_000);
            }, 0),
            usedLengthMm: trimmed.reduce((max, block) => Math.max(max, Number(block.x) + Number(block.length)), 0),
            transport,
            transportVolumeM3: layout.bounds
                ? Number(transport.length_mm) * Number(transport.width_mm) * Number(transport.height_mm) / 1_000_000_000
                : 0,
        });

        trucks.push({
            ...truckLayout,
            truckIndex: trucks.length + 1,
            truckLabel: `Машина ${trucks.length + 1}`,
            placedKeys: [...placedKeys],
        });

        remainingItems = remainingItemsAfterPlacement(remainingItems, placedKeys);

        if (truckLayout.usedPayloadPercent > 100) {
            warnings.push(`Машина ${trucks.length}: перевес ${formatKg(truckLayout.totalWeightKg)} при лимите ${formatKg(transport.max_payload_kg)}.`);
        }
    }

    const totalUnits = sumItemUnits(items);
    const placedUnits = trucks.reduce((sum, truck) => sum + truck.placedInTrailer, 0);
    const unplacedUnits = Math.max(0, totalUnits - placedUnits);

    if (unplacedUnits > 0 && trucks.length >= maxVehicles) {
        warnings.push(`Не хватило лимита в ${maxVehicles} машин — осталось ${unplacedUnits} мест.`);
    }

    const aggregateWarnings = [...new Set([
        ...warnings,
        ...trucks.flatMap((truck) => truck.warnings),
    ])];

    return {
        fits: unplacedUnits === 0,
        truckCount: trucks.length,
        trucks,
        totalUnits,
        placedUnits,
        unplacedUnits,
        warnings: aggregateWarnings,
        oversizedItems: [],
        usedPayloadPercent: averagePercent(trucks.map((truck) => truck.usedPayloadPercent)),
        usedVolumePercent: averagePercent(trucks.map((truck) => truck.usedVolumePercent)),
    };
}

function emptyMultiVehicleLayout() {
    return {
        fits: false,
        truckCount: 0,
        trucks: [],
        totalUnits: 0,
        placedUnits: 0,
        unplacedUnits: 0,
        warnings: [],
        oversizedItems: [],
        usedPayloadPercent: 0,
        usedVolumePercent: 0,
    };
}

function cloneItemsForLayout(items) {
    return items
        .map((item) => ({
            ...item,
            quantity: Math.max(0, Number(item.quantity || 0)),
        }))
        .filter((item) => item.quantity > 0);
}

function sumItemUnits(items) {
    return items.reduce((sum, item) => sum + Math.max(0, Number(item.quantity || 0)), 0);
}

function remainingItemsAfterPlacement(items, placedKeys) {
    return items
        .map((item) => {
            const prefix = `${item.source_key}-`;
            let placed = 0;

            for (const key of placedKeys) {
                if (key.startsWith(prefix)) {
                    placed += 1;
                }
            }

            return {
                ...item,
                quantity: Math.max(0, Number(item.quantity || 0) - placed),
            };
        })
        .filter((item) => item.quantity > 0);
}

function blockWeightKg(block, itemsBySource) {
    return Number(itemsBySource.get(block.source_key)?.weight_kg ?? 0);
}

function trimTrailerBlocksToPayload(blocks, transport, itemsBySource) {
    const kept = [...blocks];
    let totalWeight = kept.reduce((sum, block) => sum + blockWeightKg(block, itemsBySource), 0);
    const payloadLimit = Number(transport.max_payload_kg || 0);

    while (payloadLimit > 0 && totalWeight > payloadLimit + 0.009 && kept.length > 0) {
        const removed = kept.pop();
        totalWeight -= blockWeightKg(removed, itemsBySource);
    }

    return kept;
}

function averagePercent(values) {
    if (values.length === 0) {
        return 0;
    }

    return values.reduce((sum, value) => sum + Number(value || 0), 0) / values.length;
}

function emptyLayout() {
    return {
        blocks: [],
        bounds: null,
        warnings: [],
        fits: false,
        totalUnits: 0,
        placedUnits: 0,
        placedInTrailer: 0,
        totalWeightKg: 0,
        totalVolumeM3: 0,
        ldm: 0,
        freeLengthMm: 0,
        freeVolumeM3: 0,
        usedVolumePercent: 0,
        usedPayloadPercent: 0,
    };
}

function formatKg(value) {
    return `${Number(value || 0).toLocaleString('ru-RU', { maximumFractionDigits: 0 })} кг`;
}
