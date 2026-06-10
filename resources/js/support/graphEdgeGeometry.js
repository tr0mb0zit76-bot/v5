/**
 * Геометрия связей на канвасе: выбор стороны по относительному положению блоков.
 *
 * @typedef {'top' | 'right' | 'bottom' | 'left'} GraphSide
 * @typedef {{ x: number, y: number, width: number, height: number, centerX?: number, centerY?: number }} GraphBounds
 */

/**
 * @param {{ x: number, y: number }} fromCenter
 * @param {{ x: number, y: number }} toCenter
 * @returns {{ sourceSide: GraphSide, targetSide: GraphSide }}
 */
export function resolveConnectionSides(fromCenter, toCenter) {
    const dx = toCenter.x - fromCenter.x;
    const dy = toCenter.y - fromCenter.y;

    if (Math.abs(dy) >= Math.abs(dx)) {
        if (dy >= 0) {
            return { sourceSide: 'bottom', targetSide: 'top' };
        }

        return { sourceSide: 'top', targetSide: 'bottom' };
    }

    if (dx >= 0) {
        return { sourceSide: 'right', targetSide: 'left' };
    }

    return { sourceSide: 'left', targetSide: 'right' };
}

/**
 * @param {GraphBounds} bounds
 * @returns {{ x: number, y: number }}
 */
export function boundsCenter(bounds) {
    return {
        x: bounds.centerX ?? bounds.x + bounds.width / 2,
        y: bounds.centerY ?? bounds.y + bounds.height / 2,
    };
}

/**
 * @param {GraphBounds} bounds
 * @param {GraphSide} side
 * @returns {{ x: number, y: number }}
 */
export function portPoint(bounds, side) {
    const center = boundsCenter(bounds);

    return ({
        top: { x: center.x, y: bounds.y },
        right: { x: bounds.x + bounds.width, y: center.y },
        bottom: { x: center.x, y: bounds.y + bounds.height },
        left: { x: bounds.x, y: center.y },
    })[side];
}

/**
 * @param {{ x: number, y: number }} start
 * @param {GraphSide} startSide
 * @param {{ x: number, y: number }} end
 * @param {GraphSide} endSide
 * @param {number} [bend=52]
 * @returns {string}
 */
export function bezierPathBetween(start, startSide, end, endSide, bend = 52) {
    const cp1 = { ...start };
    const cp2 = { ...end };

    switch (startSide) {
        case 'top':
            cp1.y -= bend;
            break;
        case 'bottom':
            cp1.y += bend;
            break;
        case 'left':
            cp1.x -= bend;
            break;
        case 'right':
            cp1.x += bend;
            break;
        default:
            break;
    }

    switch (endSide) {
        case 'top':
            cp2.y -= bend;
            break;
        case 'bottom':
            cp2.y += bend;
            break;
        case 'left':
            cp2.x -= bend;
            break;
        case 'right':
            cp2.x += bend;
            break;
        default:
            break;
    }

    return `M ${start.x} ${start.y} C ${cp1.x} ${cp1.y}, ${cp2.x} ${cp2.y}, ${end.x} ${end.y}`;
}

/**
 * @param {GraphBounds} fromBounds
 * @param {GraphBounds} toBounds
 * @param {number} [bend]
 * @returns {{ path: string, labelX: number, labelY: number, sourceSide: GraphSide, targetSide: GraphSide }}
 */
export function edgeGeometryBetweenNodes(fromBounds, toBounds, bend = 52) {
    const { sourceSide, targetSide } = resolveConnectionSides(
        boundsCenter(fromBounds),
        boundsCenter(toBounds),
    );
    const start = portPoint(fromBounds, sourceSide);
    const end = portPoint(toBounds, targetSide);

    return {
        sourceSide,
        targetSide,
        path: bezierPathBetween(start, sourceSide, end, targetSide, bend),
        labelX: (start.x + end.x) / 2,
        labelY: (start.y + end.y) / 2,
    };
}

/**
 * @param {{ x: number, y: number }} fromCenter
 * @param {{ x: number, y: number }} pointer
 * @returns {GraphSide}
 */
export function sideTowardPoint(fromCenter, pointer) {
    const dx = pointer.x - fromCenter.x;
    const dy = pointer.y - fromCenter.y;

    if (Math.abs(dy) >= Math.abs(dx)) {
        return dy >= 0 ? 'bottom' : 'top';
    }

    return dx >= 0 ? 'right' : 'left';
}

/**
 * @param {{ position: { x: number, y: number } }} sourceNode
 * @param {{ position: { x: number, y: number } }} targetNode
 * @param {{ width?: number, height?: number }} [size]
 * @returns {{ sourceHandle: string, targetHandle: string }}
 */
export function vueFlowHandleIds(sourceNode, targetNode, size = { width: 200, height: 52 }) {
    const fromBounds = {
        x: sourceNode.position.x,
        y: sourceNode.position.y,
        width: size.width,
        height: size.height,
    };
    const toBounds = {
        x: targetNode.position.x,
        y: targetNode.position.y,
        width: size.width,
        height: size.height,
    };
    const { sourceSide, targetSide } = resolveConnectionSides(
        boundsCenter(fromBounds),
        boundsCenter(toBounds),
    );

    return {
        sourceHandle: `source-${sourceSide}`,
        targetHandle: `target-${targetSide}`,
    };
}
