import { expect, test } from './support/test.js';
import { signIn } from './support/session.js';

const TITLE = 'E2E Anthem';
const VIDEO_TITLE = 'E2E Clip';
const VIDEO_ID = 'dQw4w9WgXcQ';
const SPOTIFY_ID = '4uLU6hMCjMI75M1A2tKUQC';

test('the admin publishes a release and a video, the visitor plays them after a click', async ({ page, visitorPage, baseURL }) => {
    await signIn(page);
    await page.goto('/admin/music');

    await page.getByRole('button', { name: 'New release' }).click();
    await page.locator('input[name="title"]').fill(TITLE);
    await page.locator('input[name="releaseDate"]').fill('2030-01-01');
    await page.locator('textarea[name="description-en"]').fill('English words');
    await page.locator('textarea[name="description-fr"]').fill('Mots francais');
    await page.locator('input[name="spotifyUrl"]').fill(`https://open.spotify.com/album/${SPOTIFY_ID}`);
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.getByText('Saved.').first()).toBeVisible();
    await expect(page.locator('.rows__label', { hasText: TITLE })).toBeVisible();

    await page.getByRole('button', { name: 'Videos' }).click();
    await page.locator('input[name="videoUrl"]').fill(`https://youtu.be/${VIDEO_ID}`);
    await page.locator('input[name="videoTitle"]').fill(VIDEO_TITLE);
    await page.getByRole('button', { name: 'Add', exact: true }).click();
    await expect(page.locator('.rows__label', { hasText: VIDEO_TITLE })).toBeVisible();

    const foreign = [];
    visitorPage.on('request', (request) => {
        const url = new URL(request.url());
        if (url.protocol.startsWith('http') && url.origin !== new URL(baseURL).origin) foreign.push(request.url());
    });
    await visitorPage.goto('/');
    await visitorPage.waitForLoadState('networkidle');
    expect(foreign).toEqual([]);

    await visitorPage.locator('.video-button').first().click();
    await expect(visitorPage.locator(`iframe.video-frame[src^="https://www.youtube-nocookie.com/embed/${VIDEO_ID}"]`)).toBeAttached();

    await visitorPage.locator('details.release', { hasText: TITLE }).locator('summary').click();
    await visitorPage.locator('details.release[open] .spotify-load').click();
    await expect(visitorPage.locator(`iframe.spotify-frame[src^="https://open.spotify.com/embed/album/${SPOTIFY_ID}"]`)).toBeAttached();
});

test('the French home shows the French texts', async ({ visitorPage }) => {
    await visitorPage.goto('/fr');
    await expect(visitorPage.locator('html')).toHaveAttribute('lang', 'fr');
    await visitorPage.locator('details.release', { hasText: TITLE }).locator('summary').click();
    await expect(visitorPage.locator('details.release[open]')).toContainText('Mots francais');
});
