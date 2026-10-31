@php
    $code = $code ?? 500;
    $title = $title ?? 'Terjadi kesalahan sistem';
    $message = $message ?? 'Coba lagi sebentar.';
    $primary = $primary ?? '#198754';
    $primaryDark = $primaryDark ?? '#166534';
    $icon = $icon ?? 'alert';
    $isNotFound = (int) $code === 404;
    $isServerError = (int) $code >= 500;
    $eyebrow = $isNotFound ? 'Tautan tidak tersedia' : ($isServerError ? 'Layanan sedang terganggu' : 'Permintaan tidak dapat diproses');
    $codeLabel = $isNotFound ? 'Tidak ditemukan' : ($isServerError ? 'Butuh perhatian' : 'Akses berhenti');
    $schoolName = 'LMS Sekolah';
    $logoUrl = null;
    try {
        $schoolName = school_setting('school_name', 'LMS Sekolah');
        $logoUrl = school_logo_url();
    } catch (Throwable) {
        // Error page harus tetap bisa dirender ketika database atau storage sedang bermasalah.
    }
    $path = request()->getPathInfo() ?: '/';
    $occurredAt = now()->format('d M Y, H:i');
    $cspNonce = request()->attributes->get('csp_nonce');
    $showDebugDetails = config('app.debug') && ! empty($exceptionDetails);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="{{ $primary }}">
    <title>{{ $code }} · {{ $title }} · {{ $schoolName }}</title>
    <script @if($cspNonce) nonce="{{ $cspNonce }}" @endif>
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
    <style @if($cspNonce) nonce="{{ $cspNonce }}" @endif>
        :root {
            color-scheme: light;
            --primary: {{ $primary }};
            --primary-dark: {{ $primaryDark }};
            --bg: #f5f7fb;
            --surface: #ffffff;
            --surface-soft: #f8fafc;
            --text: #172033;
            --muted: #627086;
            --border: #e3e8f0;
            --ghost: #eef2f7;
            --ghost-hover: #e4eaf2;
            --shadow: 0 24px 70px rgba(28, 39, 58, 0.12);
            --shadow-soft: 0 8px 24px rgba(28, 39, 58, 0.07);
        }

        :root[data-theme="dark"] {
            color-scheme: dark;
            --bg: #0e131b;
            --surface: #171e29;
            --surface-soft: #1d2633;
            --text: #edf2f8;
            --muted: #a2afc0;
            --border: #2b3747;
            --ghost: #222d3c;
            --ghost-hover: #2d3949;
            --shadow: 0 28px 80px rgba(0, 0, 0, 0.35);
            --shadow-soft: 0 8px 24px rgba(0, 0, 0, 0.2);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 28px;
            background:
                radial-gradient(circle at 8% 8%, color-mix(in srgb, var(--primary) 10%, transparent), transparent 30%),
                radial-gradient(circle at 92% 92%, color-mix(in srgb, var(--primary) 8%, transparent), transparent 32%),
                var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            letter-spacing: normal;
        }

        .error-shell { width: min(100%, 1040px); margin: 0 auto; }

        .brand-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 22px;
        }

        .brand-link {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            min-height: 44px;
            color: var(--text);
            text-decoration: none;
        }

        .brand-link img {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: var(--shadow-soft);
        }

        .brand-copy { display: grid; gap: 2px; }
        .brand-copy strong { font-size: 0.98rem; line-height: 1.2; }
        .brand-copy small { color: var(--muted); font-size: 0.75rem; }

        .status-pill {
            display: inline-flex;
            align-items: center;
            min-height: 32px;
            padding: 6px 11px;
            border: 1px solid color-mix(in srgb, var(--primary) 28%, var(--border));
            border-radius: 999px;
            background: color-mix(in srgb, var(--primary) 9%, var(--surface));
            color: var(--primary-dark);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
        }

        :root[data-theme="dark"] .status-pill { color: var(--primary); }

        .error-card {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(300px, 0.92fr);
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 26px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .error-content { padding: clamp(30px, 6vw, 66px); }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
            color: var(--primary-dark);
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        :root[data-theme="dark"] .eyebrow { color: var(--primary); }

        .eyebrow-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary);
            box-shadow: 0 0 0 5px color-mix(in srgb, var(--primary) 14%, transparent);
        }

        .code-line { display: flex; align-items: baseline; gap: 14px; margin-bottom: 10px; }

        .notice-code {
            margin: 0;
            color: var(--primary);
            font-size: clamp(4.4rem, 12vw, 7.6rem);
            font-weight: 850;
            line-height: 0.86;
            letter-spacing: -0.08em;
            font-variant-numeric: tabular-nums;
        }

        .code-label { color: var(--muted); font-size: 0.84rem; font-weight: 700; }

        h1 {
            max-width: 560px;
            margin: 0 0 12px;
            color: var(--text);
            font-size: clamp(1.6rem, 3vw, 2.25rem);
            line-height: 1.16;
            letter-spacing: -0.04em;
        }

        .lead { max-width: 54ch; margin: 0; color: var(--muted); font-size: 1rem; line-height: 1.7; }

        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 30px; }

        a, button {
            appearance: none;
            min-height: 44px;
            border: 1px solid transparent;
            border-radius: 11px;
            padding: 10px 17px;
            font: inherit;
            font-size: 0.9rem;
            font-weight: 750;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.15s ease, background-color 0.15s ease, border-color 0.15s ease;
        }

        .button-primary {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 7px 16px color-mix(in srgb, var(--primary) 22%, transparent);
        }

        .button-primary:hover { background: var(--primary-dark); border-color: var(--primary-dark); transform: translateY(-1px); }

        .button-secondary { background: var(--ghost); border-color: var(--border); color: var(--text); }
        .button-secondary:hover { background: var(--ghost-hover); transform: translateY(-1px); }

        a:focus-visible, button:focus-visible {
            outline: 3px solid color-mix(in srgb, var(--primary) 42%, transparent);
            outline-offset: 3px;
        }

        .tip {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            max-width: 560px;
            margin-top: 34px;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--surface-soft);
        }

        .tip-icon {
            display: grid;
            flex: 0 0 30px;
            width: 30px;
            height: 30px;
            place-items: center;
            border-radius: 9px;
            background: color-mix(in srgb, var(--primary) 13%, transparent);
            color: var(--primary);
        }

        .tip strong, .tip span { display: block; }
        .tip strong { margin-bottom: 3px; font-size: 0.82rem; }
        .tip span { color: var(--muted); font-size: 0.79rem; line-height: 1.5; }

        .visual-panel {
            position: relative;
            display: grid;
            min-height: 430px;
            place-items: center;
            overflow: hidden;
            padding: 32px;
            background: linear-gradient(145deg, color-mix(in srgb, var(--primary) 88%, #172033), color-mix(in srgb, var(--primary-dark) 92%, #101827));
        }

        .visual-panel::before, .visual-panel::after {
            position: absolute;
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 50%;
            content: "";
        }

        .visual-panel::before { width: 410px; height: 410px; transform: translate(19%, -25%); }
        .visual-panel::after { width: 260px; height: 260px; transform: translate(-40%, 36%); }

        .illustration {
            position: relative;
            z-index: 1;
            width: min(100%, 330px);
            padding: 24px;
            border: 1px solid rgba(255,255,255,0.22);
            border-radius: 22px;
            background: rgba(255,255,255,0.12);
            box-shadow: 0 20px 50px rgba(0,0,0,0.16);
            color: #ffffff;
            backdrop-filter: blur(8px);
        }

        .illustration-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .illustration-dots { display: flex; gap: 6px; }
        .illustration-dots i { width: 7px; height: 7px; border-radius: 50%; background: rgba(255,255,255,0.64); }
        .illustration-line { width: 72px; height: 8px; border-radius: 999px; background: rgba(255,255,255,0.24); }
        .illustration-code { margin: 0; font-size: clamp(4.3rem, 11vw, 6.6rem); font-weight: 850; line-height: 0.9; letter-spacing: -0.09em; }
        .illustration-copy { width: 72%; height: 9px; margin-top: 19px; border-radius: 999px; background: rgba(255,255,255,0.38); }
        .illustration-copy.short { width: 48%; margin-top: 9px; background: rgba(255,255,255,0.21); }
        .illustration-bars { display: flex; align-items: end; gap: 8px; height: 74px; margin-top: 28px; }
        .illustration-bars i { flex: 1; border-radius: 6px 6px 2px 2px; background: rgba(255,255,255,0.26); }
        .illustration-bars i:nth-child(1) { height: 38%; }
        .illustration-bars i:nth-child(2) { height: 72%; background: rgba(255,255,255,0.48); }
        .illustration-bars i:nth-child(3) { height: 52%; }
        .illustration-bars i:nth-child(4) { height: 88%; background: rgba(255,255,255,0.7); }
        .illustration-bars i:nth-child(5) { height: 62%; }

        .details {
            grid-column: 1 / -1;
            padding: 22px clamp(30px, 6vw, 66px) 26px;
            border-top: 1px solid var(--border);
            background: var(--surface-soft);
        }

        .details h2 { margin: 0 0 14px; font-size: 0.9rem; letter-spacing: -0.01em; }
        .detail-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin: 0; }
        .detail-item { min-width: 0; }
        .detail-item dt { margin-bottom: 5px; color: var(--muted); font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; }
        .detail-item dd { margin: 0; overflow: hidden; color: var(--text); font-size: 0.82rem; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
        .detail-item code { color: var(--primary-dark); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.78rem; }
        :root[data-theme="dark"] .detail-item code { color: var(--primary); }

        .debug-details { margin-top: 18px; border-top: 1px dashed var(--border); padding-top: 14px; }
        .debug-details summary { color: var(--muted); cursor: pointer; font-size: 0.78rem; font-weight: 700; }
        .debug-details pre { max-height: 180px; overflow: auto; margin: 10px 0 0; padding: 12px; border-radius: 10px; background: var(--ghost); color: var(--text); font: 0.75rem/1.5 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; white-space: pre-wrap; word-break: break-word; }

        .footer-note { margin: 17px 0 0; color: var(--muted); font-size: 0.74rem; text-align: center; }

        @media (max-width: 760px) {
            body { padding: 16px; }
            .brand-bar { margin-bottom: 16px; }
            .brand-copy small { display: none; }
            .error-card { grid-template-columns: 1fr; border-radius: 21px; }
            .visual-panel { order: -1; min-height: 230px; padding: 22px; }
            .illustration { width: min(100%, 260px); padding: 17px; }
            .illustration-top { margin-bottom: 15px; }
            .illustration-code { font-size: 4.7rem; }
            .illustration-bars { height: 48px; margin-top: 18px; }
            .error-content { padding: 31px 24px 27px; }
            .notice-code { font-size: 5rem; }
            .details { padding: 20px 24px 23px; }
            .detail-grid { grid-template-columns: 1fr; gap: 11px; }
        }

        @media (max-width: 430px) {
            .status-pill { padding-inline: 9px; font-size: 0.65rem; }
            .brand-link img { width: 38px; height: 38px; }
            .code-line { gap: 10px; }
            .code-label { font-size: 0.75rem; }
            .actions a, .actions button { width: 100%; text-align: center; }
        }

        @media (prefers-reduced-motion: reduce) {
            a, button { transition: none; }
        }
    </style>
</head>
<body>
    <main class="error-shell" aria-labelledby="error-title">
        <header class="brand-bar">
            <a class="brand-link" href="{{ url('/') }}" aria-label="Kembali ke halaman utama {{ $schoolName }}">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="Logo {{ $schoolName }}">
                @endif
                <span class="brand-copy">
                    <strong>{{ $schoolName }}</strong>
                    <small>Learning Management System</small>
                </span>
            </a>
            <span class="status-pill" aria-label="Kode status {{ $code }}">STATUS {{ $code }}</span>
        </header>

        <section class="error-card">
            <div class="error-content">
                <div class="eyebrow"><span class="eyebrow-dot" aria-hidden="true"></span>{{ $eyebrow }}</div>
                <div class="code-line">
                    <div class="notice-code" aria-hidden="true">{{ $code }}</div>
                    <span class="code-label">{{ $codeLabel }}</span>
                </div>
                <h1 id="error-title">{{ $title }}</h1>
                <p class="lead">{{ $message }}</p>

                <div class="actions" aria-label="Aksi halaman error">
                    <a class="button-primary" href="{{ url('/') }}">Ke halaman utama</a>
                    <button class="button-secondary" type="button" onclick="window.location.reload()">Muat ulang</button>
                    <button class="button-secondary" type="button" onclick="goBack()">Kembali</button>
                </div>

                <div class="tip">
                    <span class="tip-icon" aria-hidden="true">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 8v5m0 3.5h.01M10.4 4.8 3.2 17.4A2 2 0 0 0 4.9 20h14.2a2 2 0 0 0 1.7-2.6L13.6 4.8a1.85 1.85 0 0 0-3.2 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span>
                        <strong>{{ $isNotFound ? 'Periksa kembali alamat halaman' : 'Tidak perlu mengulang data Anda' }}</strong>
                        <span>{{ $isNotFound ? 'Gunakan menu utama atau kembali ke halaman sebelumnya untuk melanjutkan.' : 'Simpan alamat halaman ini jika perlu melapor kepada administrator sekolah.' }}</span>
                    </span>
                </div>
            </div>

            <div class="visual-panel" aria-hidden="true">
                <div class="illustration">
                    <div class="illustration-top"><span class="illustration-dots"><i></i><i></i><i></i></span><span class="illustration-line"></span></div>
                    <p class="illustration-code">{{ $code }}</p>
                    <div class="illustration-copy"></div>
                    <div class="illustration-copy short"></div>
                    <div class="illustration-bars"><i></i><i></i><i></i><i></i><i></i></div>
                </div>
            </div>

            <section class="details" aria-labelledby="details-title">
                <h2 id="details-title">Detail permintaan</h2>
                <dl class="detail-grid">
                    <div class="detail-item"><dt>Status</dt><dd>{{ $code }} · {{ $codeLabel }}</dd></div>
                    <div class="detail-item"><dt>Alamat</dt><dd><code>{{ $path }}</code></dd></div>
                    <div class="detail-item"><dt>Waktu</dt><dd>{{ $occurredAt }} · {{ strtoupper(request()->method()) }}</dd></div>
                </dl>
                @if($showDebugDetails)
                    <details class="debug-details">
                        <summary>Detail teknis (mode debug)</summary>
                        <pre>{{ $exceptionDetails }}</pre>
                    </details>
                @endif
            </section>
        </section>

        <p class="footer-note">Jika masalah terus berulang, hubungi administrator {{ $schoolName }} dan sertakan kode status {{ $code }}.</p>
    </main>
    <script @if($cspNonce) nonce="{{ $cspNonce }}" @endif>
        function goBack() {
            if (document.referrer && new URL(document.referrer).origin === window.location.origin) {
                window.history.back();
                return;
            }
            window.location.href = @json(url('/'));
        }
    </script>
</body>
</html>
