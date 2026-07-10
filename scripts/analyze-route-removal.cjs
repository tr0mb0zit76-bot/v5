const { execSync } = require('child_process');
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

const toRemove = lines.slice(routeTabIdx + 1, cargoIdx);
console.log('lines to remove:', toRemove.length);
const opens = (toRemove.join('\n').match(/<div[\s>]/g) || []).length;
const closes = (toRemove.join('\n').match(/<\/div>/g) || []).length;
console.log('div opens:', opens, 'closes:', closes, 'diff:', opens - closes);
console.log('\nfirst 3 lines to remove:');
console.log(toRemove.slice(0, 3).join('\n'));
console.log('\nlast 5 lines to remove:');
console.log(toRemove.slice(-5).join('\n'));

lines.splice(routeTabIdx + 1, cargoIdx - routeTabIdx - 1);

const templateStart = lines.findIndex((l) => l.trim() === '<template>');
const templateEnd = lines.findIndex((l) => l.trim() === '</template>');
const template = lines.slice(templateStart, templateEnd + 1).join('\n');
const tOpens = (template.match(/<div[\s>]/g) || []).length;
const tCloses = (template.match(/<\/div>/g) || []).length;
console.log('\nafter both splices template div diff:', tOpens - tCloses);
