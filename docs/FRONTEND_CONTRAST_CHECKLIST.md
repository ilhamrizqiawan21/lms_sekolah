# Frontend Contrast Checklist

Checklist ini dipakai saat mengubah tema warna frontend LMS. Fokusnya memastikan tema tetap terbaca pada layout Bootstrap + Vite tanpa mengganggu pagination dan komponen form.

## Tema Hijau

- [ ] Sidebar text terbaca di atas background hijau.
- [ ] Topbar title dan role label terbaca.
- [ ] Button primary/success jelas pada default, hover, dan disabled.
- [ ] Link dan pagination aktif terlihat jelas.
- [ ] Badge success, warning, danger, info tetap terbaca.
- [ ] Focus ring input terlihat pada background putih.

## Tema Biru Azure

- [ ] Sidebar text terbaca di atas background biru.
- [ ] Topbar title dan role label terbaca.
- [ ] Button primary/success jelas pada default, hover, dan disabled.
- [ ] Link dan pagination aktif terlihat jelas.
- [ ] Badge success, warning, danger, info tetap terbaca.
- [ ] Focus ring input terlihat pada background putih.

## Tema Biru Aqua

- [ ] Sidebar text terbaca di atas background aqua.
- [ ] Topbar title dan role label terbaca.
- [ ] Button primary/success jelas pada default, hover, dan disabled.
- [ ] Link dan pagination aktif terlihat jelas.
- [ ] Badge success, warning, danger, info tetap terbaca.
- [ ] Focus ring input terlihat pada background putih.

## Area Wajib Dicek

- [ ] Login.
- [ ] Dashboard admin.
- [ ] Kelas & Siswa.
- [ ] Tabel dengan pagination Laravel.
- [ ] Tabel dengan DataTables.
- [ ] Form input nilai atau absensi.
- [ ] Modal edit.
- [ ] Alert success/error/warning.
- [ ] Toast dan confirm dialog.

## Catatan Pagination

- Jangan override markup pagination Laravel.
- Jangan mengganti class `.pagination`, `.page-item`, atau `.page-link` di Blade.
- Override visual cukup lewat CSS variables Bootstrap dan selector yang sudah ada.
- Jika pagination terlihat rusak setelah build Vite, cek urutan load CSS: Bootstrap dari Vite harus lebih dulu, `public/css/lms-app.css` setelahnya.

## Hasil audit Fase 1 (2026-10-04)

Rasio kontras WCAG dihitung dari nilai token. Batas AA: 4,5:1 untuk teks biasa.

| Pasangan | Rasio | Status |
|---|---|---|
| Light: `--text-strong` / kartu | 16,27 | Lolos |
| Light: `--text-body` / kartu | 10,35 | Lolos |
| Light: `--text-muted` `#5b6b82` / `--app-bg` | 5,07 (sebelumnya `#64748b`: 4,45) | Lolos setelah perbaikan |
| Light: status success, warning, danger, info (teks/latar) | 6,37 sampai 7,15 | Lolos |
| Dark: `--text-strong`, `--text-body`, `--text-muted` / kartu | 8,02 sampai 16,96 | Lolos |
| Dark: status success, warning, danger, info | 7,01 sampai 9,46 | Lolos |
| Teks putih / primary tema hijau | 4,53 | Lolos (tipis) |
| Teks putih / primary tema biru-azure | 4,50 | Lolos (tepat di batas) |
| Teks putih / primary tema biru-aqua `#0891b2` | 3,68 | **Gagal** (usul primary `#0b7f9b`: 4,64) |
| Teks putih / primary tema indigo, marun | 6,29 | Lolos |
| Teks putih / sidebar kelima tema | 5,36 sampai 9,93 | Lolos |
| Link `--primary-600` / putih (kelima tema) | 4,58 sampai 7,58 | Lolos |
| Link mode gelap (`--primary-600`) / kartu gelap | 2,34 sampai 3,87 | **Gagal**, diperbaiki dengan `--text-brand` |
| Link mode gelap `--text-brand` (60% tema + 40% putih) | 5,15 sampai 6,96 (terburuk: marun) | Lolos |

Perbaikan yang diterapkan: `--text-muted` diperkuat, `--text-brand` ditambahkan (light = `--primary-600`, dark = campuran terang), `--bs-link-color` dan `.text-primary`/`.text-success` memakai `--text-brand`. Belum diperbaiki: primary tema biru-aqua untuk tombol (keputusan warna brand).

Untuk audit ulang: hitung rasio dari nilai di `resources/css/tokens.css` dan warna tema di `resources/views/app.blade.php`. Indikator fokus (ring hijau 3 px) diperiksa secara visual di Fase 2 setelah komponen form dikerjakan.
