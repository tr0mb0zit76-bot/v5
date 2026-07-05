import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const iconBackground = '#0F172A';
const resourcesDir = resolve(root, 'resources');
const requiredSources = [
    resolve(resourcesDir, 'icon.png'),
    resolve(resourcesDir, 'icon-foreground.png'),
];

function run(command, args, cwd = root) {
    const result = spawnSync(command, args, {
        cwd,
        stdio: 'inherit',
    });

    if (result.error) {
        console.error(result.error.message);
        process.exit(1);
    }

    if (result.status !== 0) {
        process.exit(result.status ?? 1);
    }
}

const assetsCli = resolve(root, 'node_modules/@capacitor/assets/bin/capacitor-assets');

const missing = requiredSources.filter((path) => !existsSync(path));

if (missing.length > 0) {
    console.log('Icon sources missing, preparing from Android mipmap assets…');
    run(process.execPath, [resolve(root, 'tools/prepare-traklo-icon-source.mjs')]);
}

run(process.execPath, [
    assetsCli,
    'generate',
    '--android',
    '--assetPath',
    'resources',
    '--iconBackgroundColor',
    iconBackground,
    '--iconBackgroundColorDark',
    iconBackground,
]);

console.log('Android launcher icons regenerated from resources/');
