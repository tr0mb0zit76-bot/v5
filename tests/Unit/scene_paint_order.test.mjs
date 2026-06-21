import {
    buildHeightRulerTicks,
    buildLengthRulerTicks,
    buildWidthRulerTicks,
    calculateLayout,
    footprintPositionAfterRotation,
    sortBlocksForScenePaint,
} from '../../resources/js/support/loadingPlannerLayout.js';

const transport = { length_mm: 13600, width_mm: 2450, height_mm: 2700, max_payload_kg: 20000 };
const items = [{
    source_key: 'a',
    name: 'A',
    quantity: 2,
    length_mm: 1200,
    width_mm: 800,
    height_mm: 1200,
    weight_kg: 500,
    stackable: true,
    max_stack: 2,
    can_rotate: false,
    color: '#f00',
}];
const base = { 'a-0': { x: 0, y: 0, z: 0 }, 'a-1': { x: 0, y: 0, z: 1200 } };
const layout = calculateLayout(transport, items, {}, { freezeBase: true, basePlacements: base });
const sorted = sortBlocksForScenePaint(layout.blocks);

if (sorted.length !== 2) {
    throw new Error('expected 2 blocks');
}

if (sorted[0].key !== 'a-0' || sorted[1].key !== 'a-1') {
    throw new Error('lower tier must render before upper tier');
}

const tightStack = calculateLayout(transport, items, {}, {
    freezeBase: true,
    basePlacements: base,
    placementGapMm: 0,
});
const upper = tightStack.blocks.find((block) => block.key === 'a-1');
const lower = tightStack.blocks.find((block) => block.key === 'a-0');

if (Number(upper?.z) !== Number(lower?.unit_height)) {
    throw new Error(`tight vertical stack expected z=${lower?.unit_height}, got z=${upper?.z}`);
}

const lengthTicks = buildLengthRulerTicks(13600);
if (lengthTicks.map((tick) => tick.label).join(',') !== '0,3,6,9,12,13.6') {
    throw new Error(`unexpected length ruler ticks: ${lengthTicks.map((tick) => tick.label).join(',')}`);
}

const widthTicks = buildWidthRulerTicks(2450);
if (widthTicks.map((tick) => tick.label).join(',') !== '0,1,2.45') {
    throw new Error(`unexpected width ruler ticks: ${widthTicks.map((tick) => tick.label).join(',')}`);
}

const heightTicks = buildHeightRulerTicks(2700);
if (heightTicks.map((tick) => tick.label).join(',') !== '0,1,2,2.7') {
    throw new Error(`unexpected height ruler ticks: ${heightTicks.map((tick) => tick.label).join(',')}`);
}

const rotated = footprintPositionAfterRotation(100, 200, 1200, 800, 800, 1200);
if (rotated.x !== 300 || rotated.y !== 0) {
    throw new Error(`rotation must preserve center: got ${rotated.x},${rotated.y}`);
}
