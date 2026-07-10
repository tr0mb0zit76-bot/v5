const fs = require('fs');

const content = fs.readFileSync('resources/js/Pages/Orders/Wizard.vue', 'utf8');
const m = content.match(/<template>([\s\S]*)<\/template>\s*<script/s);
const template = m[1];
const lines = template.split(/\r?\n/);

const stack = [];
const tagRe = /<\/?div[\s>]/g;

for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    let match;
    tagRe.lastIndex = 0;
    while ((match = tagRe.exec(line)) !== null) {
        const isClose = line[match.index + 1] === '/';
        if (isClose) {
            if (stack.length === 0) {
                console.log('EXTRA CLOSE at template line', i + 2, ':', line.trim().slice(0, 80));
            } else {
                stack.pop();
            }
        } else {
            stack.push({ line: i + 2, text: line.trim().slice(0, 100) });
        }
    }
}

console.log('Unclosed divs:', stack.length);
stack.slice(-5).forEach((s) => console.log('  line', s.line, s.text));
