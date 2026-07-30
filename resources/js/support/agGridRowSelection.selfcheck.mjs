/**
 * ponytail: self-check for filtered select-all defaults.
 * Ceiling: only checks helper shape, not AG Grid runtime.
 * Upgrade: browser e2e on header checkbox with active filter.
 */
import {
  agGridFilteredMultiRowSelection,
} from './agGridRowSelection.js';

const selection = agGridFilteredMultiRowSelection({ enableClickSelection: true });

const assert = (cond, msg) => {
  if (!cond) {
    throw new Error(msg);
  }
};

assert(selection.mode === 'multiRow', 'mode must be multiRow');
assert(selection.selectAll === 'filtered', 'selectAll must be filtered (not all)');
assert(selection.headerCheckbox === true, 'headerCheckbox must be true');
assert(selection.enableClickSelection === true, 'override enableClickSelection');
assert(selection.checkboxes === true, 'checkboxes must be true');

console.log('agGridRowSelection self-check OK');
