/**
 * Self-check: outgoing UPD must not fill closing expand.
 * Run: node scripts/check-order-document-outgoing-dedupe.mjs
 */
import assert from 'node:assert/strict';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const { expandClosingRowsForEdo } = await import(
    pathToFileURL(path.join(root, 'resources/js/support/orderDocumentClosingEdoRows.js')).href
);

const outgoingUpd = {
    id: 42,
    type: 'upd',
    party: 'customer',
    direction: 'outgoing',
    status: 'signed',
    original_name: 'upd-out.pdf',
    uploaded_file_preview_url: '/preview/42',
};

const closingPlaceholder = {
    id: null,
    is_placeholder: true,
    type: 'upd',
    party: 'customer',
    slot_kind: 'customer_closing',
    slot_key: 'customer-all',
    contractor_id: null,
    requirement_label: 'Закрывающий документ заказчику',
};

const expanded = expandClosingRowsForEdo([closingPlaceholder], [outgoingUpd], []);
const filledWithOutgoing = expanded.filter((row) => Number(row.id) === 42);

assert.equal(
    filledWithOutgoing.length,
    0,
    'outgoing UPD must not bind into closing checklist expand',
);
assert.ok(
    expanded.some((row) => row.is_placeholder && row.slot_kind === 'customer_closing'),
    'closing slot stays empty placeholder',
);

// Dedupe guard (same as orderDocumentRegistryRows.dedupeRegistryRowsByDocumentId)
function dedupe(rows) {
    const seen = new Set();
    return rows.filter((row) => {
        if (!row || row.is_placeholder || row.id == null || row.id === '') {
            return true;
        }
        const id = Number(row.id);
        if (!Number.isFinite(id) || id <= 0) {
            return true;
        }
        if (seen.has(id)) {
            return false;
        }
        seen.add(id);
        return true;
    });
}

const duplicateUi = [
    { id: 42, type: 'upd', direction: 'outgoing', is_placeholder: false },
    { id: 42, type: 'upd', slot_kind: 'customer_closing', is_placeholder: false },
];
assert.equal(dedupe(duplicateUi).length, 1);

console.log('ok: outgoing UPD closing expand skip');
