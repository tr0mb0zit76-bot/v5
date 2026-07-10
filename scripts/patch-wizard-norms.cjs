const fs = require('fs');
const path = require('path');

const p = path.join(__dirname, '../resources/js/Pages/Orders/Wizard.vue');
let lines = fs.readFileSync(p, 'utf8').split(/\r?\n/);

const replacement = `            <OrderWizardNormsPenaltiesTab
                v-else-if="activeTab === 'norms_penalties'"
                v-model:client-norms-penalties="form.financial_term.client_norms_penalties"
                v-model:carrier-norms-by-leg="form.financial_term.carrier_norms_by_leg"
                :currency-options="currencyOptions"
                :is-order-form-editable="isOrderFormEditable"
                :validation-messages="normsPenaltiesTabValidationMessages"
                :stage-label="stageLabel"
                @sync-carrier-norms="syncCarrierNormsByLegFromPerformers"
            />`.split('\n');

lines.splice(818, 220, ...replacement);
fs.writeFileSync(p, lines.join('\n'), 'utf8');
console.log('replaced norms tab, lines now', lines.length);
