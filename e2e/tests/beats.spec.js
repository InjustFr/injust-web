import { expect, test } from './support/test.js';
import { signIn } from './support/session.js';

const TITLE = 'E2E Riff';
const TIER = 'E2E Licence';
const NAME = 'E2E Artist';
const BODY = 'Hello, I would like this beat for my next single please.';
const FRAME = Buffer.concat([Buffer.from([0xff, 0xfb, 0x90, 0x00]), Buffer.alloc(413)]);
const MP3 = Buffer.concat(Array.from({ length: 380 }, () => FRAME));

async function setBeats(page, on) {
    await page.goto('/admin/settings');
    const toggle = page.getByRole('switch', { name: 'Beats store' });
    await expect(toggle).toBeVisible();
    if ((await toggle.isChecked()) !== on) await toggle.click();
    if (on) await expect(toggle).toBeChecked();
    else await expect(toggle).not.toBeChecked();
}

async function remove(page, row) {
    await row.getByRole('button', { name: 'Delete' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toHaveCount(0);
}

test('the admin lists a beat with its files, the visitor previews it and asks for a licence', async ({ page, visitorPage }) => {
    await signIn(page);
    await setBeats(page, true);

    await page.goto('/admin/beats');
    await page.getByRole('button', { name: 'New tier' }).first().click();
    let dialog = page.getByRole('dialog');
    await dialog.locator('input[name="name-en"]').fill(TIER);
    await dialog.locator('input[name="name-fr"]').fill('Licence E2E');
    await dialog.locator('input[name="price"]').fill('29');
    await dialog.getByLabel('MP3').check();
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.locator('.rows__item', { hasText: TIER })).toBeVisible();

    await page.getByRole('button', { name: 'New beat' }).first().click();
    dialog = page.getByRole('dialog');
    await dialog.locator('input[name="title"]').fill(TITLE);
    await dialog.locator('input[name="bpm"]').fill('140');
    await dialog.locator('input[name="tags"]').fill('punk, loud');
    await dialog.getByRole('switch', { name: 'Visible on the site' }).click();
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    const row = page.locator('.beat-row', { hasText: TITLE });
    await expect(row).toBeVisible();
    await expect(row).toContainText('Not on the site');

    await row.getByRole('button', { name: 'Show files' }).click();
    await row.locator('input[name="file-preview"]').setInputFiles({ name: 'preview.mp3', mimeType: 'audio/mpeg', buffer: MP3 });
    await expect(row.getByText('preview.mp3')).toBeVisible();
    await expect(row).not.toContainText('Not on the site');
    await row.locator('input[name="file-mp3"]').setInputFiles({ name: 'full.mp3', mimeType: 'audio/mpeg', buffer: MP3 });
    await expect(row.getByText('full.mp3')).toBeVisible();

    const catalogue = await page.request.get('/api/admin/beats');
    const payload = await catalogue.text();
    expect(payload).toContain('full.mp3');
    expect(payload).not.toMatch(/"(path|url|downloadUrl)"/);

    const foreign = [];
    visitorPage.on('request', (request) => {
        if (!request.url().startsWith('http://php-e2e')) foreign.push(request.url());
    });
    await visitorPage.goto('/beats');
    const line = visitorPage.locator('.beat-row', { hasText: TITLE });
    await expect(line.locator('.beat-bpm')).toHaveText('140 BPM');
    await expect(line.locator('.beat-price')).toContainText('€29');
    await line.getByRole('button', { name: `Play “${TITLE}”` }).click();
    await expect(line.getByRole('button', { name: `Play “${TITLE}”` })).toHaveAttribute('aria-pressed', 'true');
    await expect.poll(() => visitorPage.locator('audio').evaluate((audio) => audio.currentSrc)).toMatch(/\/media\/audio\/[0-9A-HJKMNP-TV-Z]{26}\.mp3$/);
    await expect.poll(() => visitorPage.locator('audio').evaluate((audio) => audio.readyState)).toBeGreaterThan(0);

    expect((await visitorPage.request.get('/api/admin/beats', { maxRedirects: 0 })).status()).not.toBe(200);
    expect((await visitorPage.request.get('/media/private/01J00000000000000000000000')).status()).toBe(404);
    expect((await visitorPage.request.get('/media/audio/01J00000000000000000000000.mp3')).status()).toBe(404);
    expect(foreign).toEqual([]);

    await line.getByRole('link', { name: 'Contact me' }).click();
    await expect(visitorPage).toHaveURL(/\/contact\?topic=beats&offer=/);
    await expect(visitorPage.locator('.contact-offer-name')).toHaveText(TITLE);

    await visitorPage.locator('#contact-name').fill(NAME);
    await visitorPage.locator('#contact-email').fill('artist@example.com');
    await visitorPage.locator('#contact-body').fill(BODY);
    await visitorPage.locator('.contact-send').click();
    await expect(visitorPage.locator('.contact-done')).toBeVisible();

    await page.goto('/admin/messages');
    const message = page.locator('.message', { hasText: NAME });
    await expect(message).toContainText(TITLE);
    await message.getByRole('button', { name: 'Mark as handled' }).click();
    await expect(page.locator('.message', { hasText: NAME })).toHaveCount(0);

    await page.goto('/admin/beats');
    await remove(page, page.locator('.beat-row', { hasText: TITLE }));
    await remove(page, page.locator('.rows__item', { hasText: TIER }));
    await setBeats(page, false);
});
