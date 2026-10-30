import { expect, test } from '@playwright/test';

test.beforeEach(() => {
    if (!process.env.BROWSER_BASE_URL?.startsWith('http://127.0.0.1:')) {
        throw new Error('Run npm run test:browser to use isolated fixtures.');
    }
});

test('landing page presents the LMS and a direct login path', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('heading', { name: /Belajar lebih terarah/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /Masuk ke LMS/i }).first()).toHaveAttribute('href', '/login');
    await expect(page.getByRole('link', { name: /Pengumuman/i })).toBeVisible();
});

test('landing page is usable on mobile without horizontal overflow', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');

    await expect(page.getByRole('heading', { name: /Belajar lebih terarah/i })).toBeVisible();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
    expect(overflow).toBeLessThanOrEqual(1);
});
