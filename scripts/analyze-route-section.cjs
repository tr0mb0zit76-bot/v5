const { execSync } = require('child_process');
const path = require('path');

const lines = execSync('git show HEAD:resources/js/Pages/Orders/Wizard.vue', {
    cwd: path.join(__dirname, '..'),
}).toString().split(/\r?\n/);

const routeStart = lines.findIndex((l) => l.includes("activeTab === 'route'"));
const cargoIdx = lines.findIndex((l) => l.includes("activeTab === 'cargo'"));

console.log('routeStart', routeStart + 1, lines[routeStart]);
console.log('cargoIdx', cargoIdx + 1, lines[cargoIdx]);
console.log('lines between', cargoIdx - routeStart);

// Count div balance in route section only
const routeSection = lines.slice(routeStart, cargoIdx).join('\n');
const opens = (routeSection.match(/<div[\s>]/g) || []).length;
const closes = (routeSection.match(/<\/div>/g) || []).length;
console.log('route section div opens:', opens, 'closes:', closes, 'diff:', opens - closes);

// Show last 15 lines before cargo
console.log('\n--- last 15 lines before cargo ---');
console.log(lines.slice(cargoIdx - 15, cargoIdx).join('\n'));

// Show first 5 lines of route
console.log('\n--- first 5 lines of route ---');
console.log(lines.slice(routeStart, routeStart + 5).join('\n'));
