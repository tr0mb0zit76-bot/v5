const fs = require('fs');
const { execSync } = require('child_process');

function extractTemplate(content) {
    const m = content.match(/<template>([\s\S]*)<\/template>\s*<script/s);
    return m ? m[1] : null;
}

function divDiff(t) {
    const opens = (t.match(/<div[\s>]/g) || []).length;
    const closes = (t.match(/<\/div>/g) || []).length;
    return { opens, closes, diff: opens - closes };
}

const current = fs.readFileSync('resources/js/Pages/Orders/Wizard.vue', 'utf8');
const git = execSync('git show HEAD:resources/js/Pages/Orders/Wizard.vue', { cwd: __dirname + '/..' }).toString();

console.log('git:', divDiff(extractTemplate(git)));
console.log('current:', divDiff(extractTemplate(current)));

// Simulate correct template patch
let lines = git.split(/\r?\n/);
const mainStart = lines.findIndex((l) => l.includes("activeTab === 'main'"));
const routeStart = lines.findIndex((l) => l.includes("activeTab === 'route'"));
lines.splice(mainStart, routeStart - mainStart + 1,
    "            <OrderWizardMainTab v-if=\"activeTab === 'main'\" />",
    '',
    "            <OrderWizardRouteTab v-else-if=\"activeTab === 'route'\" />",
);
const routeTabIdx = lines.findIndex((l) => l.includes('OrderWizardRouteTab'));
const cargoIdx = lines.findIndex((l) => l.includes("activeTab === 'cargo'"));
lines.splice(routeTabIdx + 1, cargoIdx - routeTabIdx - 1);

// norms patch - find norms block and replace
const normsStart = lines.findIndex((l) => l.includes("activeTab === 'norms_penalties'"));
const mailStart = lines.findIndex((l) => l.includes("activeTab === 'mail'"));
if (normsStart >= 0 && mailStart > normsStart) {
    const normsReplacement = `            <OrderWizardNormsPenaltiesTab
                v-else-if="activeTab === 'norms_penalties'"
                v-model:client-norms-penalties="form.financial_term.client_norms_penalties"
                v-model:carrier-norms-by-leg="form.financial_term.carrier_norms_by_leg"
                :currency-options="currencyOptions"
                :is-order-form-editable="isOrderFormEditable"
                :validation-messages="normsPenaltiesTabValidationMessages"
                :stage-label="stageLabel"
                @sync-carrier-norms="syncCarrierNormsByLegFromPerformers"
            />`;
    lines.splice(normsStart, mailStart - normsStart, normsReplacement);
}

console.log('simulated patch:', divDiff(extractTemplate(lines.join('\n'))));
