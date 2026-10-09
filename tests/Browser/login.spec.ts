import { expect, test } from '@playwright/test';

test.beforeEach(() => {
    if (!process.env.BROWSER_BASE_URL?.startsWith('http://127.0.0.1:')) {
        throw new Error('Run npm run test:browser to use isolated fixtures.');
    }
});

test('login succeeds with seeded credentials', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Username', { exact: true }).fill('browser-siswa');
    await page.getByLabel('Password', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Masuk ke LMS' }).click();
    await expect(page.locator('#mainContent')).toBeVisible({ timeout: 15000 });
});

test('login fails with a wrong password and shows an accessible error', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Username', { exact: true }).fill('browser-siswa');
    await page.getByLabel('Password', { exact: true }).fill('salah-total');
    await page.getByRole('button', { name: 'Masuk ke LMS' }).click();
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('[role="alert"], .invalid-feedback, [id$="-error"]').first()).toBeVisible();
    await expect(page.locator('#mainContent')).toHaveCount(0);
});

test('password toggle switches type and aria-pressed', async ({ page }) => {
    await page.goto('/login');
    const password = page.getByLabel('Password', { exact: true });
    const toggle = page.locator('button[aria-pressed]').first();
    await expect(password).toHaveAttribute('type', 'password');
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    await toggle.click();
    await expect(password).toHaveAttribute('type', 'text');
    await expect(toggle).toHaveAttribute('aria-pressed', 'true');
});

test('login is usable on mobile without horizontal overflow', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await expect(page.getByRole('button', { name: 'Masuk ke LMS' })).toBeVisible();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
    expect(overflow).toBeLessThanOrEqual(1);
});
