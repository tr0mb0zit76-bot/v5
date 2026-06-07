/**
 * Capture Sales Book screenshots via Playwright.
 * Run: node scripts/_playwright/capture.mjs
 */
import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.join(__dirname, '..', '..');
const baseUrl = process.env.CRM_BASE_URL ?? 'http://crm.aa.local';
const email = process.env.CRM_EMAIL ?? 'cursor@cursor.ru';
const password = process.env.CRM_PASSWORD ?? '4xS-kNB-cwu-V9Y';
const outDir = path.join(projectRoot, 'storage', 'app', 'sales-book-screenshots');

await mkdir(outDir, { recursive: true });

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    locale: 'ru-RU',
});
const page = await context.newPage();

async function shot(name) {
    const file = path.join(outDir, name);
    await page.screenshot({ path: file, fullPage: false });
    console.log(`saved ${file}`);
}

console.log('login…');
await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
await page.waitForSelector('#email', { timeout: 60000 });
await page.fill('#email', email);
await page.fill('#password', password);
await page.getByRole('button', { name: 'Войти' }).click();
await page.waitForURL(/\/dashboard/, { timeout: 60000 });

console.log('documents registry…');
await page.goto(`${baseUrl}/documents`, { waitUntil: 'domcontentloaded' });
await page.waitForSelector('text=Реестр документов', { timeout: 30000 });
await shot('01-documents-registry.png');

console.log('add document modal…');
await page.getByRole('button', { name: 'Добавить документ' }).first().click();
await page.waitForSelector('text=Новый документ', { timeout: 15000 });
await page.waitForTimeout(500);
await shot('02-documents-add-modal.png');
await page.keyboard.press('Escape');

console.log('order documents tab…');
await page.goto(`${baseUrl}/orders/4/edit?tab=documents`, { waitUntil: 'domcontentloaded' });
await page.waitForSelector('text=Печатные формы', { timeout: 60000 });
await page.waitForTimeout(800);
await shot('03-order-documents-tab.png');

console.log('sales book article…');
await page.goto(`${baseUrl}/sales-assistant/book?article_id=10`, { waitUntil: 'domcontentloaded' });
await page.waitForSelector('text=Руководство по CRM', { timeout: 60000 }).catch(() => page.waitForSelector('text=Документы', { timeout: 30000 }));
await page.waitForTimeout(800);
await shot('04-sales-book-documents.png');

await browser.close();
console.log('done');
