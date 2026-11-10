import { expect, test } from './support/test.js';
import { signIn } from './support/session.js';

const TITLE = 'E2E Sale Riff';
const PREMIUM = 'E2E Premium';
const EXCLUSIVE = 'E2E Exclusive';
const FRAME = Buffer.concat([Buffer.from([0xff, 0xfb, 0x90, 0x00]), Buffer.alloc(413)]);
const MP3 = Buffer.concat(Array.from({ length: 380 }, () => FRAME));
const IDENTITY = {
    name: 'Demo Artist',
    businessForm: 'EI',
    siren: '123456782',
    address: "1 rue de l'Exemple, 75000 Paris",
    email: 'demo@example.com',
    phone: '+33 1 23 45 67 89',
    publicationDirector: 'Demo Artist',
    mediatorName: 'Demo Mediator',
    mediatorUrl: 'https://mediator.example.com',
    hostName: 'Demo Host',
    hostAddress: "2 avenue de l'Hébergeur, 75000 Paris",
    hostPhone: '+33 1 98 76 54 32',
};

async function configure(page, on) {
    await page.request.put('/api/admin/settings/legal-identity', { data: IDENTITY });
    await page.request.put('/api/admin/settings/sections', { data: { lessons: false, production: false, beats: on } });
    expect((await page.request.put('/api/admin/settings/checkout', { data: { enabled: on } })).ok()).toBe(true);
}

async function newTier(page, name, price, exclusive) {
    await page.getByRole('button', { name: 'New tier' }).first().click();
    const dialog = page.getByRole('dialog');
    await dialog.locator('input[name="name-en"]').fill(name);
    await dialog.locator('input[name="name-fr"]').fill(name);
    await dialog.locator('input[name="price"]').fill(price);
    await dialog.getByLabel('MP3').check();
    if (exclusive) await dialog.getByRole('switch', { name: 'Exclusive licence' }).click();
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.locator('.rows__item', { hasText: name })).toBeVisible();
}

async function remove(page, row) {
    await row.getByRole('button', { name: 'Delete' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toHaveCount(0);
}

async function buy(visitorPage, tier) {
    await visitorPage.goto('/beats');
    await visitorPage.locator('.beat-row', { hasText: TITLE }).getByRole('button', { name: 'Buy' }).click();
    await visitorPage.locator('.licence-option', { hasText: tier }).getByRole('link', { name: 'Buy' }).click();
    await expect(visitorPage).toHaveURL(/\/checkout\/beat\/.+\?tier=/);
    await visitorPage.locator('#checkout-email').fill('beatbuyer@example.com');
    for (const kind of ['terms', 'digital-waiver', 'licence']) await visitorPage.locator(`#consent-${kind}`).check();
    await visitorPage.getByRole('button', { name: 'Order with obligation to pay' }).click();
    await expect(visitorPage).toHaveURL(/\/_fake-checkout\//);
    await visitorPage.getByRole('button', { name: 'Pay' }).click();
    await expect(visitorPage).toHaveURL(/\/checkout\/success\?order=/);
}

test.afterAll(async ({ browser, baseURL }) => {
    const context = await browser.newContext({ baseURL });
    const page = await context.newPage();
    await signIn(page);
    await configure(page, false);
    await context.close();
});

test('a visitor buys a beat licence and downloads it, then an exclusive sale withdraws the beat', async ({ page, visitorPage }) => {
    await signIn(page);
    await configure(page, true);

    await page.goto('/admin/beats');
    await newTier(page, PREMIUM, '59', false);
    await newTier(page, EXCLUSIVE, '499', true);

    await page.getByRole('button', { name: 'New beat' }).first().click();
    const dialog = page.getByRole('dialog');
    await dialog.locator('input[name="title"]').fill(TITLE);
    await dialog.locator('input[name="bpm"]').fill('150');
    await dialog.getByRole('switch', { name: 'Visible on the site' }).click();
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    const row = page.locator('.beat-row', { hasText: TITLE });
    await expect(row).toBeVisible();
    await row.getByRole('button', { name: 'Show files' }).click();
    await row.locator('input[name="file-preview"]').setInputFiles({ name: 'preview.mp3', mimeType: 'audio/mpeg', buffer: MP3 });
    await expect(row.getByText('preview.mp3')).toBeVisible();
    await row.locator('input[name="file-mp3"]').setInputFiles({ name: 'full.mp3', mimeType: 'audio/mpeg', buffer: MP3 });
    await expect(row.getByText('full.mp3')).toBeVisible();

    await buy(visitorPage, PREMIUM);
    await expect(visitorPage.locator('.checkout-state')).toContainText('Payment confirmed');
    const download = visitorPage.locator('.checkout-downloads a').first();
    await expect(download).toBeVisible();
    const href = await download.getAttribute('href');
    const file = await visitorPage.request.get(href);
    expect(file.status()).toBe(200);
    expect(file.headers()['content-disposition']).toContain('attachment');
    expect((await file.body()).length).toBe(MP3.length);
    const tampered = href.replace(/_hash=[^&]+/, '_hash=tampered');
    expect((await visitorPage.request.get(tampered)).status()).toBe(403);
    expect((await visitorPage.request.get(href.replace(/\/download\/([^/]+)\/[^/?]+/, '/download/$1/stems'))).status()).toBe(403);

    await expect(visitorPage.locator('.checkout-downloads a', { hasText: /licen[cs]e/i })).toBeVisible();

    await buy(visitorPage, EXCLUSIVE);
    await visitorPage.goto('/beats');
    await expect(visitorPage.locator('.beat-row', { hasText: TITLE })).toHaveCount(0);

    await page.goto('/admin/beats');
    await expect(page.locator('.beat-row', { hasText: TITLE })).toContainText(/sold/i);

    await remove(page, page.locator('.beat-row', { hasText: TITLE }));
    await remove(page, page.locator('.rows__item', { hasText: PREMIUM }));
    await remove(page, page.locator('.rows__item', { hasText: EXCLUSIVE }));
});
