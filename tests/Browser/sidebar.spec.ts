import { expect, test, type Page } from '@playwright/test';

test.beforeEach(() => {
    if (!process.env.BROWSER_BASE_URL?.startsWith('http://127.0.0.1:')) {
        throw new Error('Run npm run test:browser to use isolated fixtures.');
    }
});

async function login(page: Page, role: string) {
    await page.goto('/login');
    await page.getByLabel('Username', { exact: true }).fill(`browser-${role}`);
    await page.getByLabel('Password', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Masuk ke LMS' }).click();
    await expect(page.locator('#mainContent')).toBeVisible({ timeout: 15000 });
}

test('sidebar mobile dapat dibuka dan ditutup dari tombol topbar', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, 'admin');
    await page.goto('/admin/dashboard');

    const toggle = page.locator('.topbar-toggle-btn');
    const sidebar = page.locator('#sidebar');
    const overlay = page.locator('.sidebar-overlay');

    await expect(sidebar.locator('.sidebar-logo-icon img')).toHaveCount(0);
    await expect(sidebar.locator('.sidebar-logo-icon > .bi-layers-fill')).toHaveCount(1);
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(sidebar).toBeInViewport();
    await expect(overlay).toHaveClass(/show/);

    const toggleIsTopmost = await page.evaluate(() => document.elementFromPoint(20, 20)?.closest('.topbar-toggle-btn') !== null);
    expect(toggleIsTopmost).toBe(true);

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(sidebar).not.toBeInViewport();
    const mobileNav = page.locator('.mobile-bottom-nav');
    const mobileNavBox = await mobileNav.boundingBox();
    expect(mobileNavBox).not.toBeNull();
    expect(mobileNavBox?.x).toBeGreaterThan(0);
    expect((mobileNavBox?.x ?? 0) + (mobileNavBox?.width ?? 0)).toBeLessThan(390);
    await expect(page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).resolves.toBeLessThanOrEqual(1);
});

test('menu audit admin dapat dilipat tanpa menghilangkan aksesnya', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page, 'admin');
    await page.goto('/admin/dashboard');

    const audit = page.locator('details.sidebar-collapsible');
    await expect(audit).toHaveCount(1);
    await expect(audit.locator('summary')).toContainText('Audit & Keamanan');
    await expect(audit.getByRole('link', { name: 'Log Error' })).not.toBeVisible();

    await audit.locator('summary').click();
    await expect(audit.getByRole('link', { name: 'Log Error' })).toBeVisible();
    await audit.locator('summary').click();
    await expect(audit.getByRole('link', { name: 'Log Error' })).not.toBeVisible();
});

test('drawer mobile menutup setelah memilih menu', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, 'admin');
    await page.goto('/admin/dashboard');

    const toggle = page.locator('.topbar-toggle-btn');
    await page.evaluate(() => {
        const state = window as Window & { __pageTransitionPhases?: string[] };
        state.__pageTransitionPhases = [];
        new MutationObserver(() => {
            if (document.documentElement.classList.contains('page-route-entering')) {
                state.__pageTransitionPhases?.push('enter');
            }
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    });
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('link', { name: 'Kelas & Siswa' }).click();
    await expect(page).toHaveURL(/\/admin\/kelas-siswa$/);
    await expect.poll(() => page.evaluate(() => (window as Window & { __pageTransitionPhases?: string[] }).__pageTransitionPhases ?? [])).toContain('enter');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
});

test('posisi scroll sidebar dipertahankan setelah membuka menu bagian bawah', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 720 });
    await login(page, 'admin');
    await page.goto('/admin/dashboard');

    const sidebarNav = page.locator('#sidebar .sidebar-nav');
    await sidebarNav.evaluate((element) => { element.scrollTop = element.scrollHeight; });
    const previousScrollTop = await sidebarNav.evaluate((element) => element.scrollTop);
    expect(previousScrollTop).toBeGreaterThan(0);

    await sidebarNav.getByRole('link', { name: 'Pengaturan', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/pengaturan$/);
    await expect.poll(() => sidebarNav.evaluate((element) => element.scrollTop)).toBeGreaterThan(0);
});
