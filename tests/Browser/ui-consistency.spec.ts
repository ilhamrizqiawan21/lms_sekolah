import { expect, test, type Locator, type Page } from '@playwright/test';

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

async function setColorMode(page: Page, mode: 'light' | 'dark') {
    const currentMode = await page.locator('html').getAttribute('data-bs-theme');
    if (currentMode !== mode) {
        await page.getByRole('button', { name: mode === 'dark' ? 'Aktifkan mode gelap' : 'Aktifkan mode terang' }).click();
    }
    await expect(page.locator('html')).toHaveAttribute('data-bs-theme', mode);
    await page.waitForTimeout(200);
}

async function appearance(button: Locator) {
    return button.evaluate(element => {
        const style = getComputedStyle(element);
        return { background: style.backgroundColor, color: style.color, radius: style.borderRadius };
    });
}

for (const mode of ['light', 'dark'] as const) {
    test(`primary and legacy success actions share colors in ${mode} mode`, async ({ page }) => {
        await login(page, 'admin');
        await page.goto('/admin/kelas-siswa');
        await setColorMode(page, mode);
        const palette = await page.evaluate(() => {
            const styles = getComputedStyle(document.documentElement);
            return {
                body: styles.getPropertyValue('--surface-body').trim(),
                card: styles.getPropertyValue('--surface-card').trim(),
            };
        });
        expect(palette).toEqual(mode === 'dark'
            ? { body: '#0f172a', card: '#151f33' }
            : { body: '#f5f7fa', card: '#ffffff' });

        const primary = page.getByRole('button', { name: 'Simpan', exact: true });
        const normal = await appearance(primary);
        await primary.hover();
        // Wait for the shared color transition to finish before comparing states.
        await page.waitForTimeout(200);
        const hover = await appearance(primary);

        await page.goto('/admin/kelas-mapel');
        await setColorMode(page, mode);
        const save = page.getByRole('button', { name: 'Simpan Pengajaran', exact: true });
        expect(await appearance(save)).toEqual(normal);
        await save.hover();
        await page.waitForTimeout(200);
        expect(await appearance(save)).toEqual(hover);
        await save.focus();
        await expect(save).toHaveCSS('outline-style', 'solid');
        await save.evaluate(element => { (element as HTMLButtonElement).disabled = true; });
        await expect(save).toBeDisabled();
        await expect(save).toHaveCSS('opacity', '0.6');
        await expect(save).toHaveCSS('background-color', normal.background);

        const badge = page.locator('.assignment-form-panel .badge').last();
        expect(await badge.evaluate(element => getComputedStyle(element).backgroundColor)).not.toEqual(normal.background);
    });
}

test('sidebar navigation text and icons keep theme-token contrast', async ({ page }) => {
    await login(page, 'admin');
    await page.goto('/admin/dashboard');

    for (const mode of ['light', 'dark'] as const) {
        await setColorMode(page, mode);
        const contrast = await page.locator('.sidebar-menu .nav-link:not(.active)').first().evaluate(link => {
            const icon = link.querySelector('i');
            const sidebar = link.closest('.sidebar');
            if (!icon || !sidebar) throw new Error('Sidebar navigation link is missing its icon or sidebar surface.');

            const luminance = (color: string) => {
                const values = color.match(/[\d.]+/g)?.slice(0, 3).map(Number) ?? [];
                const channels = values.map(value => {
                    const normalized = value / 255;
                    return normalized <= 0.04045 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
                });
                return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
            };
            const ratio = (foreground: string, background: string) => {
                const values = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
                return (values[0] + 0.05) / (values[1] + 0.05);
            };
            const linkStyle = getComputedStyle(link);
            const iconStyle = getComputedStyle(icon);
            const surface = getComputedStyle(sidebar).backgroundColor;
            const tokenColor = (token: string) => {
                const probe = document.createElement('span');
                probe.style.color = `var(${token})`;
                document.body.append(probe);
                const resolved = getComputedStyle(probe).color;
                probe.remove();
                return resolved;
            };

            return {
                textColor: linkStyle.color,
                iconColor: iconStyle.color,
                surface,
                textContrast: ratio(linkStyle.color, surface),
                iconContrast: ratio(iconStyle.color, surface),
                surfaceUsesThemeToken: surface === tokenColor('--sidebar-navy'),
                textUsesThemeToken: linkStyle.color === tokenColor('--sidebar-foreground'),
                iconUsesThemeToken: iconStyle.color === tokenColor('--sidebar-foreground-muted'),
            };
        });

        expect(contrast.textContrast, `${mode} sidebar text: ${JSON.stringify(contrast)}`).toBeGreaterThanOrEqual(4.5);
        expect(contrast.iconContrast, `${mode} sidebar icon: ${JSON.stringify(contrast)}`).toBeGreaterThanOrEqual(3);
        expect(contrast.surfaceUsesThemeToken).toBe(true);
        expect(contrast.textUsesThemeToken).toBe(true);
        expect(contrast.iconUsesThemeToken).toBe(true);
    }
});

