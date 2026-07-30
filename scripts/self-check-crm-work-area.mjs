/**
 * Runnable self-check for crmWorkArea (no Vite aliases).
 * Usage: node scripts/self-check-crm-work-area.mjs
 */
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const modPath = pathToFileURL(path.join(root, 'resources/js/support/crmWorkArea.js')).href;
const { selfCheckCrmWorkArea, bindCrmWorkAreaVisit } = await import(modPath);

bindCrmWorkAreaVisit(() => {});
const result = selfCheckCrmWorkArea();

if (!result.ok) {
    console.error('crmWorkArea self-check FAILED', result.errors);
    process.exit(1);
}

console.log('crmWorkArea self-check OK');
