import assert from 'node:assert/strict';

/**
 * Mirrors useOrderWizardCargoTab volume rules:
 * - full L×W×H → auto volume
 * - incomplete / empty dims → keep manual volume (do not wipe)
 */
function parseLocaleDecimal(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }
    const normalized = String(value).trim().replace(',', '.');
    if (normalized === '') {
        return null;
    }
    const n = Number(normalized);

    return Number.isFinite(n) ? n : null;
}

function cargoComputedVolumeM3(item) {
    const l = parseLocaleDecimal(item.length_m);
    const w = parseLocaleDecimal(item.width_m);
    const h = parseLocaleDecimal(item.height_m);

    if (l === null || w === null || h === null || l <= 0 || w <= 0 || h <= 0) {
        return null;
    }

    return l * w * h;
}

function syncVolume(item) {
    const computed = cargoComputedVolumeM3(item);
    if (computed !== null) {
        item.volume_m3 = Math.round(computed * 1000) / 1000;
        item.volume_from_dimensions = true;
    } else {
        item.volume_from_dimensions = false;
    }

    return item;
}

const manual = syncVolume({ length_m: null, width_m: null, height_m: null, volume_m3: 42 });
assert.equal(manual.volume_m3, 42);
assert.equal(manual.volume_from_dimensions, false);

const partial = syncVolume({ length_m: 1.2, width_m: null, height_m: null, volume_m3: 18.5 });
assert.equal(partial.volume_m3, 18.5);
assert.equal(partial.volume_from_dimensions, false);

const fromDims = syncVolume({ length_m: 2, width_m: 1, height_m: 1.5, volume_m3: null });
assert.equal(fromDims.volume_m3, 3);
assert.equal(fromDims.volume_from_dimensions, true);

console.log('orderWizardCargoVolume.selfcheck: ok');
