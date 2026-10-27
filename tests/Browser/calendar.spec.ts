import { expect, test, type Page } from '@playwright/test';

test.beforeEach(async ({ page }) => {
    if (!process.env.BROWSER_BASE_URL?.startsWith('http://127.0.0.1:')) {
        throw new Error('Run npm run test:browser to use isolated fixtures.');
    }
    page.on('pageerror', error => { throw error; });
});

async function login(page: Page, role: string) {
    await page.goto('/login');
    await page.getByLabel('Username', { exact: true }).fill(`browser-${role}`);
    await page.getByLabel('Password', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Masuk ke LMS' }).click();
    await expect(page.locator('#mainContent')).toBeVisible({ timeout: 15000 });
}

const roleRoutes = [
    ['admin', '/admin/kalender'],
    ['guru', '/guru/kalender'],
    ['siswa', '/siswa/kalender'],
    ['kepala_sekolah', '/kepsek/kalender'],
] as const;

for (const [role, route] of roleRoutes) {
    test(`${role} calendar starts each week on Monday and fits mobile`, async ({ page }, testInfo) => {
        await login(page, role);
        const response = await page.goto(`${route}?year=2026&month=3`);
        expect(response?.status()).toBe(200);

        await expect(page.locator('.calendar-head th')).toHaveText(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']);
        const firstWeek = page.locator('.calendar-table tbody tr').first().locator('td');
        await expect(firstWeek).toHaveCount(7);
        await expect(firstWeek.nth(0).locator('.calendar-day')).toHaveCount(0);
        await expect(firstWeek.nth(6).locator('.calendar-day')).toHaveText('1');

        if (role === 'admin' || role === 'guru') {
            await firstWeek.nth(6).click();
            await expect(page.locator('input[name="event_date"]')).toHaveValue('2026-03-01');
        } else {
            await expect(page.locator('input[name="event_date"]')).toHaveCount(0);
            await expect(firstWeek.nth(6)).toHaveCSS('cursor', 'auto');
        }
        await page.screenshot({ path: testInfo.outputPath(`${role}-1440.png`), fullPage: true });

        for (const width of [820, 390]) {
            await page.setViewportSize({ width, height: 844 });
            await expect(page.locator('.calendar-workspace')).toBeVisible();
            await expect(page.locator('.topbar-toggle-btn')).toHaveAttribute('aria-expanded', 'false');
            await expect(page.locator('.sidebar-overlay')).not.toHaveClass(/show/);
            await expect.poll(() => page.locator('#sidebar').evaluate((sidebar) =>
                new DOMMatrixReadOnly(getComputedStyle(sidebar).transform).m41,
            )).toBeLessThan(-250);
            expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
            await page.screenshot({ path: testInfo.outputPath(`${role}-${width}.png`), fullPage: true });
        }
    });
}
