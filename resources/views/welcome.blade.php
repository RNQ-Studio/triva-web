<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">

    <title>TRIVA | Semua Kebutuhan Mobil dalam Satu Aplikasi</title>
    <meta
        name="description"
        content="TRIVA membantu Anda melakukan appraisal kendaraan, booking servis, simulasi kredit, dan estimasi perbaikan dari satu aplikasi."
    >
    <meta name="theme-color" content="#062a66">
    <link rel="canonical" href="{{ url('/') }}">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="TRIVA | Semua Kebutuhan Mobil dalam Satu Aplikasi">
    <meta
        property="og:description"
        content="Kelola kebutuhan kendaraan Anda dengan alur yang jelas, progres yang dapat dipantau, dan informasi yang transparan."
    >
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ asset('landing/triva-service-bay.jpg') }}">
    <meta property="og:image:alt" content="Kendaraan keluarga berwarna navy di area servis modern">

    <link rel="preload" as="image" href="{{ asset('landing/triva-service-bay.webp') }}" type="image/webp">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="apple-touch-icon-precomposed" sizes="180x180" href="{{ asset('apple-touch-icon-precomposed.png') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    @fonts

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite('resources/css/app.css')
    @endif

    <style>
        :root {
            --surface: #f5f7fa;
            --surface-elevated: #ffffff;
            --surface-muted: #eaf0f5;
            --surface-deep: #062a66;
            --text: #071d43;
            --text-soft: #52627a;
            --text-on-deep: #f7fbff;
            --line: #d8e0e9;
            --accent: #008078;
            --accent-strong: #006c65;
            --accent-soft: #d9f1ef;
            --shadow: 0 24px 70px rgba(6, 42, 102, 0.14);
            --radius-card: 18px;
            --radius-control: 12px;
            --content: 1240px;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --surface: #071323;
                --surface-elevated: #0d1d31;
                --surface-muted: #13263c;
                --surface-deep: #0a326f;
                --text: #eff6ff;
                --text-soft: #aebdd0;
                --text-on-deep: #f7fbff;
                --line: #263a52;
                --accent: #25b8ad;
                --accent-strong: #4ccbc2;
                --accent-soft: #123e43;
                --shadow: 0 24px 70px rgba(0, 0, 0, 0.34);
            }
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: var(--surface);
            color: var(--text);
            font-family: "Instrument Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 16px;
            line-height: 1.5;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
        }

        body,
        a,
        button,
        summary {
            -webkit-tap-highlight-color: transparent;
        }

        img {
            display: block;
            max-width: 100%;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        a:focus-visible,
        summary:focus-visible {
            outline: 3px solid var(--accent);
            outline-offset: 4px;
        }

        .container {
            width: min(calc(100% - 40px), var(--content));
            margin-inline: auto;
        }

        .site-nav {
            position: sticky;
            top: 0;
            z-index: 30;
            border-bottom: 1px solid color-mix(in srgb, var(--line) 78%, transparent);
            background: color-mix(in srgb, var(--surface) 88%, transparent);
            -webkit-backdrop-filter: blur(18px) saturate(150%);
            backdrop-filter: blur(18px) saturate(150%);
        }

        .nav-inner {
            min-height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            flex: 0 0 auto;
            padding: 6px 10px;
            border-radius: var(--radius-control);
            background: #f4f7fa;
            transition: transform 180ms ease;
        }

        .brand:hover {
            transform: translateY(-1px);
        }

        .brand img {
            width: 126px;
            height: 38px;
            object-fit: contain;
        }

        .desktop-nav {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 28px;
            white-space: nowrap;
        }

        .desktop-nav > a:not(.button) {
            color: var(--text-soft);
            font-size: 0.94rem;
            font-weight: 650;
            transition: color 180ms ease;
        }

        .desktop-nav > a:not(.button):hover {
            color: var(--text);
        }

        .button {
            display: inline-flex;
            min-height: 46px;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            border-radius: var(--radius-control);
            padding: 0 20px;
            font-size: 0.95rem;
            font-weight: 750;
            line-height: 1;
            white-space: nowrap;
            transition: transform 180ms ease, background-color 180ms ease, border-color 180ms ease;
        }

        .button:hover {
            transform: translateY(-2px);
        }

        .button:active {
            transform: translateY(0) scale(0.98);
        }

        .button-primary {
            background: var(--accent);
            color: #ffffff;
        }

        .button-primary:hover {
            background: var(--accent-strong);
        }

        .button-secondary {
            border-color: var(--line);
            background: var(--surface-elevated);
            color: var(--text);
        }

        .button-secondary:hover {
            border-color: var(--accent);
        }

        .mobile-menu {
            display: none;
            position: relative;
        }

        .mobile-menu summary {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--line);
            border-radius: var(--radius-control);
            padding: 0 15px;
            color: var(--text);
            font-weight: 700;
            cursor: pointer;
            list-style: none;
        }

        .mobile-menu summary::-webkit-details-marker {
            display: none;
        }

        .mobile-menu-panel {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: min(290px, calc(100vw - 40px));
            display: grid;
            gap: 4px;
            border: 1px solid var(--line);
            border-radius: var(--radius-card);
            padding: 10px;
            background: var(--surface-elevated);
            box-shadow: var(--shadow);
        }

        .mobile-menu-panel a {
            display: block;
            border-radius: 10px;
            padding: 12px;
            color: var(--text-soft);
            font-weight: 650;
        }

        .mobile-menu-panel a:hover {
            background: var(--surface-muted);
            color: var(--text);
        }

        .mobile-menu-panel .button {
            margin-top: 4px;
            color: #ffffff;
        }

        .hero {
            min-height: calc(100dvh - 72px);
            display: flex;
            align-items: center;
            overflow: clip;
            padding: clamp(44px, 7vh, 76px) 0 clamp(74px, 10vh, 110px);
        }

        .hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 0.9fr) minmax(520px, 1.1fr);
            align-items: center;
            gap: clamp(48px, 7vw, 96px);
        }

        .hero-copy {
            position: relative;
            z-index: 2;
        }

        .eyebrow {
            margin: 0 0 20px;
            color: var(--accent);
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .hero h1 {
            max-width: 720px;
            margin: 0;
            font-size: clamp(3.15rem, 5.6vw, 5.35rem);
            font-weight: 820;
            letter-spacing: -0.065em;
            line-height: 0.98;
        }

        .hero h1 span {
            color: var(--accent);
        }

        .hero-lede {
            max-width: 610px;
            margin: 26px 0 0;
            color: var(--text-soft);
            font-size: clamp(1.04rem, 1.4vw, 1.22rem);
            line-height: 1.65;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 34px;
        }

        .hero-visual {
            position: relative;
            height: min(62vh, 560px);
            min-height: 430px;
            border-radius: var(--radius-card);
            isolation: isolate;
        }

        .hero-photo {
            width: 100%;
            height: 100%;
            border-radius: inherit;
            object-fit: cover;
            object-position: center;
            box-shadow: var(--shadow);
        }

        .hero-visual::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 1;
            border: 1px solid rgba(255, 255, 255, 0.34);
            border-radius: inherit;
            background: linear-gradient(90deg, rgba(6, 42, 102, 0.42), transparent 46%);
            pointer-events: none;
        }

        .hero-phone {
            position: absolute;
            bottom: -54px;
            left: -44px;
            z-index: 2;
            width: clamp(190px, 20vw, 244px);
            border-radius: var(--radius-card);
            box-shadow: 0 24px 64px rgba(3, 24, 58, 0.28);
            transform: rotate(-2deg);
        }

        .proof-strip {
            border-block: 1px solid var(--line);
            background: var(--surface-elevated);
        }

        .proof-grid {
            display: grid;
            grid-template-columns: 1.2fr repeat(3, 1fr);
            align-items: stretch;
        }

        .proof-item {
            display: flex;
            min-height: 112px;
            align-items: center;
            padding: 24px 28px;
            border-left: 1px solid var(--line);
        }

        .proof-item:first-child {
            border-left: 0;
            padding-left: 0;
        }

        .proof-item:last-child {
            padding-right: 0;
        }

        .proof-item p {
            margin: 0;
            color: var(--text-soft);
            font-size: 0.98rem;
        }

        .proof-item strong {
            display: block;
            margin-bottom: 4px;
            color: var(--text);
            font-size: 1.05rem;
        }

        .section {
            padding: clamp(84px, 10vw, 140px) 0;
        }

        .section-heading {
            max-width: 760px;
            margin-bottom: clamp(42px, 6vw, 72px);
        }

        .section-heading h2 {
            margin: 0;
            font-size: clamp(2.3rem, 4.2vw, 4rem);
            font-weight: 800;
            letter-spacing: -0.052em;
            line-height: 1.02;
        }

        .section-heading p {
            max-width: 640px;
            margin: 20px 0 0;
            color: var(--text-soft);
            font-size: 1.08rem;
            line-height: 1.65;
        }

        .services-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr 0.85fr;
            grid-template-rows: repeat(2, minmax(230px, auto));
            gap: 18px;
        }

        .service {
            position: relative;
            overflow: hidden;
            display: flex;
            min-height: 230px;
            flex-direction: column;
            justify-content: flex-end;
            border: 1px solid var(--line);
            border-radius: var(--radius-card);
            padding: clamp(24px, 3vw, 36px);
            background: var(--surface-elevated);
        }

        .service-main {
            grid-row: 1 / span 2;
            border-color: transparent;
            background:
                linear-gradient(180deg, transparent 25%, rgba(3, 28, 67, 0.9) 100%),
                url("{{ asset('landing/triva-service-bay.webp') }}") center / cover;
            color: var(--text-on-deep);
        }

        .service-teal {
            border-color: transparent;
            background: linear-gradient(145deg, #00776f, #075169);
            color: #f7fbff;
        }

        .service-navy {
            border-color: transparent;
            background: linear-gradient(145deg, #062a66, #103d7b);
            color: #f7fbff;
        }

        .service p {
            max-width: 34ch;
            margin: 0 0 12px;
            color: var(--text-soft);
            font-size: 0.94rem;
        }

        .service-main p,
        .service-teal p,
        .service-navy p {
            color: rgba(247, 251, 255, 0.78);
        }

        .service h3 {
            max-width: 13ch;
            margin: 0;
            font-size: clamp(1.45rem, 2.3vw, 2.25rem);
            letter-spacing: -0.035em;
            line-height: 1.08;
        }

        .service-main h3 {
            font-size: clamp(2.3rem, 4vw, 4rem);
        }

        .product-section {
            overflow: clip;
            background: var(--surface-elevated);
        }

        .screen-grid {
            display: grid;
            grid-template-columns: 1.08fr 0.92fr 0.92fr;
            align-items: start;
            gap: 22px;
        }

        .screen-card {
            margin: 0;
            border: 1px solid var(--line);
            border-radius: var(--radius-card);
            padding: 20px 20px 0;
            background: var(--surface-muted);
            overflow: hidden;
        }

        .screen-card:nth-child(2) {
            margin-top: 88px;
        }

        .screen-card:nth-child(3) {
            margin-top: 176px;
        }

        .screen-card img {
            width: 100%;
            max-height: 720px;
            object-fit: contain;
            object-position: top center;
        }

        .screen-card figcaption {
            padding: 18px 4px 20px;
            border-top: 1px solid var(--line);
        }

        .screen-card strong {
            display: block;
            margin-bottom: 4px;
            color: var(--text);
            font-size: 1.02rem;
        }

        .screen-card span {
            color: var(--text-soft);
            font-size: 0.9rem;
        }

        .journey {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            border-top: 1px solid var(--line);
        }

        .journey-item {
            min-height: 250px;
            padding: 34px clamp(24px, 4vw, 54px) 28px;
            border-left: 1px solid var(--line);
        }

        .journey-item:first-child {
            padding-left: 0;
            border-left: 0;
        }

        .journey-item:last-child {
            padding-right: 0;
        }

        .journey-kicker {
            display: block;
            margin-bottom: 58px;
            color: var(--accent);
            font-size: 0.9rem;
            font-weight: 750;
        }

        .journey h3 {
            margin: 0 0 12px;
            font-size: clamp(1.5rem, 2.2vw, 2rem);
            letter-spacing: -0.032em;
        }

        .journey p {
            max-width: 31ch;
            margin: 0;
            color: var(--text-soft);
        }

        .trust-section {
            padding: clamp(80px, 9vw, 120px) 0;
            background: var(--surface-muted);
        }

        .trust-grid {
            display: grid;
            grid-template-columns: 0.82fr 1.18fr;
            gap: clamp(50px, 8vw, 110px);
            align-items: start;
        }

        .trust-grid h2 {
            margin: 0;
            font-size: clamp(2.35rem, 4vw, 3.75rem);
            letter-spacing: -0.05em;
            line-height: 1.03;
        }

        .trust-list {
            border-top: 1px solid var(--line);
        }

        .trust-item {
            display: grid;
            grid-template-columns: minmax(170px, 0.7fr) 1.3fr;
            gap: 28px;
            padding: 26px 0;
            border-bottom: 1px solid var(--line);
        }

        .trust-item strong {
            font-size: 1.04rem;
        }

        .trust-item p {
            margin: 0;
            color: var(--text-soft);
        }

        .closing {
            padding: clamp(82px, 10vw, 132px) 0;
        }

        .closing-panel {
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-card);
            padding: clamp(42px, 8vw, 92px);
            background:
                radial-gradient(circle at 84% 18%, rgba(37, 184, 173, 0.28), transparent 34%),
                linear-gradient(145deg, #052455, #07366f);
            color: var(--text-on-deep);
        }

        .closing-panel::after {
            content: "";
            position: absolute;
            right: -86px;
            bottom: -136px;
            width: 340px;
            height: 340px;
            border: 52px solid rgba(37, 184, 173, 0.18);
            border-radius: 50%;
            pointer-events: none;
        }

        .closing-panel h2 {
            position: relative;
            z-index: 1;
            max-width: 760px;
            margin: 0;
            font-size: clamp(2.55rem, 5vw, 4.85rem);
            letter-spacing: -0.058em;
            line-height: 1;
        }

        .closing-panel p {
            position: relative;
            z-index: 1;
            max-width: 560px;
            margin: 22px 0 32px;
            color: rgba(247, 251, 255, 0.76);
            font-size: 1.08rem;
        }

        .closing-panel .button {
            position: relative;
            z-index: 1;
            background: #ffffff;
            color: #063369;
        }

        .closing-panel .button:hover {
            background: #e8fffd;
        }

        .site-footer {
            border-top: 1px solid var(--line);
            padding: 42px 0;
        }

        .footer-grid {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 36px;
        }

        .footer-brand {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: var(--radius-control);
            background: #f4f7fa;
        }

        .footer-brand img {
            width: 116px;
            height: 36px;
            object-fit: contain;
        }

        .footer-copy {
            max-width: 480px;
            margin: 16px 0 0;
            color: var(--text-soft);
            font-size: 0.92rem;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 12px 24px;
            color: var(--text-soft);
            font-size: 0.92rem;
            font-weight: 650;
        }

        .footer-links a:hover {
            color: var(--text);
        }

        .reveal {
            opacity: 1;
            transform: none;
        }

        @keyframes hero-enter {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes reveal-enter {
            from {
                opacity: 0;
                transform: translateY(28px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (prefers-reduced-motion: no-preference) {
            .hero-copy > * {
                animation: hero-enter 680ms cubic-bezier(0.16, 1, 0.3, 1) both;
            }

            .hero-copy > :nth-child(2) {
                animation-delay: 80ms;
            }

            .hero-copy > :nth-child(3) {
                animation-delay: 160ms;
            }

            .hero-copy > :nth-child(4) {
                animation-delay: 240ms;
            }

            .hero-visual {
                animation: hero-enter 820ms 140ms cubic-bezier(0.16, 1, 0.3, 1) both;
            }

            @supports (animation-timeline: view()) {
                .reveal {
                    animation: reveal-enter linear both;
                    animation-timeline: view();
                    animation-range: entry 12% cover 32%;
                }
            }
        }

        @media (max-width: 1060px) {
            .hero-grid {
                grid-template-columns: minmax(0, 1fr) minmax(430px, 0.95fr);
                gap: 46px;
            }

            .hero h1 {
                font-size: clamp(3.2rem, 6vw, 4.3rem);
            }

            .hero-phone {
                left: -26px;
            }

            .desktop-nav {
                gap: 18px;
            }

            .services-grid {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: minmax(340px, auto) repeat(2, minmax(210px, auto));
            }

            .service-main {
                grid-column: 1 / span 2;
                grid-row: auto;
            }
        }

        @media (max-width: 820px) {
            .container {
                width: min(calc(100% - 32px), var(--content));
            }

            .desktop-nav {
                display: none;
            }

            .mobile-menu {
                display: block;
            }

            .hero {
                min-height: auto;
                padding: 54px 0 104px;
            }

            .hero-grid {
                grid-template-columns: 1fr;
                gap: 50px;
            }

            .hero-copy {
                max-width: 660px;
            }

            .hero h1 {
                font-size: clamp(3.15rem, 12vw, 4.65rem);
            }

            .hero-visual {
                height: 500px;
                min-height: 0;
                margin-left: 34px;
            }

            .hero-phone {
                left: -34px;
            }

            .proof-grid {
                grid-template-columns: 1fr 1fr;
            }

            .proof-item,
            .proof-item:first-child,
            .proof-item:last-child {
                min-height: 100px;
                padding: 20px;
                border-left: 0;
                border-bottom: 1px solid var(--line);
            }

            .proof-item:nth-child(even) {
                border-left: 1px solid var(--line);
            }

            .proof-item:nth-last-child(-n + 2) {
                border-bottom: 0;
            }

            .screen-grid {
                width: calc(100vw - 16px);
                margin-left: calc((100vw - min(calc(100vw - 32px), var(--content))) / -2);
                display: flex;
                gap: 16px;
                overflow-x: auto;
                padding: 4px 16px 24px;
                scroll-padding-inline: 16px;
                scroll-snap-type: x mandatory;
            }

            .screen-card,
            .screen-card:nth-child(2),
            .screen-card:nth-child(3) {
                width: min(78vw, 420px);
                flex: 0 0 auto;
                margin-top: 0;
                scroll-snap-align: start;
            }

            .journey {
                grid-template-columns: 1fr;
            }

            .journey-item,
            .journey-item:first-child,
            .journey-item:last-child {
                min-height: auto;
                padding: 30px 0;
                border-left: 0;
                border-bottom: 1px solid var(--line);
            }

            .journey-kicker {
                margin-bottom: 28px;
            }

            .trust-grid {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                align-items: flex-start;
                flex-direction: column;
            }

            .footer-links {
                justify-content: flex-start;
            }
        }

        @media (max-width: 560px) {
            .container {
                width: min(calc(100% - 28px), var(--content));
            }

            .nav-inner {
                min-height: 66px;
            }

            .brand img {
                width: 112px;
                height: 34px;
            }

            .hero {
                padding-top: 42px;
            }

            .hero h1 {
                font-size: clamp(2.8rem, 13vw, 3.75rem);
            }

            .hero-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .button {
                width: 100%;
            }

            .hero-visual {
                height: 390px;
                margin-left: 18px;
            }

            .hero-phone {
                bottom: -48px;
                left: -18px;
                width: 172px;
            }

            .proof-grid {
                grid-template-columns: 1fr;
            }

            .proof-item,
            .proof-item:first-child,
            .proof-item:last-child,
            .proof-item:nth-child(even),
            .proof-item:nth-last-child(-n + 2) {
                min-height: auto;
                padding: 18px 0;
                border-left: 0;
                border-bottom: 1px solid var(--line);
            }

            .proof-item:last-child {
                border-bottom: 0;
            }

            .services-grid {
                grid-template-columns: 1fr;
                grid-template-rows: auto;
            }

            .service-main {
                grid-column: auto;
                min-height: 390px;
            }

            .service {
                min-height: 210px;
            }

            .trust-item {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .closing-panel {
                padding: 40px 24px;
            }

            .screen-grid {
                width: calc(100vw - 7px);
                margin-left: -7px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <nav class="site-nav" aria-label="Navigasi utama">
        <div class="container nav-inner">
            <a class="brand" href="/" aria-label="TRIVA beranda">
                <img src="{{ asset('landing/triva-logo.png') }}" alt="TRIVA" width="126" height="38">
            </a>

            <div class="desktop-nav">
                <a href="#layanan">Layanan</a>
                <a href="#pengalaman">Tampilan aplikasi</a>
                <a href="#cara-kerja">Cara kerja</a>
                <a href="{{ route('privacy-policy') }}">Privasi</a>
                <a class="button button-primary" href="https://triva.web.app/">Buka TRIVA</a>
            </div>

            <details class="mobile-menu">
                <summary>Menu</summary>
                <div class="mobile-menu-panel">
                    <a href="#layanan">Layanan</a>
                    <a href="#pengalaman">Tampilan aplikasi</a>
                    <a href="#cara-kerja">Cara kerja</a>
                    <a href="{{ route('privacy-policy') }}">Privasi</a>
                    <a class="button button-primary" href="https://triva.web.app/">Buka TRIVA</a>
                </div>
            </details>
        </div>
    </nav>

    <main>
        <section class="hero">
            <div class="container hero-grid">
                <div class="hero-copy">
                    <p class="eyebrow">Ekosistem layanan otomotif</p>
                    <h1>Mobil Anda, <span>satu aplikasi.</span></h1>
                    <p class="hero-lede">
                        Appraisal, booking servis, simulasi kredit, dan estimasi perbaikan dalam satu aplikasi yang transparan.
                    </p>
                    <div class="hero-actions">
                        <a class="button button-primary" href="https://triva.web.app/">Buka TRIVA</a>
                        <a class="button button-secondary" href="#layanan">Jelajahi layanan</a>
                    </div>
                </div>

                <div class="hero-visual">
                    <picture>
                        <source srcset="{{ asset('landing/triva-service-bay.webp') }}" type="image/webp">
                        <img
                            class="hero-photo"
                            src="{{ asset('landing/triva-service-bay.jpg') }}"
                            alt="Kendaraan keluarga berwarna navy di area servis modern"
                            width="1536"
                            height="1024"
                            fetchpriority="high"
                        >
                    </picture>
                    <img
                        class="hero-phone"
                        src="{{ asset('landing/app-home.webp') }}"
                        alt="Desain layar Beranda aplikasi TRIVA"
                        width="720"
                        height="1518"
                        fetchpriority="high"
                    >
                </div>
            </div>
        </section>

        <section class="proof-strip" aria-label="Keunggulan TRIVA">
            <div class="container proof-grid">
                <div class="proof-item">
                    <p><strong>Satu profil kendaraan</strong>Dipakai ulang di seluruh layanan.</p>
                </div>
                <div class="proof-item">
                    <p><strong>Status yang jelas</strong>Pantau progres dari aplikasi.</p>
                </div>
                <div class="proof-item">
                    <p><strong>Keputusan transparan</strong>Asumsi dan disclaimer selalu terlihat.</p>
                </div>
                <div class="proof-item">
                    <p><strong>Masuk dengan Google</strong>Akses ringkas tanpa password tambahan.</p>
                </div>
            </div>
        </section>

        <section id="layanan" class="section">
            <div class="container">
                <header class="section-heading reveal">
                    <h2>Lima layanan untuk perjalanan kendaraan Anda.</h2>
                    <p>
                        Mulai dari mengetahui nilai mobil hingga merawat dan membiayai kendaraan berikutnya, semua berada dalam alur yang konsisten.
                    </p>
                </header>

                <div class="services-grid reveal">
                    <article class="service service-main">
                        <p>Nilai kendaraan berbasis data dan tinjauan kondisi.</p>
                        <h3>Trade-in Appraisal</h3>
                    </article>
                    <article class="service service-teal">
                        <p>Ajukan preferensi workshop atau layanan rumah.</p>
                        <h3>Booking Toyota</h3>
                    </article>
                    <article class="service">
                        <p>Temukan alur servis untuk kendaraan non-Toyota.</p>
                        <h3>Booking OtoXpert</h3>
                    </article>
                    <article class="service">
                        <p>Bandingkan skenario pembiayaan dengan input yang jelas.</p>
                        <h3>Simulasi Kredit</h3>
                    </article>
                    <article class="service service-navy">
                        <p>Kirim foto kerusakan dan ikuti proses estimasi.</p>
                        <h3>Estimasi Body &amp; Paint</h3>
                    </article>
                </div>
            </div>
        </section>

        <section id="pengalaman" class="section product-section">
            <div class="container">
                <header class="section-heading reveal">
                    <h2>Dibuat agar setiap progres mudah dipahami.</h2>
                    <p>
                        Desain aplikasi menempatkan tindakan utama, status, dan informasi keputusan dalam hierarki yang nyaman dibaca.
                    </p>
                </header>

                <div class="screen-grid reveal">
                    <figure class="screen-card">
                        <img
                            src="{{ asset('landing/app-home.webp') }}"
                            alt="Desain layar Beranda TRIVA dengan lima layanan utama"
                            width="720"
                            height="1518"
                            loading="lazy"
                        >
                        <figcaption>
                            <strong>Beranda yang terarah</strong>
                            <span>Lima layanan utama dan aktivitas terkini berada dalam satu pandangan.</span>
                        </figcaption>
                    </figure>

                    <figure class="screen-card">
                        <img
                            src="{{ asset('landing/appraisal-result.webp') }}"
                            alt="Desain layar hasil appraisal kendaraan di TRIVA"
                            width="720"
                            height="1518"
                            loading="lazy"
                        >
                        <figcaption>
                            <strong>Hasil yang dapat ditelusuri</strong>
                            <span>Kisaran harga, pembanding, masa berlaku, dan disclaimer tampil bersama.</span>
                        </figcaption>
                    </figure>

                    <figure class="screen-card">
                        <img
                            src="{{ asset('landing/booking-status.webp') }}"
                            alt="Desain layar status permintaan booking servis di TRIVA"
                            width="720"
                            height="1518"
                            loading="lazy"
                        >
                        <figcaption>
                            <strong>Status tanpa asumsi</strong>
                            <span>Preferensi jadwal tetap dibedakan dari booking yang sudah dikonfirmasi.</span>
                        </figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <section id="cara-kerja" class="section">
            <div class="container">
                <header class="section-heading reveal">
                    <h2>Dari kebutuhan sampai keputusan.</h2>
                    <p>
                        TRIVA menjaga konteks kendaraan Anda agar setiap layanan dimulai dengan informasi yang relevan.
                    </p>
                </header>

                <div class="journey reveal">
                    <article class="journey-item">
                        <span class="journey-kicker">Simpan kendaraan</span>
                        <h3>Mulai dari data yang sama.</h3>
                        <p>Profil kendaraan dapat dipakai kembali tanpa mengulang seluruh input pada setiap layanan.</p>
                    </article>
                    <article class="journey-item">
                        <span class="journey-kicker">Pilih layanan</span>
                        <h3>Ikuti alur yang relevan.</h3>
                        <p>Setiap layanan menampilkan data, consent, dan disclaimer sesuai kebutuhan keputusan Anda.</p>
                    </article>
                    <article class="journey-item">
                        <span class="journey-kicker">Pantau progres</span>
                        <h3>Ketahui apa yang terjadi.</h3>
                        <p>Status, tindakan berikutnya, dan hasil akhir tetap tersedia meski notifikasi terlambat.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="trust-section">
            <div class="container trust-grid reveal">
                <h2>Informasi jujur untuk keputusan yang lebih tenang.</h2>
                <div class="trust-list">
                    <article class="trust-item">
                        <strong>Hasil tetap indikatif</strong>
                        <p>Appraisal dan estimasi menjelaskan asumsi, sumber, serta kebutuhan inspeksi sebelum menjadi penawaran final.</p>
                    </article>
                    <article class="trust-item">
                        <strong>Jadwal tidak dianggap final</strong>
                        <p>Preferensi servis baru berstatus terkonfirmasi setelah petugas atau sistem partner menyetujuinya.</p>
                    </article>
                    <article class="trust-item">
                        <strong>Data tetap terlindungi</strong>
                        <p>Akses akun memakai Google dan foto kendaraan disimpan melalui jalur akses yang terbatas.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="closing">
            <div class="container">
                <div class="closing-panel reveal">
                    <h2>Kebutuhan mobil Anda, kini lebih terhubung.</h2>
                    <p>
                        Masuk ke TRIVA dan mulai dari kendaraan yang ingin Anda appraisal, servis, biayai, atau perbaiki.
                    </p>
                    <a class="button" href="https://triva.web.app/">Buka TRIVA</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-grid">
            <div>
                <a class="footer-brand" href="/" aria-label="TRIVA beranda">
                    <img src="{{ asset('landing/triva-logo.png') }}" alt="TRIVA" width="116" height="36" loading="lazy">
                </a>
                <p class="footer-copy">
                    Platform layanan otomotif yang menghubungkan data kendaraan, proses layanan, dan keputusan pelanggan.
                </p>
            </div>
            <div class="footer-links">
                <a href="{{ route('privacy-policy') }}">Kebijakan Privasi</a>
                <a href="{{ route('account-deletion') }}">Penghapusan Akun</a>
                <a href="{{ route('public.articles.index') }}">Artikel</a>
                <span>&copy; {{ date('Y') }} TRIVA</span>
            </div>
        </div>
    </footer>
</body>
</html>
