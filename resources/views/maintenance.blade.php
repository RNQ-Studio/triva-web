<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $title }} | TRIVA</title>
    <meta name="description" content="{{ $message }}">
    <meta name="theme-color" content="#062a66">

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    {{--
        Halaman ini sengaja berdiri sendiri: tanpa @vite, tanpa @fonts, dan
        tanpa query database. Ia justru harus tampil pada saat bagian lain
        sistem sedang tidak sehat.
    --}}
    <style>
        :root {
            --surface: #f5f7fa;
            --surface-elevated: #ffffff;
            --surface-deep: #062a66;
            --text: #071d43;
            --text-soft: #52627a;
            --line: #d8e0e9;
            --accent: #008078;
            --accent-soft: #d9f1ef;
            --shadow: 0 24px 70px rgba(6, 42, 102, 0.14);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --surface: #071323;
                --surface-elevated: #0d1d31;
                --surface-deep: #0a326f;
                --text: #eff6ff;
                --text-soft: #aebdd0;
                --line: #263a52;
                --accent: #25b8ad;
                --accent-soft: #123e43;
                --shadow: 0 24px 70px rgba(0, 0, 0, 0.34);
            }
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: var(--surface);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        .card {
            width: 100%;
            max-width: 560px;
            padding: 40px 32px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: var(--surface-elevated);
            box-shadow: var(--shadow);
            text-align: center;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }

        .icon {
            margin: 24px auto 0;
            width: 72px;
            height: 72px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--surface-deep);
            color: #f7fbff;
        }

        h1 {
            margin: 20px 0 0;
            font-size: 1.6rem;
            line-height: 1.3;
            letter-spacing: -0.01em;
        }

        p {
            margin: 12px 0 0;
            color: var(--text-soft);
        }

        .until {
            margin-top: 24px;
            padding: 14px 18px;
            border: 1px solid var(--line);
            border-radius: 12px;
            font-size: 0.95rem;
        }

        .until strong {
            color: var(--text);
        }

        .brand {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
            font-size: 0.85rem;
            color: var(--text-soft);
        }

        @media (max-width: 480px) {
            .card {
                padding: 32px 22px;
            }

            h1 {
                font-size: 1.35rem;
            }
        }
    </style>
</head>
<body>
    <main class="card">
        <span class="badge"><span class="dot"></span>Status Layanan</span>

        <div class="icon" aria-hidden="true">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
            </svg>
        </div>

        <h1>{{ $title }}</h1>

        <p>{{ $message }}</p>

        @if ($until !== null)
            <div class="until">
                Diperkirakan kembali normal pada
                <strong>{{ $until->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') }} WIB</strong>
            </div>
        @endif

        <div class="brand">TRIVA &middot; Terima kasih atas kesabaran Anda.</div>
    </main>
</body>
</html>
