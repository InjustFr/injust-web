export const EDITOR = {
    account: 'editor',
    claims: { email: 'editor@injust.test', name: 'Editor', groups: ['injust'] },
};

export const OUTSIDER = {
    account: 'outsider',
    claims: { email: 'outsider@injust.test', name: 'Outsider', groups: ['strangers'] },
};

export async function signInWithAccount(page, account) {
    await page.locator('input[name="username"]').fill(account.account);
    await page.locator('textarea[name="claims"]').fill(JSON.stringify(account.claims));
    await page.getByRole('button', { name: 'Sign-in' }).click();
}

export async function signIn(page, account = EDITOR) {
    await page.goto('/admin/');
    await signInWithAccount(page, account);
    await page.waitForURL('/admin/');
}
