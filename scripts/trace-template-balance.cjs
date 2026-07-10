const { execSync } = require('child_process');
const path = require('path');

function countDivDiff(content) {
    const opens = (content.match(/<div[\s>]/g) || []).length;
    const closes = (content.match(/<\/div>/g) || []).length;
    return { opens, closes, diff: opens - closes };
}

const gitLines = execSync('git show HEAD:resources/js/Pages/Orders/Wizard.vue', {
    cwd: path.join(__dirname, '..'),
}).toString().split(/\r?\n/);

const tStart = gitLines.findIndex((l) => l.trim() === '<template>');
const tEnd = gitLines.findIndex((l) => l.trim() === '</template>');
const orig = gitLines.slice(tStart, tEnd + 1).join('\n');
console.log('original:', countDivDiff(orig));

let lines = [...gitLines];

const mainStart = lines.findIndex((l) => l.includes("activeTab === 'main'"));
const routeStart = lines.findIndex((l) => l.includes("activeTab === 'route'"));
console.log('mainStart', mainStart + 1, 'routeStart', routeStart + 1);

const removed1 = lines.slice(mainStart, routeStart + 1);
console.log('splice1 removes:', countDivDiff(removed1.join('\n')));

lines.splice(mainStart, routeStart - mainStart + 1,
    "            <OrderWizardMainTab v-if=\"activeTab === 'main'\" />",
    '',
    "            <OrderWizardRouteTab v-else-if=\"activeTab === 'route'\" />",
);

const t1 = lines.slice(tStart, tEnd + 1).join('\n');
console.log('after splice1:', countDivDiff(t1));

const routeTabIdx = lines.findIndex((l) => l.includes('OrderWizardRouteTab'));
const cargoIdx = lines.findIndex((l) => l.includes("activeTab === 'cargo'"));
const removed2 = lines.slice(routeTabIdx + 1, cargoIdx);
console.log('splice2 removes:', countDivDiff(removed2.join('\n')));

lines.splice(routeTabIdx + 1, cargoIdx - routeTabIdx - 1);

const tEnd2 = lines.findIndex((l) => l.trim() === '</template>');
const t2 = lines.slice(tStart, tEnd2 + 1).join('\n');
console.log('after splice2:', countDivDiff(t2));
