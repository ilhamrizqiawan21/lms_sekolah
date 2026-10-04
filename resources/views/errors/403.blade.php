@include('errors.status', [
    'code' => 403,
    'title' => 'Akses Ditolak',
    'message' => 'Anda tidak memiliki izin untuk mengakses halaman atau data tersebut.',
    'primary' => '#198754',
    'primaryDark' => '#166534',
    'icon' => 'lock',
])
