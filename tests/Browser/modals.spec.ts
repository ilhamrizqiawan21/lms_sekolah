import { expect, test } from '@playwright/test';

test.beforeEach(() => {
    if (!process.env.BROWSER_BASE_URL?.startsWith('http://127.0.0.1:')) {
        throw new Error('Run npm run test:browser to use isolated fixtures.');
    }
});

async function login(page: import('@playwright/test').Page, role: string) {
    await page.goto('/login');
    await page.getByLabel('Username', { exact: true }).fill(`browser-${role}`);
    await page.getByLabel('Password', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Masuk ke LMS' }).click();
    await expect(page.locator('#mainContent')).toBeVisible({ timeout: 15000 });
}

test('question bank modal: Esc closes, footer submit saves the form', async ({ page }) => {
    await login(page, 'guru');
    await page.goto('/guru/soal-bank');
    await page.getByRole('button', { name: 'Tambah Soal' }).first().click();
    const dialog = page.getByRole('dialog', { name: 'Tambah Soal ke Bank' });
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();

    await page.getByRole('button', { name: 'Tambah Soal' }).first().click();
    await expect(dialog).toBeVisible();
    await dialog.getByLabel('Pertanyaan Soal').fill('Berapa hasil 2 + 2 pada uji modal?');
    await dialog.getByPlaceholder('Teks jawaban pilihan A').fill('4');
    await dialog.getByPlaceholder('Teks jawaban pilihan B').fill('5');
    await dialog.getByPlaceholder('Teks jawaban pilihan C').fill('6');
    await dialog.getByPlaceholder('Teks jawaban pilihan D').fill('7');
    await dialog.getByRole('button', { name: 'Tambahkan Soal' }).click();
    await expect(dialog).toBeHidden({ timeout: 10000 });
    await expect(page.getByText('Berapa hasil 2 + 2 pada uji modal?')).toBeVisible();
});

test('submission detail modal is a labelled dialog that closes with Esc', async ({ page }) => {
    await login(page, 'guru');
    await page.goto('/guru/tugas/1/1/pengumpulan');
    await expect(page.locator('#mainContent')).toBeVisible();
    const trigger = page.getByRole('button', { name: /detail|jawaban/i }).first();
    if (await trigger.count() === 0) test.skip(true, 'Seed has no submission with a detail action.');
    await trigger.click();
    const dialog = page.getByRole('dialog', { name: /Detail Pengumpulan/ });
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
});

test('announcement form built from shared inputs saves a new announcement', async ({ page }) => {
    await login(page, 'admin');
    await page.goto('/admin/pengumuman');
    await page.getByRole('button', { name: /Buat Pengumuman/ }).click();
    await page.getByLabel(/^Judul/).fill('Pengumuman uji form bersama');
    await page.getByLabel('Target', { exact: true }).selectOption('siswa');
    await page.getByLabel(/^Isi/).fill('Isi pengumuman dari tes browser.');
    await page.getByRole('button', { name: /Simpan|Publikasikan|Kirim/ }).first().click();
    await expect(page.getByText('Pengumuman uji form bersama')).toBeVisible({ timeout: 10000 });
});

test('submission filters keep working with shared inputs', async ({ page }) => {
    await login(page, 'guru');
    await page.goto('/guru/tugas/1/1/pengumpulan');
    const rows = page.getByRole('row', { name: /Pengguna Uji siswa/ });
    await expect(rows).toHaveCount(1);
    await page.getByLabel('Filter status pengumpulan').selectOption('perlu_perbaikan');
    await expect(rows).toHaveCount(0);
    await page.getByLabel('Filter status pengumpulan').selectOption('semua');
    await expect(rows).toHaveCount(1);
    await page.getByLabel('Cari siswa').fill('tidak-ada-siswa-ini');
    await expect(rows).toHaveCount(0);
});
