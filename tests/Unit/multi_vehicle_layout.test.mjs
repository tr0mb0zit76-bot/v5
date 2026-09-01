import assert from 'node:assert/strict';
import {
    blockCountsAsLoaded,
    blockInTrailer,
    calculateLayout,
    calculateMultiVehicleLayout,
    isOversizeUnit,
    pickBlockAtScenePoint,
    pickBlockNearScenePoint,
    pickTopBlockFromCandidates,
    transportAllowsOversize,
    unitFitsTransportDimensions,
} from '../../resources/js/support/loadingPlannerLayout.js';

const transport = {
    length_mm: 13600,
    width_mm: 2450,
    height_mm: 2700,
    max_payload_kg: 22000,
    allows_oversize: false,
    category: 'truck',
};

const palletItem = {
    source_key: 'pallet-a',
    name: 'Паллета EUR',
    package_type: 'pallet',
    quantity: 20,
    length_mm: 1200,
    width_mm: 800,
    height_mm: 1200,
    weight_kg: 350,
    can_rotate: true,
    stackable: false,
    color: '#8b5cf6',
};

const single = calculateLayout(transport, [{ ...palletItem, quantity: 10 }]);
assert.equal(single.fits, true, '10 pallets should fit in one truck');

const overflow = calculateLayout(transport, [{ ...palletItem, quantity: 40 }]);
assert.equal(overflow.fits, false, '40 pallets should not fit in one truck');

const multi = calculateMultiVehicleLayout(transport, [{ ...palletItem, quantity: 40 }]);
assert.equal(multi.fits, true, '40 pallets should fit across multiple trucks');
assert.ok(multi.truckCount >= 2, 'should require at least two trucks');
assert.equal(multi.placedUnits, 40, 'all units should be placed');
assert.equal(multi.unplacedUnits, 0, 'no unplaced units');

for (const truck of multi.trucks) {
    assert.equal(
        truck.blocks.every((block) => block.in_trailer),
        true,
        `truck ${truck.truckIndex} must not spill to staging`,
    );
    assert.equal(
        new Set(truck.blocks.map((block) => block.key)).size,
        truck.blocks.length,
        `truck ${truck.truckIndex} must not duplicate keys within one layout`,
    );
}

const oversized = calculateMultiVehicleLayout(transport, [{
    source_key: 'transformer',
    name: 'Трансформатор',
    quantity: 1,
    length_mm: 15000,
    width_mm: 3000,
    height_mm: 4000,
    weight_kg: 12000,
    can_rotate: false,
    stackable: false,
    color: '#f00',
}]);

assert.equal(oversized.fits, false, 'oversized cargo on strict trailer should not fit');
assert.equal(oversized.truckCount, 0, 'no trucks when every unit is oversized');
assert.ok(oversized.warnings.some((warning) => warning.includes('габарит')), 'oversized warning expected');
assert.equal(oversized.unplacedUnits, 1);

const mixed = calculateMultiVehicleLayout(transport, [
    { ...palletItem, quantity: 10 },
    {
        source_key: 'cabin',
        name: 'Кабина',
        quantity: 1,
        length_mm: 4940,
        width_mm: 1700,
        height_mm: 4000,
        weight_kg: 4300,
        can_rotate: true,
        stackable: false,
        color: '#0f0',
    },
]);
assert.ok(mixed.truckCount >= 1, 'placeable cargo still forms trucks');
assert.equal(mixed.placedUnits, 10, 'pallets placed despite cabin oversize');
assert.equal(mixed.unplacedUnits, 1, 'cabin remains unplaced');
assert.ok(mixed.oversizedItems.includes('Кабина'), 'cabin listed as oversized');
assert.ok(mixed.warnings.some((warning) => warning.includes('Кабина')), 'cabin warning kept');

assert.equal(isOversizeUnit({
    length_mm: 15000,
    width_mm: 3000,
    height_mm: 4000,
    can_rotate: false,
}, transport), true);
assert.equal(unitFitsTransportDimensions({
    length_mm: 15000,
    width_mm: 3000,
    height_mm: 4000,
    can_rotate: false,
}, transport), false);

const platformTransport = {
    ...transport,
    allows_oversize: true,
    category: 'platform',
};

assert.equal(transportAllowsOversize(platformTransport), true);

const oversizeItem = {
    source_key: 'beam',
    name: 'Балка',
    quantity: 1,
    length_mm: 15000,
    width_mm: 1200,
    height_mm: 800,
    weight_kg: 5000,
    can_rotate: false,
    stackable: false,
    color: '#f59e0b',
};

assert.equal(unitFitsTransportDimensions(oversizeItem, platformTransport), true, 'platform allows oversize units');
assert.equal(isOversizeUnit(oversizeItem, platformTransport), true, 'beam is oversize relative to deck');

