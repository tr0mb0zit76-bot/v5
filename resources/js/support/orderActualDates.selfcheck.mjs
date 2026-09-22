/**
 * Runnable check: node resources/js/support/orderActualDates.selfcheck.mjs
 */
import {
    clampActualDateToToday,
    isActualLoadingAfterUnloading,
    isPlausibleActualIsoDate,
} from './orderActualDates.js';

function assert(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

const loading = '2026-08-28';

assert(!isPlausibleActualIsoDate('0002-08-28'), 'year 0002 is not plausible');
assert(!isPlausibleActualIsoDate('0020-08-28'), 'year 0020 is not plausible');
assert(!isPlausibleActualIsoDate('0202-08-28'), 'year 0202 is not plausible');
assert(isPlausibleActualIsoDate('2026-08-28'), '2026 is plausible');

assert(
    !isActualLoadingAfterUnloading(loading, '0002-09-21'),
    'typing year 0002 must not fail loading/unloading order',
);
assert(
    !isActualLoadingAfterUnloading(loading, '0020-09-21'),
    'typing year 0020 must not fail loading/unloading order',
);
assert(
    !isActualLoadingAfterUnloading(loading, '0202-09-21'),
    'typing year 0202 must not fail loading/unloading order',
);
assert(
    isActualLoadingAfterUnloading(loading, '2026-08-01'),
    'real earlier unloading must fail',
);
assert(
    !isActualLoadingAfterUnloading(loading, '2026-09-01'),
    'later unloading is ok',
);
assert(
    !isActualLoadingAfterUnloading(loading, '2026-08-28'),
    'same day is ok',
);

assert(clampActualDateToToday('0002-09-21') === '0002-09-21', 'do not clamp mid-typing year');

console.log('orderActualDates.selfcheck: ok');
