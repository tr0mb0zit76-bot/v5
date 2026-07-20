/**
 * Self-check: rebalance траншей — предыдущие доли не сбрасываются.
 * Запуск: node tests/js/orderPaymentScheduleUi.rebalance.selfcheck.mjs
 */
import {
    blankInstallmentRow,
    rebalanceInstallmentPercents,
    equalizeInstallmentPercents,
} from '../../resources/js/support/orderPaymentScheduleUi.js';

function assert(cond, message) {
    if (!cond) {
        throw new Error(message);
    }
}

function percents(schedule) {
    return schedule.installments.map((row) => Number(row.percent));
}

function nearlyEqual(a, b, eps = 0.011) {
    return Math.abs(a - b) <= eps;
}

function sum(values) {
    return values.reduce((acc, value) => acc + value, 0);
}

const schedule = {
    installments: [
        blankInstallmentRow({ percent: 100 / 3 }),
        blankInstallmentRow({ percent: 100 / 3 }),
        blankInstallmentRow({ percent: 100 / 3 }),
    ],
};

equalizeInstallmentPercents(schedule);
assert(nearlyEqual(sum(percents(schedule)), 100), 'equalize must sum to 100');

schedule.installments[0].percent = 50;
rebalanceInstallmentPercents(schedule, 0);
assert(nearlyEqual(schedule.installments[0].percent, 50), 'first stays 50');
assert(nearlyEqual(schedule.installments[1].percent, 25), `second should be 25, got ${schedule.installments[1].percent}`);
assert(nearlyEqual(schedule.installments[2].percent, 25), `third should be 25, got ${schedule.installments[2].percent}`);

schedule.installments[1].percent = 30;
rebalanceInstallmentPercents(schedule, 1);
assert(nearlyEqual(schedule.installments[0].percent, 50), 'first must stay 50 after editing second');
assert(nearlyEqual(schedule.installments[1].percent, 30), 'second stays 30');
assert(nearlyEqual(schedule.installments[2].percent, 20), `third should absorb 20, got ${schedule.installments[2].percent}`);
assert(nearlyEqual(sum(percents(schedule)), 100), 'sum must stay 100');

console.log('orderPaymentScheduleUi.rebalance.selfcheck: ok');
