import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const apkPath = resolve(process.cwd(), process.argv[2] || 'public/downloads/traklo.apk');
const bytes = readFileSync(apkPath);
const text = bytes.toString('latin1');

const markers = {
    zipMagic: bytes.subarray(0, 4).toString('hex'),
    hasEndOfCentralDirectory: text.includes('PK\u0005\u0006'),
    hasAndroidManifest: text.includes('AndroidManifest.xml'),
    hasMetaInf: text.includes('META-INF/'),
    hasApkSigBlock: text.includes('APK Sig Block 42'),
    hasV1SignatureFile: /META-INF\/[^/]+\.(RSA|DSA|EC)/.test(text),
};

console.log(JSON.stringify({
    path: apkPath,
    size_mb: Number((bytes.length / 1024 / 1024).toFixed(2)),
    ...markers,
}, null, 2));

if (markers.zipMagic !== '504b0304' || ! markers.hasEndOfCentralDirectory || ! markers.hasAndroidManifest) {
    process.exit(1);
}
