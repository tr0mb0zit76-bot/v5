const { execSync } = require('child_process');
const gitContent = execSync('git show HEAD:resources/js/Pages/Orders/Wizard.vue', { cwd: require('path').join(__dirname, '..') }).toString();
const lines = gitContent.split(/\r?\n/);
const end = lines.findIndex((l) => l.includes("activeTab === 'norms_penalties'"));
console.log(lines.slice(end - 10, end + 1).join('\n'));
