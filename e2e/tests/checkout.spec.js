import { expect, test } from './support/test.js';
import { signIn } from './support/session.js';

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

async function configure(page, { checkout, lessons = true }) {
    await page.request.put('/api/admin/settings/legal-identity', { data: IDENTITY });
    await page.request.put('/api/admin/settings/sections', { data: { lessons, production: false, beats: false } });
    const response = await page.request.put('/api/admin/settings/checkout', { data: { enabled: checkout } });
    expect(response.ok()).toBe(true);
}

async function startOrder(visitorPage) {
    await visitorPage.goto('/lessons');
    await visitorPage.locator('.offer').first().getByRole('link', { name: 'Book & pay' }).click();
    await expect(visitorPage).toHaveURL(/\/checkout\/lesson\//);

    const submit = visitorPage.getByRole('button', { name: 'Order with obligation to pay' });
    await expect(submit).toBeDisabled();
    await visitorPage.locator('#checkout-email').fill('buyer@example.com');
    for (const kind of ['terms', 'early-start', 'pro-rata']) {
        const box = visitorPage.locator(`#consent-${kind}`);
        await expect(box).not.toBeChecked();
        await box.check();
    }
    await expect(submit).toBeEnabled();
    await submit.click();
    await expect(visitorPage).toHaveURL(/\/_fake-checkout\//);
}

test.afterAll(async ({ browser, baseURL }) => {
    const context = await browser.newContext({ baseURL });
    const page = await context.newPage();
    await signIn(page);
    await configure(page, { checkout: false, lessons: false });
    await context.close();
});

test('a visitor books a lesson and pays, the admin sees the paid order with its invoice and consents', async ({ page, visitorPage }) => {
    await signIn(page);
    await configure(page, { checkout: true });

    await startOrder(visitorPage);
    await visitorPage.getByRole('button', { name: 'Pay' }).click();
    await expect(visitorPage).toHaveURL(/\/checkout\/success\?order=/);
    await expect(visitorPage.locator('.checkout-state')).toContainText('Payment confirmed');
    const reference = (await visitorPage.locator('.checkout-reference').textContent()).trim();
    expect(reference).toMatch(/^INJ-[0-9A-Z]{6}$/);

    await page.goto('/admin/orders');
    const row = page.locator('tbody', { hasText: reference });
    await expect(row).toContainText('Paid');
    await row.getByRole('button', { name: 'Show details' }).click();
    await expect(row).toContainText(/Invoice \d{4}-\d{4}/);
    await expect(row).toContainText('buyer@example.com');
    await expect(row).toContainText('I expressly request that the service starts');
    await expect(row).toContainText('Sent on');

    const href = await row.getByRole('link', { name: 'Open invoice' }).getAttribute('href');
    const invoice = await page.request.get(href);
    expect(invoice.status()).toBe(200);
    expect(invoice.headers()['content-type']).toContain('application/pdf');
    expect((await invoice.body()).subarray(0, 4).toString()).toBe('%PDF');
});

test('cancelling on the payment page places no order', async ({ page, visitorPage }) => {
    await signIn(page);
    await configure(page, { checkout: true });

    await startOrder(visitorPage);
    await visitorPage.getByRole('link', { name: 'Cancel' }).click();
    await expect(visitorPage).toHaveURL(/\/checkout\/cancel\?order=/);
    await expect(visitorPage.getByText('Nothing was charged')).toBeVisible();
    await expect(visitorPage.getByRole('link', { name: 'Back to the offer' })).toBeVisible();
});

test('with checkout off there is no Book & pay and the checkout page is a 404', async ({ page, visitorPage }) => {
    await signIn(page);
    await configure(page, { checkout: true });
    await visitorPage.goto('/lessons');
    const path = await visitorPage.locator('.offer').first().getByRole('link', { name: 'Book & pay' }).getAttribute('href');

    await configure(page, { checkout: false });
    await visitorPage.goto('/lessons');
    await expect(visitorPage.getByRole('link', { name: 'Contact me' }).first()).toBeVisible();
    await expect(visitorPage.getByRole('link', { name: 'Book & pay' })).toHaveCount(0);
    expect((await visitorPage.goto(path)).status()).toBe(404);
});
