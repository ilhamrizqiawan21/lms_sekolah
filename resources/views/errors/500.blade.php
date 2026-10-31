@include('errors.status', [
    'code' => 500,
    'title' => 'Terjadi kesalahan sistem',
    'message' => 'LMS belum dapat menyelesaikan permintaan ini. Coba muat ulang; jika tetap muncul, hubungi administrator sekolah.',
    'primary' => '#dc2626',
    'primaryDark' => '#991b1b',
    'icon' => 'alert',
])
