const fs = require('fs');
const { execSync } = require('child_process');

function extractTemplate(content) {
    const m = content.match(/<template>([\s\S]*)<\/template>\s*<script/s);
    return m ? m[1] : null;
}

function buildSimulated() {
    let lines = execSync('git show HEAD:resources/js/Pages/Orders/Wizard.vue', { cwd: __dirname + '/..' }).toString().split(/\r?\n/);
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
    const normsStart = lines.findIndex((l) => l.includes("activeTab === 'norms_penalties'"));
    const mailStart = lines.findIndex((l) => l.includes("activeTab === 'mail'"));
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
    return extractTemplate(lines.join('\n')).split(/\r?\n/);
}

const sim = buildSimulated();
const cur = extractTemplate(fs.readFileSync('resources/js/Pages/Orders/Wizard.vue', 'utf8')).split(/\r?\n/);

console.log('sim lines', sim.length, 'cur lines', cur.length);

for (let i = 0; i < Math.max(sim.length, cur.length); i++) {
    const a = sim[i] ?? '<<<MISSING>>>';
    const b = cur[i] ?? '<<<MISSING>>>';
    if (a !== b) {
        console.log('\nFirst diff at line', i + 2);
        console.log('SIM:', a);
        console.log('CUR:', b);
        for (let j = Math.max(0, i - 3); j < i + 8; j++) {
            const mark = j === i ? '>>>' : '   ';
            console.log(`${mark} ${j + 2} CUR: ${(cur[j] ?? '').slice(0, 100)}`);
        }
        break;
    }
}
