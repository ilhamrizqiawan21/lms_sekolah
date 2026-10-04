<!DOCTYPE html>
<html lang="id">
<head>
    @php
        $inertiaSchoolName = school_setting('school_name', 'Nama Sekolah');
        $inertiaAppName = 'LMS Sekolah';
        $inertiaLogoUrl = school_logo_url();
        $inertiaFaviconUrl = school_favicon_url();
        try {
            $inertiaTheme = \App\Models\Pengaturan::getValue('warna_tema', 'hijau');
        } catch (\Throwable) {
            $inertiaTheme = 'hijau';
        }
        $inertiaThemeColors = [
            'hijau' => [
                'primary' => '#198754',
                'secondary' => '#0d6efd',
                'sidebar' => '#166534',
                'navbar' => '#198754',
            ],
            'biru-azure' => [
                'primary' => '#0d6efd',
                'secondary' => '#22c55e',
                'sidebar' => '#1d4ed8',
                'navbar' => '#0d6efd',
            ],
            'biru-aqua' => [
                'primary' => '#0891b2',
                'secondary' => '#14b8a6',
                'sidebar' => '#0e7490',
                'navbar' => '#0891b2',
            ],
            'indigo' => [
                'primary' => '#4f46e5',
                'secondary' => '#06b6d4',
                'sidebar' => '#3730a3',
                'navbar' => '#4338ca',
            ],
            'marun' => [
                'primary' => '#be123c',
                'secondary' => '#f59e0b',
                'sidebar' => '#881337',
                'navbar' => '#be123c',
            ],
        ];
        $inertiaActiveTheme = $inertiaThemeColors[$inertiaTheme] ?? $inertiaThemeColors['hijau'];
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="application-name" content="{{ $inertiaAppName }}">
    <meta name="theme-color" content="{{ $inertiaActiveTheme['primary'] }}">
    <title inertia>{{ $inertiaAppName }} - {{ $inertiaSchoolName }}</title>
    <script>
        (() => {
            const key = 'lms.color-mode';
            const stored = (() => {
                try { return window.localStorage.getItem(key); } catch { return null; }
            })();
            // Halaman login selalu terang, apa pun preferensi tersimpan atau mode sistem.
            const forceLight = {{ request()->routeIs('login') || request()->is('login') ? 'true' : 'false' }};
            const mode = forceLight
                ? 'light'
                : (['light', 'dark'].includes(stored)
                    ? stored
                    : (window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
            if (forceLight) document.documentElement.setAttribute('data-force-light', '');
            document.documentElement.setAttribute('data-bs-theme', mode);
            document.documentElement.style.colorScheme = mode;
        })();
    </script>
    <link rel="icon" href="{{ $inertiaFaviconUrl }}">
    <style>
        :root {
            --app-primary: {{ $inertiaActiveTheme['primary'] }};
            --app-primary-dark: {{ $inertiaActiveTheme['sidebar'] }};
            --app-accent: {{ $inertiaActiveTheme['secondary'] }};
            --sidebar-bg: {{ $inertiaActiveTheme['sidebar'] }};
            --navbar-bg: {{ $inertiaActiveTheme['navbar'] }};
            --toast-success-bg: {{ $inertiaActiveTheme['primary'] }};
            /* Skala brand mengikuti tema pilihan admin */
            --primary-50: color-mix(in srgb, {{ $inertiaActiveTheme['primary'] }} 8%, white);
            --primary-100: color-mix(in srgb, {{ $inertiaActiveTheme['primary'] }} 18%, white);
            --primary-200: color-mix(in srgb, {{ $inertiaActiveTheme['primary'] }} 38%, white);
            --primary-300: color-mix(in srgb, {{ $inertiaActiveTheme['primary'] }} 70%, white);
            --primary-400: color-mix(in srgb, {{ $inertiaActiveTheme['primary'] }} 86%, white);
            --primary-500: {{ $inertiaActiveTheme['primary'] }};
            --primary-600: color-mix(in srgb, {{ $inertiaActiveTheme['primary'] }} 88%, black);
            --primary-700: color-mix(in srgb, {{ $inertiaActiveTheme['sidebar'] }} 78%, black);
            --primary-800: color-mix(in srgb, {{ $inertiaActiveTheme['sidebar'] }} 66%, black);
            --primary-900: color-mix(in srgb, {{ $inertiaActiveTheme['sidebar'] }} 52%, black);
            --shadow-green: 0 4px 16px color-mix(in srgb, {{ $inertiaActiveTheme['primary'] }} 24%, transparent);
            --focus-ring: 0 0 0 3px color-mix(in srgb, {{ $inertiaActiveTheme['primary'] }} 18%, transparent);
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/inertia.ts'])
    <link rel="stylesheet" href="{{ asset('css/login-isolation.css') }}">
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
