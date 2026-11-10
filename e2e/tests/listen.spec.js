import { expect, test } from './support/test.js';
import { signIn } from './support/session.js';

const TITLE = 'White Men are the Issue';
const SLUG = 'white-men-are-the-issue';
const DEEZER = 'https://www.deezer.com/album/1061026552';

async function openPanel(page) {
    await page.goto('/admin/music');
    const row = page.locator('.rows__item', { hasText: TITLE });
    await row.getByRole('button', { name: 'Edit', exact: true }).click();
    return page.locator('.listen');
}

test('the admin fetches the links of a UPC, the visitor listens through the counted redirect', async ({ page, visitorPage }) => {
    await signIn(page);
    const panel = await openPanel(page);
    await expect(page.locator('input[name="upc"]')).toHaveValue('701518909440');
    await panel.getByRole('button', { name: 'Fetch links now' }).click();
    await expect(panel.locator('.listen__row', { hasText: 'Deezer' }).locator('.listen__badge')).toHaveText('Auto');
    await expect(panel.locator('.listen__row', { hasText: 'Apple Music' }).locator('.listen__badge')).toHaveText('Auto');
    const youtubeMusic = panel.locator('.listen__row', { hasText: 'YouTube Music' }).locator('.listen__badge');
    await expect(youtubeMusic).toHaveText('Found by search');
    await expect(youtubeMusic).toHaveAttribute('title', 'Matched by title: check it');
    await expect(panel.locator('.listen__url input')).toHaveValue(new RegExp(`/listen/${SLUG}$`));

    await panel.locator('.listen__row', { hasText: 'Apple Music' }).getByRole('button', { name: 'Hide this link' }).click();
    await expect(panel.locator('.listen__row', { hasText: 'Apple Music' }).locator('.listen__link')).toHaveText('Hidden');

    await visitorPage.goto('/');
    await visitorPage.locator(`#release-${SLUG} summary`).click();
    await visitorPage.locator(`#release-${SLUG}`).getByRole('link', { name: 'All platforms' }).click();
    await expect(visitorPage).toHaveURL(new RegExp(`/listen/${SLUG}$`));
    await expect(visitorPage.getByRole('heading', { name: TITLE })).toBeVisible();
    const platforms = visitorPage.locator('.listen-platforms');
    await expect(platforms.getByRole('link', { name: /Deezer/ })).toBeVisible();
    await expect(platforms.getByRole('link', { name: /Apple Music/ })).toHaveCount(0);

    const href = await platforms.getByRole('link', { name: /Deezer/ }).getAttribute('href');
    expect(href).toBe(`/listen/${SLUG}/deezer`);
    const redirect = await visitorPage.request.get(href, { maxRedirects: 0 });
    expect(redirect.status()).toBe(302);
    expect(redirect.headers().location).toBe(DEEZER);

    const again = await openPanel(page);
    await expect(again.locator('.listen__row', { hasText: 'Deezer' }).locator('.listen__clicks')).toHaveText('1 click');

    await again.locator('.listen__row', { hasText: 'Apple Music' }).getByRole('button', { name: 'Show again' }).click();
    await expect(again.locator('.listen__row', { hasText: 'Apple Music' }).locator('.listen__badge')).toHaveText('Auto');
});
