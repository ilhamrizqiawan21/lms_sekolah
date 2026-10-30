@include('errors.status', [
    'code' => 500,
    'title' => 'Terjadi kesalahan sistem',
    'message' => 'Ada kendala di server. Coba lagi sebentar.',
    'primary' => '#dc2626',
    'primaryDark' => '#991b1b',
    'icon' => 'alert',
])
