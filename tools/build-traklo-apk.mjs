import { copyFileSync, existsSync, mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const args = new Set(process.argv.slice(2));
const release = args.has('--release');
const variant = release ? 'release' : 'debug';
const task = release ? 'assembleRelease' : 'assembleDebug';
const npx = 'npx';
const gradle = process.platform === 'win32' ? 'gradlew.bat' : './gradlew';
const capacitorWebDir = resolve(root, 'public/capacitor');
const buildGradlePath = resolve(root, 'android/app/build.gradle');
const buildGradle = readFileSync(buildGradlePath, 'utf8');

const versionCode = Number(buildGradle.match(/versionCode\s+(\d+)/)?.[1] ?? 0);
const versionName = buildGradle.match(/versionName\s+["']([^"']+)["']/)?.[1] ?? '';

if (!versionCode || !versionName) {
    throw new Error(`Cannot read versionCode/versionName from ${buildGradlePath}`);
}

function run(command, commandArgs, cwd) {
    const result = spawnSync(command, commandArgs, {
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

function runWindowsBatch(command, commandArgs, cwd) {
    if (process.platform !== 'win32' || !command.endsWith('.bat')) {
        run(command, commandArgs, cwd);

        return;
    }

    run('cmd.exe', ['/d', '/s', '/c', command, ...commandArgs], cwd);
}

function readProperties(path) {
    if (!existsSync(path)) {
        return {};
    }

    return Object.fromEntries(
        readFileSync(path, 'utf8')
            .split(/\r?\n/)
            .map((line) => line.trim())
            .filter((line) => line !== '' && !line.startsWith('#'))
            .map((line) => {
                const separator = line.indexOf('=');

                if (separator === -1) {
                    return [line, ''];
                }

                return [line.slice(0, separator).trim(), line.slice(separator + 1).trim()];
            }),
    );
}

function androidSdkDir() {
    const envPath = process.env.ANDROID_HOME || process.env.ANDROID_SDK_ROOT;

    if (envPath) {
        return envPath;
    }

    const localProperties = readProperties(resolve(root, 'android/local.properties'));
    const sdkDir = localProperties['sdk.dir'];

    if (!sdkDir) {
        throw new Error('Cannot resolve Android SDK path. Set ANDROID_HOME or android/local.properties sdk.dir.');
    }

    return sdkDir.replaceAll('\\\\', '\\').replaceAll('\\:', ':');
}

function latestApkSigner() {
    const buildToolsDir = resolve(androidSdkDir(), 'build-tools');
    const versions = readdirSync(buildToolsDir, { withFileTypes: true })
        .filter((entry) => entry.isDirectory())
        .map((entry) => entry.name)
        .sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
    const latest = versions.at(-1);

    if (!latest) {
        throw new Error(`No Android build-tools found in ${buildToolsDir}`);
    }

    const signer = resolve(buildToolsDir, latest, process.platform === 'win32' ? 'apksigner.bat' : 'apksigner');

    if (!existsSync(signer)) {
        throw new Error(`apksigner not found: ${signer}`);
    }

    return signer;
}

function releaseSigningProperties() {
    const properties = readProperties(resolve(root, 'android/traklo-release.properties'));

    return {
        storeFile: properties.storeFile || process.env.TRAKLO_RELEASE_STORE_FILE,
        storePassword: properties.storePassword || process.env.TRAKLO_RELEASE_STORE_PASSWORD,
        keyAlias: properties.keyAlias || process.env.TRAKLO_RELEASE_KEY_ALIAS,
        keyPassword: properties.keyPassword || process.env.TRAKLO_RELEASE_KEY_PASSWORD,
    };
}

const syncCommand = process.platform === 'win32' ? 'cmd.exe' : npx;
const syncArgs = process.platform === 'win32' ? ['/d', '/s', '/c', npx, 'cap', 'sync', 'android'] : ['cap', 'sync', 'android'];
mkdirSync(capacitorWebDir, { recursive: true });
run(syncCommand, syncArgs, root);

const buildCommand = process.platform === 'win32' ? 'cmd.exe' : gradle;
const buildArgs = process.platform === 'win32' ? ['/d', '/s', '/c', gradle, task] : [task];
run(buildCommand, buildArgs, resolve(root, 'android'));

const outputDir = resolve(root, 'public/downloads');
mkdirSync(outputDir, { recursive: true });

const apkOutputDir = resolve(root, `android/app/build/outputs/apk/${variant}`);
const sourceApk = resolve(apkOutputDir, `app-${variant}.apk`);
const targetApk = resolve(outputDir, 'traklo.apk');

if (release) {
    const apkFiles = existsSync(apkOutputDir)
        ? readdirSync(apkOutputDir).filter((file) => file.endsWith('.apk'))
        : [];
    const unsignedApk = apkFiles.includes('app-release-unsigned.apk')
        ? resolve(apkOutputDir, 'app-release-unsigned.apk')
        : resolve(apkOutputDir, apkFiles[0] || '');
    const signing = releaseSigningProperties();

    if (!existsSync(unsignedApk)) {
        throw new Error(`Cannot find release APK to sign in ${apkOutputDir}. Found: ${apkFiles.join(', ') || 'none'}`);
    }

    for (const key of ['storeFile', 'storePassword', 'keyAlias', 'keyPassword']) {
        if (!signing[key]) {
            throw new Error(`Missing release signing value: ${key}`);
        }
    }

    const apksigner = latestApkSigner();
    runWindowsBatch(apksigner, [
        'sign',
        '--ks',
        resolve(root, 'android', signing.storeFile),
        '--ks-key-alias',
        signing.keyAlias,
        '--ks-pass',
        `pass:${signing.storePassword}`,
        '--key-pass',
        `pass:${signing.keyPassword}`,
        '--out',
        targetApk,
        unsignedApk,
    ], root);
    runWindowsBatch(apksigner, ['verify', '--verbose', targetApk], root);
} else if (existsSync(sourceApk)) {
    copyFileSync(sourceApk, targetApk);
} else {
    throw new Error(`Cannot find debug APK: ${sourceApk}`);
}

const apkUrl = process.env.TRAKLO_APK_URL || '/downloads/traklo.apk';
const changelog = process.env.TRAKLO_CHANGELOG || `Traklo ${versionName}: обновление приложения.`;

const iconSource = resolve(root, 'resources/icon.png');
const iconTarget = resolve(outputDir, 'traklo-icon.png');
if (existsSync(iconSource)) {
    copyFileSync(iconSource, iconTarget);
}

const manifest = {
    app_name: 'Traklo',
    latest_version_code: versionCode,
    latest_version_name: versionName,
    min_supported_version_code: Number(process.env.TRAKLO_MIN_SUPPORTED_VERSION_CODE || 1),
    apk_url: apkUrl,
    changelog,
};

writeFileSync(
    resolve(outputDir, 'traklo-update.json'),
    `${JSON.stringify(manifest, null, 2)}\n`,
    'utf8',
);

console.log(`Traklo ${versionName} (${versionCode}) ${variant} APK: ${targetApk}`);
console.log(`Update manifest: ${resolve(outputDir, 'traklo-update.json')}`);
