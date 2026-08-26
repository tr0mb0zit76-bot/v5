/**
 * Self-check: paper TN ↔ ETRN checklist alternative.
 * Run: node scripts/check-paper-or-etrn-checklist.mjs
 */
function applyPaperOrEtrnTransportAlternatives(checklist) {
    const items = (Array.isArray(checklist) ? checklist : []).map((item) => ({ ...item }));
    const waybill = items.find((item) => item?.key === 'waybill');
    const etrn = items.find((item) => item?.key === 'etrn');

    if (!waybill || !etrn) {
        return items;
    }

    if (waybill.completed && !etrn.completed) {
        etrn.completed = true;
        etrn.fulfilled_by_alternative = 'waybill';
    }

    if (etrn.completed && !waybill.completed) {
        waybill.completed = true;
        waybill.fulfilled_by_alternative = 'etrn';
    }

    return items;
}

function assert(cond, msg) {
    if (!cond) {
        console.error('FAIL:', msg);
        process.exit(1);
    }
}

const withPaper = applyPaperOrEtrnTransportAlternatives([
    { key: 'waybill', completed: true },
    { key: 'etrn', completed: false },
]);
assert(withPaper.find((i) => i.key === 'etrn')?.completed === true, 'paper should complete etrn');
assert(withPaper.find((i) => i.key === 'etrn')?.fulfilled_by_alternative === 'waybill', 'alt=waybill');

const withEtrn = applyPaperOrEtrnTransportAlternatives([
    { key: 'waybill', completed: false },
    { key: 'etrn', completed: true },
]);
assert(withEtrn.find((i) => i.key === 'waybill')?.completed === true, 'etrn should complete waybill');

console.log('OK paper↔etrn checklist alternative');
