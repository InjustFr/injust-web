import { expect, test } from './support/test.js';
import { signIn } from './support/session.js';

const LABEL = 'E2E Target';
const TARGET = 'https://e2e-target.example/landing';
const SEO_TITLE = 'E2E Contact Title';

test('a visitor click on a link is counted and lands on the target', async ({ page, visitorPage }) => {
    await signIn(page);
    await page.goto('/admin/links');
    await expect(page.locator('.public-url__link')).toHaveText(new URL('/links', page.url()).href);
    await page.getByRole('button', { name: 'New link' }).click();
    await page.locator('input[name="label-en"]').fill(LABEL);
    await page.locator('input[name="label-fr"]').fill(LABEL);
    await page.locator('input[name="url"]').fill(TARGET);
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.locator('.rows__label', { hasText: LABEL })).toBeVisible();

    await visitorPage.goto('/links');
    const redirect = visitorPage.waitForResponse((response) => response.url().includes('/go/'));
    await visitorPage.getByRole('link', { name: LABEL }).click();
    const response = await redirect;
    expect(response.status()).toBe(302);
    expect(response.headers().location).toBe(TARGET);

    await page.reload();
    await expect(page.locator('.rows', { hasText: LABEL })).toContainText('1 click');
});

test('the admin sets the contact SEO title and the page shows it', async ({ page, visitorPage }) => {
    await signIn(page);
    await page.goto('/admin/seo');
    const contact = page.locator('.page-section', { has: page.getByRole('heading', { name: 'Contact', exact: true }) });
    await contact.locator('input[name="title-en"]').fill(SEO_TITLE);
    await contact.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(contact.locator('.seo__preview-url').last()).toHaveText(`${new URL(page.url()).host}/contact`);
    await expect(page.getByText('Saved.').first()).toBeVisible();

    await visitorPage.goto('/contact');
    await expect(visitorPage).toHaveTitle(SEO_TITLE);
});

test('the 404 page keeps the site layout', async ({ visitorPage }) => {
    const response = await visitorPage.goto('/fr/nulle-part');
    expect(response.status()).toBe(404);
    await expect(visitorPage.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(visitorPage.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);
    await expect(visitorPage.locator('header')).toBeVisible();
});
