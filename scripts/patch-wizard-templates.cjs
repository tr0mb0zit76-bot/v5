const fs = require('fs');
const path = require('path');

const wizardPath = path.join(__dirname, '../resources/js/Pages/Orders/Wizard.vue');
let lines = fs.readFileSync(wizardPath, 'utf8').split(/\r?\n/);

// 1. Main tab
const mainStart = lines.findIndex((l) => l.includes("activeTab === 'main'"));
const routeStart = lines.findIndex((l) => l.includes("activeTab === 'route'"));
if (mainStart !== -1 && routeStart !== -1) {
    const mainReplacement = [
        '            <OrderWizardMainTab v-if="activeTab === \'main\'" />',
        '',
        '            <OrderWizardRouteTab v-else-if="activeTab === \'route\'" />',
    ];
    lines.splice(mainStart, routeStart - mainStart + 1, ...mainReplacement);
}

// 2. Remove leftover route body if any (between RouteTab and cargo)
const routeTabIdx = lines.findIndex((l) => l.includes('OrderWizardRouteTab'));
const cargoIdx = lines.findIndex((l) => l.includes("activeTab === 'cargo'"));
if (routeTabIdx !== -1 && cargoIdx !== -1 && cargoIdx - routeTabIdx > 2) {
    lines.splice(routeTabIdx + 1, cargoIdx - routeTabIdx - 1);
}

fs.writeFileSync(wizardPath, lines.join('\n'), 'utf8');
console.log('Template patches done, lines', lines.length);
