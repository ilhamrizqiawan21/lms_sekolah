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
            --bg: #f3f5fb;
            --surface: rgba(255, 255, 255, 0.78);
            --surface-solid: #ffffff;
            --text: #172033;
            --muted: #5b6b82;
            --border: rgba(255, 255, 255, 0.85);
            --ghost: #eef1f8;
            --shadow: 0 40px 80px -36px rgba(16, 24, 40, 0.3), 0 1px 2px rgba(16, 24, 40, 0.04);
        }

        :root[data-theme="dark"] {
            color-scheme: dark;
            --bg: #060a13;
            --surface: rgba(14, 21, 36, 0.7);
            --surface-solid: #0e1524;
            --text: #f1f5fb;
            --muted: #9fb0c5;
            --border: rgba(255, 255, 255, 0.09);
            --ghost: rgba(255, 255, 255, 0.07);
            --shadow: 0 40px 90px -30px rgba(0, 0, 0, 0.85);
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
            background:
                radial-gradient(46rem 30rem at -5% -10%, color-mix(in srgb, var(--primary) 24%, transparent), transparent 70%),
                radial-gradient(40rem 28rem at 105% 5%, rgba(99, 102, 241, 0.16), transparent 70%),
                radial-gradient(36rem 24rem at 60% 115%, rgba(14, 165, 233, 0.12), transparent 70%),
                var(--bg);
            background-attachment: fixed;
            color: var(--text);
            font-family: "Plus Jakarta Sans", "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            letter-spacing: -0.005em;
        }

        .notice {
            width: min(100%, 520px);
            padding: 44px 32px 36px;
            border: 1px solid var(--border);
            border-radius: 2rem;
            background: var(--surface);
            -webkit-backdrop-filter: blur(24px) saturate(170%);
            backdrop-filter: blur(24px) saturate(170%);
            box-shadow: var(--shadow);
            text-align: center;
        }

        .notice-code {
            margin: 0 0 6px;
            font-size: clamp(3.4rem, 14vw, 5rem);
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.06em;
            background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 45%, #312e81));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .notice-icon {
            width: 72px;
            height: 72px;
            display: inline-grid;
            place-items: center;
            margin: 10px 0 20px;
            border-radius: 1.4rem;
            background: color-mix(in srgb, var(--primary) 13%, transparent);
            color: var(--primary);
        }

        :root[data-theme="dark"] .notice-code {
            background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 55%, white), color-mix(in srgb, var(--primary) 70%, #a5b4fc));
            -webkit-background-clip: text;
            background-clip: text;
        }

        :root[data-theme="dark"] .notice-icon {
            color: color-mix(in srgb, var(--primary) 60%, white);
        }

        h1 {
            margin: 0 0 10px;
            font-size: 1.6rem;
            line-height: 1.3;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        p {
            margin: 0 auto;
            max-width: 38ch;
            color: var(--muted);
            font-size: 0.98rem;
            line-height: 1.7;
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
            border: 0;
            border-radius: 1rem;
            padding: 12px 20px;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.18s ease, filter 0.18s ease;
        }

        a {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #ffffff;
            box-shadow: 0 16px 30px -12px color-mix(in srgb, var(--primary) 80%, transparent);
        }

        a:hover,
        button:hover {
            transform: translateY(-1px);
            filter: brightness(1.06);
        }

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid color-mix(in srgb, var(--primary) 45%, transparent);
            outline-offset: 2px;
        }

        button {
            background: var(--ghost);
            color: var(--text);
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
