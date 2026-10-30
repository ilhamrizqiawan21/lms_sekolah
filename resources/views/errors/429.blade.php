@include('errors.status', [
    'code' => 429,
    'title' => 'Terlalu banyak permintaan',
    'message' => 'Terlalu banyak permintaan dari perangkat ini. Tunggu sebentar, lalu coba lagi.',
    'primary' => '#0891b2',
    'primaryDark' => '#0e7490',
    'icon' => 'clock',
])
