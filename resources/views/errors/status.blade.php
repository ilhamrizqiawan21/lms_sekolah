@php
    $code = $code ?? 500;
    $title = $title ?? 'Terjadi kesalahan sistem';
    $message = $message ?? 'Silakan coba lagi beberapa saat lagi.';
    $primary = $primary ?? '#198754';
    $primaryDark = $primaryDark ?? '#166534';
    $icon = $icon ?? 'alert';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }}</title>
    <script>
        (function () {
            try {
                var mode = localStorage.getItem('lms.color-mode');
                if (mode !== 'light' && mode !== 'dark') {
                    mode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', mode);
            } catch (e) {}
        })();
    </script>
    <style>
        :root {
            color-scheme: light;
            --primary: {{ $primary }};
            --primary-dark: {{ $primaryDark }};
            --bg: #f8f8f5;
            --surface: #ffffff;
            --text: #1a1e24;
            --muted: #5e6878;
            --border: #e2e5eb;
            --ghost: #f1f3f7;
            --ghost-hover: #e5e8ef;
            --shadow: 0 4px 16px -4px rgba(20, 22, 26, 0.08);
        }

        :root[data-theme="dark"] {
            color-scheme: dark;
            --bg: #101317;
            --surface: #181c22;
            --text: #eaedf2;
            --muted: #959fae;
            --border: #282e38;
            --ghost: #202630;
            --ghost-hover: #29303c;
            --shadow: 0 4px 16px -4px rgba(0, 0, 0, 0.4);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background-color: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            letter-spacing: normal;
        }

        .notice {
            width: min(100%, 480px);
            padding: 40px 32px 36px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background-color: var(--surface);
            box-shadow: var(--shadow);
            text-align: center;
        }

        .notice-code {
            margin: 0 0 8px;
            font-size: clamp(3rem, 10vw, 4rem);
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.02em;
            color: var(--primary);
            font-variant-numeric: tabular-nums;
        }

        .notice-icon {
            width: 56px;
            height: 56px;
            display: inline-grid;
            place-items: center;
            margin: 8px 0 16px;
            border-radius: 10px;
            background: color-mix(in srgb, var(--primary) 12%, transparent);
            color: var(--primary);
        }

        h1 {
            margin: 0 0 10px;
            font-size: 1.35rem;
            line-height: 1.35;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: var(--text);
        }

        p {
            margin: 0 auto;
            max-width: 38ch;
            color: var(--muted);
            font-size: 0.925rem;
            line-height: 1.6;
        }

        .actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 28px;
            flex-wrap: wrap;
        }

        a,
        button {
            appearance: none;
            border: 1px solid transparent;
            border-radius: 8px;
            padding: 10px 18px;
            font: inherit;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }

        a {
            background-color: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
        }

        a:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        button {
            background-color: var(--ghost);
            border-color: var(--border);
            color: var(--text);
        }

        button:hover {
            background-color: var(--ghost-hover);
        }

        a:focus-visible,
        button:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        @media (prefers-reduced-motion: reduce) {
            a, button { transition: none; }
        }
    </style>
</head>
<body>
    <main class="notice" role="main" aria-labelledby="notice-title">
        <div class="notice-code">{{ $code }}</div>
        <div class="notice-icon" aria-hidden="true">
            @if($icon === 'search')
                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" role="img" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9.8 5.2a7 7 0 1 1-2.5 2.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M4 4l5.8 5.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M11 11h4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M11 14h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            @elseif($icon === 'clock')
                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" role="img" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.8"/>
                </svg>
            @elseif($icon === 'lock')
                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" role="img" xmlns="http://www.w3.org/2000/svg">
                    <path d="M7 11V8a5 5 0 0 1 10 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M6.5 11h11A1.5 1.5 0 0 1 19 12.5v6A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-6A1.5 1.5 0 0 1 6.5 11Z" stroke="currentColor" stroke-width="1.8"/>
                </svg>
            @elseif($icon === 'tool')
                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" role="img" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14.7 6.3a4.5 4.5 0 0 0-5.1 5.8L4.9 16.8a2 2 0 0 0 2.8 2.8l4.7-4.7a4.5 4.5 0 0 0 5.8-5.1l-2.8 2.8-2-2 2.8-2.8Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M7.4 17.4h.01" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                </svg>
            @else
                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" role="img" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 8v5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                    <path d="M12 16.5h.01" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                    <path d="M10.4 4.8 3.2 17.4A2 2 0 0 0 4.9 20h14.2a2 2 0 0 0 1.7-2.6L13.6 4.8a1.85 1.85 0 0 0-3.2 0Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                </svg>
            @endif
        </div>
        <h1 id="notice-title">{{ $title }}</h1>
        <p>{{ $message }}</p>
        <div class="actions">
            <a href="{{ url('/') }}">Ke Halaman Utama</a>
            <button type="button" onclick="history.back()">Kembali</button>
        </div>
    </main>
</body>
</html>
