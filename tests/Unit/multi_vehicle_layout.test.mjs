import assert from 'node:assert/strict';
import {
    calculateMultiVehicleLayout,
    calculateLayout,
    unitFitsTransportDimensions,
} from '../../resources/js/support/loadingPlannerLayout.js';

const transport = {
    length_mm: 13600,
    width_mm: 2450,
    height_mm: 2700,
    max_payload_kg: 22000,
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

assert.equal(oversized.fits, false, 'oversized cargo should not fit');
assert.ok(oversized.warnings.some((warning) => warning.includes('габарит')), 'oversized warning expected');
assert.equal(unitFitsTransportDimensions({
    length_mm: 15000,
    width_mm: 3000,
    height_mm: 4000,
    can_rotate: false,
}, transport), false);

console.log('multi_vehicle_layout.test.mjs: ok');
