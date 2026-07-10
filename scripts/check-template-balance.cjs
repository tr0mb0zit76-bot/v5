const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

let lines = execSync('git show HEAD:resources/js/Pages/Orders/Wizard.vue', {
    cwd: path.join(__dirname, '..'),
}).toString().split(/\r?\n/);

const mainStart = lines.findIndex((l) => l.includes("activeTab === 'main'"));
const routeStart = lines.findIndex((l) => l.includes("activeTab === 'route'"));
lines.splice(mainStart, routeStart - mainStart + 1,
    "            <OrderWizardMainTab v-if=\"activeTab === 'main'\" />",
    '',
    "            <OrderWizardRouteTab v-else-if=\"activeTab === 'route'\" />",
);

const routeTabIdx = lines.findIndex((l) => l.includes('OrderWizardRouteTab'));
const cargoIdx = lines.findIndex((l) => l.includes("activeTab === 'cargo'"));
if (cargoIdx - routeTabIdx > 2) {
    lines.splice(routeTabIdx + 1, cargoIdx - routeTabIdx - 1);
}

const template = lines.slice(lines.findIndex((l) => l.trim() === '<template>'), lines.findIndex((l) => l.trim() === '</template>') + 1).join('\n');
const opens = (template.match(/<div[\s>]/g) || []).length;
const closes = (template.match(/<\/div>/g) || []).length;
console.log('after template patch only:', opens - closes);
