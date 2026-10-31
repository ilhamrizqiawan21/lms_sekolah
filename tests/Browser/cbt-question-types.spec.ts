import { expect, test } from '@playwright/test';

test.beforeEach(() => {
    if (!process.env.BROWSER_BASE_URL?.startsWith('http://127.0.0.1:')) {
        throw new Error('Run npm run test:browser to use isolated fixtures.');
    }
});

test('siswa dapat mengisi matching dan essay sampai hasil ujian', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Username', { exact: true }).fill('browser-siswa-2');
    await page.getByLabel('Password', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Masuk ke LMS' }).click();
    await expect(page.locator('#mainContent')).toBeVisible({ timeout: 15000 });

    await page.goto('/siswa/ujian');
    await page.getByRole('button', { name: /Mulai/ }).first().click();
    await page.getByRole('button', { name: 'Mulai Sekarang' }).click();

    await expect(page.getByText('Berapa hasil 3 + 4 pada ujian uji?')).toBeVisible();
    await page.getByRole('radio').first().click();
    await page.getByRole('button', { name: 'Selanjutnya' }).click();

    await expect(page.getByText('Jodohkan ibu kota dengan negaranya.')).toBeVisible();
    const pasangan = page.getByRole('combobox');
    await pasangan.nth(0).selectOption({ label: 'Jakarta' });
    await pasangan.nth(1).selectOption({ label: 'Tokyo' });
    await page.getByRole('button', { name: 'Selanjutnya' }).click();

    await expect(page.getByText('Jelaskan manfaat belajar teratur.')).toBeVisible();
    await page.getByLabel('Jawaban Anda').fill('Belajar teratur membantu memahami materi secara bertahap.');
    await page.getByRole('button', { name: 'Kumpulkan Jawaban' }).click();
    await page.getByRole('button', { name: 'Ya, Selesaikan Ujian' }).click();

    await expect(page.getByText('Ujian Berhasil Dikumpulkan')).toBeVisible({ timeout: 15000 });
    await expect(page.getByText('Review Jawaban dan Pembahasan')).toBeVisible();
    await expect(page.getByText('Belajar teratur membantu membangun pemahaman dan kebiasaan yang konsisten.')).toBeVisible();
});
