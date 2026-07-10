const { execSync } = require('child_process');
const path = require('path');

const lines = execSync('git show HEAD:resources/js/Pages/Orders/Wizard.vue', {
    cwd: path.join(__dirname, '..'),
}).toString().split(/\r?\n/);

const mainStart = lines.findIndex((l) => l.includes("activeTab === 'main'"));
const routeStart = lines.findIndex((l) => l.includes("activeTab === 'route'"));

const mainSection = lines.slice(mainStart, routeStart + 1).join('\n');
const opens = (mainSection.match(/<div[\s>]/g) || []).length;
const closes = (mainSection.match(/<\/div>/g) || []).length;
console.log('main+routeOpen section:', opens, closes, 'diff:', opens - closes);

const mainOnly = lines.slice(mainStart, routeStart).join('\n');
const mo = (mainOnly.match(/<div[\s>]/g) || []).length;
const mc = (mainOnly.match(/<\/div>/g) || []).length;
console.log('main only:', mo, mc, 'diff:', mo - mc);
