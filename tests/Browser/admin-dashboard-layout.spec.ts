import { expect, test, type Page } from '@playwright/test';

test.beforeEach(() => {
    if (!process.env.BROWSER_BASE_URL?.startsWith('http://127.0.0.1:')) {
        throw new Error('Run npm run test:browser to use isolated fixtures.');
    }
});

async function loginAsAdmin(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Username', { exact: true }).fill('browser-admin');
    await page.getByLabel('Password', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Masuk ke LMS' }).click();
    await expect(page.locator('#mainContent')).toBeVisible({ timeout: 15000 });
}

test('admin dashboard stays readable in phone and split-window widths', async ({ page }) => {
    await loginAsAdmin(page);

    for (const width of [390, 820, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.goto('/admin/dashboard');

        await expect(page.getByRole('heading', { name: /Selamat datang, Admin LMS/i })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Tambah User' })).toBeVisible();
        await expect(page.locator('.admin-dashboard-page')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);

        const actionBar = page.locator('.admin-dashboard-page .quick-action-bar');
        const actionLayout = await actionBar.evaluate(element => {
            const style = getComputedStyle(element);
            return { display: style.display, columns: style.gridTemplateColumns };
        });

        if (width < 992) {
            expect(actionLayout.display).toBe('grid');
            expect(actionLayout.columns.split(' ').length).toBe(2);
        }
    }
});