test('shared shell keeps readable labels and flat surfaces', async ({ page }) => {
    await login(page, 'admin');
    await page.goto('/admin/dashboard');
    await expect(page.locator('.modern-topbar')).toBeVisible();
    await expect(page.locator('.topbar-toggle-btn')).toHaveCSS('display', 'none');

    const shell = await page.evaluate(() => {
        const labels = [
            document.querySelector('.breadcrumbs'),
            document.querySelector('.period-select'),
            document.querySelector('.top-profile'),
        ].filter((element): element is HTMLElement => Boolean(element));

        const topbar = document.querySelector('.modern-topbar');

        return {
            labelSizes: labels.map(element => Number.parseFloat(getComputedStyle(element).fontSize)),
            backdropFilter: topbar ? getComputedStyle(topbar).backdropFilter : null,
        };
    });

    expect(shell.labelSizes.length).toBeGreaterThan(0);
    for (const size of shell.labelSizes) expect(size).toBeGreaterThanOrEqual(12);
    expect(shell.backdropFilter).toBe('none');
});

test('dashboard teacher and sidebar labels stay readable on mobile and desktop', async ({ page }) => {
    await login(page, 'guru');
    for (const width of [390, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.goto('/guru/dashboard');

        const textSizes = await page.locator([
            '.overview-label', '.text-link', '.queue-tab', '.tab-count', '.subject-mark', '.task-title',
            '.task-meta', '.status', '.queue-footer', '.timeline-time', '.timeline-current',
            '.timeline-copy strong', '.timeline-copy small', '.class-name', '.class-meta', '.class-stat',
            '.attention-copy strong', '.attention-copy small', '#sidebar .sidebar-logo-sub',
            '#sidebar .nav-section', '#sidebar .sidebar-profile-copy strong', '#sidebar .sidebar-profile-copy small',
        ].join(', ')).evaluateAll(elements => elements.map(element => Number.parseFloat(getComputedStyle(element).fontSize)));

        expect(textSizes.length).toBeGreaterThan(0);
        for (const size of textSizes) expect(size, `${width}px dashboard text`).toBeGreaterThanOrEqual(12);
        expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
    }
});

test('card headings and page actions remain readable across viewport sizes', async ({ page }, testInfo) => {
    await login(page, 'admin');
    for (const width of [390, 820, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.goto('/admin/kelas-siswa');
        await expect(page.getByRole('heading', { name: 'Tambah Siswa Baru', level: 2 })).toBeVisible();
        const account = await page.getByRole('button', { name: 'Menu akun', exact: true }).boundingBox();
        expect(account).not.toBeNull();
        expect(account!.x + account!.width).toBeLessThanOrEqual(width);
        expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
        await page.screenshot({ path: testInfo.outputPath(`kelas-siswa-${width}.png`), fullPage: true });
    }
    await page.goto('/admin/pengumuman');
    const edit = page.locator('.announcement-actions .btn-outline-success').first();
    const destructive = page.locator('.announcement-actions .btn-outline-danger').first();
    await expect(edit).toBeVisible();
    expect((await appearance(edit)).color).not.toEqual((await appearance(destructive)).color);
});
