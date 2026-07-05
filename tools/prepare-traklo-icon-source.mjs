import { existsSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const resourcesDir = resolve(root, 'resources');
const foregroundSource = resolve(root, 'android/app/src/main/res/mipmap-xxxhdpi/ic_launcher_foreground.png');
const legacySource = resolve(root, 'android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png');
const iconBackground = '#0F172A';
const targetSize = 1024;

if (!existsSync(foregroundSource)) {
    throw new Error(`Foreground source not found: ${foregroundSource}`);
}

mkdirSync(resourcesDir, { recursive: true });

const foregroundBuffer = await sharp(foregroundSource)
    .resize(targetSize, targetSize, {
        fit: 'contain',
        background: { r: 0, g: 0, b: 0, alpha: 0 },
    })
    .png()
    .toBuffer();

await sharp(foregroundBuffer).toFile(resolve(resourcesDir, 'icon-foreground.png'));

const compositeIcon = await sharp({
    create: {
        width: targetSize,
        height: targetSize,
        channels: 4,
        background: iconBackground,
    },
})
    .composite([{ input: foregroundBuffer, gravity: 'center' }])
    .png()
    .toBuffer();

await sharp(compositeIcon).toFile(resolve(resourcesDir, 'icon.png'));

if (existsSync(legacySource)) {
    await sharp(legacySource)
        .resize(targetSize, targetSize, {
            fit: 'contain',
            background: iconBackground,
        })
        .png()
        .toFile(resolve(resourcesDir, 'icon-only.png'));
}

console.log(`Prepared Traklo icon sources in ${resourcesDir}`);
console.log('- resources/icon.png (easy mode, full composite)');
console.log('- resources/icon-foreground.png (adaptive foreground)');
console.log('- resources/icon-only.png (legacy preview, optional)');
