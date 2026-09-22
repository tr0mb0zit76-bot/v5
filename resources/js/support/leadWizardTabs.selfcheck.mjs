/**
 * Runnable check: node resources/js/support/leadWizardTabs.selfcheck.mjs
 */
import {
    isTransportIntakeLeadWizard,
    leadWizardCardProfile,
} from './leadWizardTabs.js';

function assert(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

assert(isTransportIntakeLeadWizard('transport-intake') === true, 'canon transport-intake');
assert(isTransportIntakeLeadWizard('ot-zaprosa-do-zakaza') === true, 'prod renamed slug');
assert(isTransportIntakeLeadWizard('contract-signing') === false, 'hide on contract');
assert(isTransportIntakeLeadWizard('client-acquaintance') === false, 'hide on acquaintance');
assert(isTransportIntakeLeadWizard(null) === false, 'no slug');
assert(leadWizardCardProfile('ot-zaprosa-do-zakaza') === 'default', 'renamed uses default tabs');

console.log('leadWizardTabs.selfcheck: ok');
