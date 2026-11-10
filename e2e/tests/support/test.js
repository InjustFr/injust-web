import { expect, test as base } from '@playwright/test';

async function watchPolicyViolations(page) {
    const violations = [];
    await page.exposeFunction('reportPolicyViolation', (violation) => violations.push(violation));
    await page.addInitScript(() => {
        document.addEventListener('securitypolicyviolation', (event) => {
            window.reportPolicyViolation(`${event.violatedDirective} blocked ${event.blockedURI || 'inline'} at ${event.sourceFile}:${event.lineNumber}`);
        });
    });

    return violations;
}

export const test = base.extend({
    page: async ({ page }, use) => {
        const violations = await watchPolicyViolations(page);
        await use(page);
        expect(violations, 'content security policy violations').toEqual([]);
    },
    visitorPage: async ({ browser, baseURL }, use) => {
        const context = await browser.newContext({ baseURL });
        const page = await context.newPage();
        const violations = await watchPolicyViolations(page);
        await use(page);
        await context.close();
        expect(violations, 'content security policy violations on the visitor page').toEqual([]);
    },
});

export { expect };