const oversizeLayout = calculateLayout(platformTransport, [oversizeItem]);
assert.equal(oversizeLayout.placedUnits, 1, 'oversize item should be placed on platform');
assert.equal(oversizeLayout.placedInTrailer, 1, 'oversize item should count as loaded');
assert.equal(oversizeLayout.blocks.length, 1, 'one block on scene');
assert.equal(oversizeLayout.blocks[0].in_trailer, true, 'oversize block counts as loaded');
assert.equal(oversizeLayout.blocks[0].is_oversize, true, 'block marked as oversize');
assert.equal(blockInTrailer(oversizeLayout.blocks[0], platformTransport), false, 'footprint extends beyond trailer');
assert.equal(blockCountsAsLoaded(oversizeLayout.blocks[0], platformTransport), true, 'still counts as loaded');
assert.ok(
    oversizeLayout.warnings.some((warning) => warning.includes('негабарит')),
    'informational oversize warning expected',
);
assert.equal(
    oversizeLayout.blocks.some((block) => !block.in_trailer),
    false,
    'oversize cargo must not fall back to staging',
);

const platformTrailer = {
    length_mm: 15000,
    width_mm: 3000,
    height_mm: 3500,
    max_payload_kg: 42000,
    allows_oversize: true,
    category: 'platform',
};

const compactParts = {
    source_key: 'frame-part',
    name: 'Рама',
    package_type: 'other',
    quantity: 6,
    length_mm: 3000,
    width_mm: 1400,
    height_mm: 1200,
    weight_kg: 2500,
    can_rotate: true,
    stackable: false,
    color: '#2563eb',
};

const compactMulti = calculateMultiVehicleLayout(platformTrailer, [compactParts]);
assert.equal(compactMulti.fits, true, 'six compact parts should fit');
assert.equal(compactMulti.truckCount, 1, 'consolidation should keep a single truck for compact parts');
assert.equal(compactMulti.placedUnits, 6, 'all compact parts placed');

const beamLayout = calculateLayout(platformTrailer, [{
    source_key: 'long-beam',
    name: 'Балка',
    quantity: 1,
    length_mm: 6000,
    width_mm: 1400,
    height_mm: 800,
    weight_kg: 2500,
    can_rotate: true,
    stackable: false,
    color: '#111827',
}]);
assert.equal(beamLayout.blocks.length, 1, 'beam should be placed');
assert.ok(
    beamLayout.blocks[0].length >= beamLayout.blocks[0].width,
    'rotatable beam should prefer length along trailer',
);

const floorBlock = { key: 'a-0', x: 0, y: 0, length: 2000, width: 1000, z: 0, height: 1000, unit_height: 1000 };
const topBlock = { key: 'b-0', x: 500, y: 200, length: 1000, width: 800, z: 1000, height: 800, unit_height: 800 };
assert.equal(pickBlockAtScenePoint(600, 400, [floorBlock, topBlock])?.key, 'b-0', 'pick top of stack');
assert.equal(pickBlockAtScenePoint(100, 100, [floorBlock, topBlock])?.key, 'a-0', 'pick floor when not under stack');

const nearBlock = { key: 'c-0', x: 5000, y: 500, length: 2000, width: 1000, z: 0, height: 1200, unit_height: 1200 };
assert.equal(
    pickBlockNearScenePoint(5200, 700, [nearBlock], 400)?.key,
    'c-0',
    'near pick should find nearby footprint',
);
assert.equal(
    pickTopBlockFromCandidates([floorBlock, topBlock])?.key,
    'b-0',
    'top block from candidates',
);

const heightOversizeLayout = calculateLayout(platformTrailer, [{
    source_key: 'cabin',
    name: 'Кабина',
    quantity: 1,
    length_mm: 4940,
    width_mm: 1700,
    height_mm: 4000,
    weight_kg: 4300,
    can_rotate: true,
    stackable: false,
    color: '#22c55e',
}]);
assert.equal(heightOversizeLayout.blocks.length, 1, 'height-oversize cabin should be placed');
assert.equal(heightOversizeLayout.blocks[0].in_trailer, true, 'height-oversize cabin counts as in trailer');
assert.equal(heightOversizeLayout.blocks[0].is_oversize, true, 'height-oversize cabin marked oversize');
assert.equal(blockInTrailer(heightOversizeLayout.blocks[0], platformTrailer), true, 'footprint fits trailer');
assert.ok(
    heightOversizeLayout.warnings.some((warning) => warning.includes('высоту кузова')),
    'height oversize warning expected',
);

console.log('multi_vehicle_layout.test.mjs: ok');
