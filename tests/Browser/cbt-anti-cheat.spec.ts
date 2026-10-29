import { expect, test, type Page, type Request } from '@playwright/test';

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

const isEventRequest = (request: Request) => request.method() === 'POST' && request.url().endsWith('/event');

/** Jalankan aksi lalu tunggu satu request event dan kembalikan `jenis`-nya. */
async function jenisEventSetelah(page: Page, aksi: () => Promise<unknown>): Promise<string> {
    const [request] = await Promise.all([page.waitForRequest(isEventRequest), aksi()]);
    return request.postDataJSON().jenis;
}

test('CBT anti-cheat: keluar/kembali/salin dicatat sekali dan tampil di detail jawaban guru', async ({ browser }) => {
    const siswaContext = await browser.newContext();
    const siswa = await siswaContext.newPage();
    await login(siswa, 'siswa');

    await siswa.goto('/siswa/ujian');
    await siswa.getByRole('button', { name: /Mulai/ }).first().click();
    await siswa.getByRole('button', { name: 'Mulai Sekarang' }).click();
    await expect(siswa.getByText('Berapa hasil 3 + 4 pada ujian uji?')).toBeVisible({ timeout: 15000 });

    const themeButton = siswa.getByRole('button', { name: /Aktifkan mode gelap|Aktifkan mode terang/ }).first();
    if (await siswa.locator('html').getAttribute('data-bs-theme') !== 'dark') {
        await themeButton.click();
    }
    await expect(siswa.locator('html')).toHaveAttribute('data-bs-theme', 'dark');
    await expect(siswa.locator('.exam-option-letter').first()).toHaveCSS('background-color', 'rgb(17, 28, 49)');

    const options = siswa.getByRole('radio');
    await options.first().focus();
    await options.first().press('ArrowRight');
    await expect(options.nth(1)).toBeFocused();
    await expect(options.nth(1)).toHaveAttribute('aria-checked', 'true');

    // Heartbeat pertama terkirim saat halaman ujian dibuka.
    await siswa.waitForResponse((response) => response.url().endsWith('/heartbeat') && response.ok());

    // Kehilangan fokus jendela = satu episode keluar.
    expect(await jenisEventSetelah(siswa, () => siswa.evaluate(() => window.dispatchEvent(new Event('blur'))))).toBe('window_blur');

    // Tab ikut tersembunyi pada episode yang sama: tidak boleh melaporkan keluar kedua kali.
    const tambahan: string[] = [];
    siswa.on('request', (request) => { if (isEventRequest(request)) tambahan.push(request.postDataJSON().jenis); });
    await siswa.evaluate(() => {
        Object.defineProperty(document, 'visibilityState', { value: 'hidden', configurable: true });
        document.dispatchEvent(new Event('visibilitychange'));
    });
    await siswa.waitForTimeout(500);
    expect(tambahan).toEqual([]);

    // Kembali hanya dilaporkan setelah tab terlihat lagi dan jendela kembali fokus.
    expect(await jenisEventSetelah(siswa, () => siswa.evaluate(() => {
        Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
        document.dispatchEvent(new Event('visibilitychange'));
    }))).toBe('kembali');

    // Salin teks soal dicegah dan dicatat.
    let dicegah = false;
    expect(await jenisEventSetelah(siswa, async () => {
        dicegah = await siswa.evaluate(() => {
            const event = new Event('copy', { bubbles: true, cancelable: true });
            document.querySelector('.card-body.p-4')!.dispatchEvent(event);
            return event.defaultPrevented;
        });
    })).toBe('salin');
    expect(dicegah).toBe(true);

    // Guru pengampu melihat kejadian berurutan dengan kata netral; satu kali keluar dihitung satu.
    const guruContext = await browser.newContext();
    const guru = await guruContext.newPage();
    await login(guru, 'guru');
    await guru.goto('/guru/ujian/1/1/attempt/1');
    await expect(guru.getByText('1x Pindah Tab Terdeteksi')).toBeVisible();
    await expect(guru.getByText('Keluar dari jendela ujian')).toBeVisible();
    await expect(guru.getByText('Kembali ke halaman ujian')).toBeVisible();
    await expect(guru.getByText('Mencoba menyalin teks soal')).toBeVisible();

    await siswaContext.close();
    await guruContext.close();
});
