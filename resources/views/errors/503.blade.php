@include('errors.status', [
    'code' => 503,
    'title' => 'Sistem sedang dalam pemeliharaan',
    'message' => 'Beberapa layanan sedang diperbaiki. Coba lagi sebentar.',
    'primary' => '#198754',
    'primaryDark' => '#166534',
    'icon' => 'tool',
])
