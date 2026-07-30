/**
 * ponytail: self-check for bulk confirm threshold.
 * Ceiling: no browser confirm() in CI — only threshold logic.
 */
import {
  GRID_BULK_CONFIRM_THRESHOLD,
  confirmLargeBulkGridAction,
} from './gridBulkConfirm.js';

const assert = (cond, msg) => {
  if (!cond) {
    throw new Error(msg);
  }
};

assert(GRID_BULK_CONFIRM_THRESHOLD === 20, 'threshold must stay 20');
assert(confirmLargeBulkGridAction(1, 'тест') === true, 'small batch must pass');
assert(confirmLargeBulkGridAction(20, 'тест') === true, 'at threshold must pass');
assert(confirmLargeBulkGridAction(0, 'тест') === true, 'zero must pass');

console.log('gridBulkConfirm self-check OK');
