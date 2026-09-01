import assert from 'node:assert/strict';
import {
    blockCountsAsLoaded,
    blockInTrailer,
    calculateLayout,
    calculateMultiVehicleLayout,
    isOversizeUnit,
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

console.log('multi_vehicle_layout.test.mjs: ok');
