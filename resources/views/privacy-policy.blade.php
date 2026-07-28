<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">

    <title>Kebijakan Privasi TRIVA</title>
    <meta name="description" content="Kebijakan Privasi aplikasi TRIVA menjelaskan data yang dikumpulkan, tujuan penggunaan, pembagian, keamanan, penyimpanan, dan cara meminta penghapusan akun.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ route('privacy-policy') }}">

    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="apple-touch-icon-precomposed" sizes="180x180" href="{{ asset('apple-touch-icon-precomposed.png') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <meta name="theme-color" content="#062a66">

    @fonts

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <style>
        html {
            scroll-behavior: smooth;
            scroll-padding-top: 5.5rem;
        }

        body {
            background:
                radial-gradient(circle at 8% 0%, rgba(7, 88, 181, 0.09), transparent 30rem),
                linear-gradient(180deg, #f7f9fc 0, #ffffff 34rem);
            color: #0f172a;
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 0;
            overflow-x: hidden;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        img {
            display: block;
            max-width: 100%;
        }

        .privacy-header {
            backdrop-filter: blur(18px);
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid rgba(226, 232, 240, 0.9);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .privacy-header__inner {
            align-items: center;
            display: flex;
            height: 4.5rem;
            justify-content: space-between;
            margin: 0 auto;
            max-width: 80rem;
            padding: 0 2rem;
        }

        .privacy-brand {
            align-items: center;
            color: inherit;
            display: flex;
            gap: 0.8rem;
            min-width: 0;
            text-decoration: none;
        }

        .privacy-brand__logo {
            flex: 0 0 auto;
            height: 2.25rem;
            object-fit: contain;
            width: 4.5rem;
        }

        .privacy-brand__label {
            border-left: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 700;
            padding-left: 0.8rem;
        }

        .delete-shortcut {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            color: #1e40af;
            flex: 0 0 auto;
            font-size: 0.875rem;
            font-weight: 800;
            padding: 0.58rem 1rem;
            text-decoration: none;
            transition: background-color 160ms ease, border-color 160ms ease;
        }

        .delete-shortcut:hover {
            background: #dbeafe;
            border-color: #93c5fd;
        }

        .delete-shortcut__mobile {
            display: none;
        }

        .privacy-hero {
            border-bottom: 1px solid rgba(226, 232, 240, 0.9);
        }

        .privacy-container {
            margin: 0 auto;
            max-width: 80rem;
            padding-left: 2rem;
            padding-right: 2rem;
        }

        .privacy-hero__inner {
            padding-bottom: 4.25rem;
            padding-top: 4.25rem;
        }

        .privacy-hero__copy {
            max-width: 48rem;
        }

        .privacy-eyebrow {
            align-items: center;
            background: #ffffff;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            color: #1d4ed8;
            display: inline-flex;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            margin: 0 0 1.25rem;
            padding: 0.4rem 0.8rem;
            text-transform: uppercase;
        }

        .privacy-title {
            color: #020617;
            font-size: clamp(2.5rem, 4vw, 3.5rem);
            font-weight: 850;
            letter-spacing: -0.045em;
            line-height: 1.05;
            margin: 0;
        }

        .privacy-title span {
            color: #0758b5;
        }

        .privacy-intro {
            color: #475569;
            font-size: 1.08rem;
            line-height: 1.75;
            margin: 1.35rem 0 0;
            max-width: 46rem;
        }

        .policy-meta {
            align-items: center;
            color: #64748b;
            display: flex;
            flex-wrap: wrap;
            font-size: 0.875rem;
            gap: 0.5rem 1.5rem;
            margin-top: 1.6rem;
        }

        .policy-meta strong {
            color: #334155;
            font-weight: 750;
        }

        .policy-copy > section {
            margin-top: 3.5rem;
        }

        .policy-copy > section:first-child {
            margin-top: 0;
        }

        .policy-copy > section:not(#penghapusan-akun) {
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3.5rem;
        }

        .policy-copy h2 {
            color: #0f172a;
            font-size: clamp(1.4rem, 3vw, 1.75rem);
            font-weight: 750;
            letter-spacing: -0.025em;
            line-height: 1.3;
        }

        .policy-copy h3 {
            margin-top: 1.75rem;
            color: #1e293b;
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.5;
        }

        .policy-copy p {
            margin-top: 1rem;
            color: #475569;
            line-height: 1.8;
        }

        .policy-copy ul,
        .policy-copy ol {
            margin-top: 1rem;
            padding-left: 1.2rem;
            color: #475569;
        }

        .policy-copy ul {
            list-style: disc;
        }

        .policy-copy ol {
            list-style: decimal;
        }

        .policy-copy li {
            margin-top: 0.65rem;
            padding-left: 0.3rem;
            line-height: 1.7;
        }

        .policy-copy a:not(.policy-button) {
            color: #0758b5;
            font-weight: 650;
            text-decoration: underline;
            text-decoration-color: rgba(7, 88, 181, 0.28);
            text-underline-offset: 3px;
        }

        .policy-copy strong {
            color: #1e293b;
            font-weight: 700;
        }

        .policy-toc summary {
            align-items: center;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            list-style: none;
            min-height: 3.25rem;
            padding: 0.7rem 1rem;
        }

        .policy-toc summary::-webkit-details-marker {
            display: none;
        }

        .policy-toc-chevron {
            transition: transform 180ms ease;
        }

        .policy-toc[open] .policy-toc-chevron {
            transform: rotate(180deg);
        }

        .policy-toc:not([open]) > .policy-toc-links {
            display: none;
        }

        .commitment-strip {
            display: grid;
            gap: 0.9rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 2.5rem;
            scrollbar-width: none;
        }

        .commitment-strip::-webkit-scrollbar {
            display: none;
        }

        .commitment-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.07);
            min-width: 0;
            padding: 1.15rem 1.25rem;
        }

        .commitment-card span {
            display: block;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            line-height: 1.4;
            text-transform: uppercase;
        }

        .commitment-card:nth-child(1) span {
            color: #047857;
        }

        .commitment-card:nth-child(2) span {
            color: #1d4ed8;
        }

        .commitment-card:nth-child(3) span {
            color: #b45309;
        }

        .commitment-card p {
            color: #1e293b;
            font-size: 0.9rem;
            font-weight: 750;
            line-height: 1.55;
            margin: 0.55rem 0 0;
        }

        .policy-layout {
            display: grid;
            gap: 3.5rem;
            grid-template-columns: 17rem minmax(0, 1fr);
            margin: 0 auto;
            max-width: 80rem;
            padding: 5rem 2rem;
        }

        .policy-sidebar {
            align-self: start;
            position: sticky;
            top: 6rem;
        }

        .policy-toc {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.07);
            overflow: hidden;
        }

        .policy-toc-links {
            display: grid;
            gap: 0.18rem;
            padding: 0.75rem;
        }

        .policy-toc-links a {
            border-radius: 0.75rem;
            color: #475569;
            font-size: 0.875rem;
            font-weight: 650;
            line-height: 1.35;
            padding: 0.68rem 0.75rem;
            text-decoration: none;
            transition: background-color 150ms ease, color 150ms ease;
        }

        .policy-toc-links a:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .policy-toc-links a[href="#penghapusan-akun"] {
            background: #eff6ff;
            color: #1e40af;
        }

        .policy-copy {
            max-width: 48rem;
            min-width: 0;
            width: 100%;
        }

        .policy-copy > section > p:first-child {
            color: #1d4ed8;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            margin: 0;
            text-transform: uppercase;
        }

        .policy-copy h2 {
            margin: 0.7rem 0 0;
        }

        .deletion-card {
            background: #f3f8ff;
            border: 1px solid #bfdbfe;
            border-radius: 1.5rem;
            box-shadow: 0 1px 3px rgba(30, 64, 175, 0.08);
            padding: 2rem;
            scroll-margin-top: 7rem;
        }

        .policy-button {
            align-items: center;
            background: #0758b5;
            border-radius: 0.75rem;
            color: #ffffff !important;
            display: inline-flex;
            font-size: 0.875rem;
            font-weight: 800;
            justify-content: center;
            margin-top: 1.5rem;
            padding: 0.8rem 1.25rem;
            text-align: center;
            text-decoration: none !important;
        }

        .policy-button:hover {
            background: #064993;
        }

        .contact-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            margin-top: 1.25rem;
            padding: 1.4rem;
        }

        .contact-card p {
            overflow-wrap: anywhere;
        }

        .back-to-top {
            border-top: 1px solid #e2e8f0;
            margin-top: 3.5rem;
            padding-top: 2rem;
        }

        .privacy-footer {
            background: #020617;
            border-top: 1px solid #1e293b;
            color: #cbd5e1;
        }

        .privacy-footer__inner {
            align-items: center;
            display: flex;
            gap: 2rem;
            justify-content: space-between;
            margin: 0 auto;
            max-width: 80rem;
            padding: 1.75rem 2rem;
        }

        .privacy-footer__brand {
            align-items: center;
            display: flex;
            gap: 0.8rem;
            min-width: 0;
        }

        .privacy-footer__logo-box {
            align-items: center;
            background: #ffffff;
            border-radius: 0.6rem;
            display: inline-flex;
            flex: 0 0 auto;
            height: 2.25rem;
            justify-content: center;
            overflow: hidden;
            padding: 0.3rem 0.55rem;
            width: 5.25rem;
        }

        .privacy-footer__logo {
            height: auto;
            max-height: 1.6rem;
            object-fit: contain;
            width: 4.3rem;
        }

        .privacy-footer__copyright {
            color: #cbd5e1;
            font-size: 0.875rem;
            font-weight: 650;
            margin: 0;
            white-space: nowrap;
        }

        .privacy-footer__links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem 1.25rem;
            justify-content: flex-end;
        }

        .privacy-footer__links a {
            color: #94a3b8;
            font-size: 0.875rem;
            font-weight: 650;
            text-decoration: none;
        }

        .privacy-footer__links a:hover,
        .privacy-footer__links a[aria-current="page"] {
            color: #ffffff;
        }

        @media (min-width: 1024px) {
            .policy-toc summary {
                display: none;
            }

            .policy-toc > .policy-toc-links {
                display: grid !important;
            }
        }

        @media (max-width: 1023px) {
            .policy-layout {
                display: block;
                padding-bottom: 4rem;
                padding-top: 3rem;
            }

            .policy-sidebar {
                margin-bottom: 2.5rem;
                position: static;
            }

            .policy-copy {
                max-width: 46rem;
            }
        }

        @media (max-width: 639px) {
            .privacy-header__inner {
                height: 4rem;
                padding: 0 1rem;
            }

            .privacy-brand__logo {
                height: 1.9rem;
                width: 3.9rem;
            }

            .privacy-brand__label,
            .delete-shortcut__desktop {
                display: none;
            }

            .delete-shortcut__mobile {
                display: inline;
            }

            .delete-shortcut {
                font-size: 0.75rem;
                padding: 0.5rem 0.75rem;
            }

            .privacy-container,
            .policy-layout {
                padding-left: 1.25rem;
                padding-right: 1.25rem;
            }

            .privacy-hero__inner {
                padding-bottom: 3rem;
                padding-top: 3rem;
            }

            .privacy-eyebrow {
                font-size: 0.64rem;
                margin-bottom: 1rem;
                padding: 0.35rem 0.7rem;
            }

            .privacy-title {
                font-size: clamp(2.15rem, 11vw, 2.75rem);
                line-height: 1.08;
            }

            .privacy-intro {
                font-size: 1rem;
                line-height: 1.65;
                margin-top: 1.1rem;
            }

            .policy-meta {
                align-items: flex-start;
                flex-direction: column;
                font-size: 0.8rem;
                gap: 0.3rem;
                margin-top: 1.35rem;
            }

            .commitment-strip {
                display: flex;
                margin-left: -1.25rem;
                margin-right: -1.25rem;
                margin-top: 2rem;
                overflow-x: auto;
                padding: 0 1.25rem 0.5rem;
                scroll-padding-left: 1.25rem;
                scroll-snap-type: x mandatory;
            }

            .commitment-card {
                flex: 0 0 82%;
                scroll-snap-align: start;
            }

            .policy-copy > section {
                margin-top: 2.75rem;
            }

            .policy-copy > section:not(#penghapusan-akun) {
                padding-bottom: 2.75rem;
            }

            .policy-copy p {
                line-height: 1.72;
            }

            .deletion-card {
                padding: 1.35rem;
            }

            .policy-button {
                width: 100%;
            }

            .privacy-footer__inner {
                align-items: flex-start;
                flex-direction: column;
                gap: 1.25rem;
                padding: 1.5rem 1.25rem;
            }

            .privacy-footer__links {
                justify-content: flex-start;
            }
        }

        @media print {
            .privacy-header,
            .policy-sidebar,
            .privacy-footer,
            .no-print {
                display: none !important;
            }

            body {
                background: #ffffff;
            }

            main {
                padding: 0 !important;
            }

            .policy-copy {
                max-width: none !important;
            }
        }
    </style>
</head>
<body id="top" class="min-h-screen text-slate-900 antialiased">
    <header class="privacy-header">
        <div class="privacy-header__inner">
            <a href="/" class="privacy-brand" aria-label="TRIVA — kembali ke beranda">
                <img src="{{ asset('images/triva-mark.png') }}" alt="TRIVA" class="privacy-brand__logo">
                <span class="privacy-brand__label">Pusat Privasi</span>
            </a>

            <a href="#penghapusan-akun" class="delete-shortcut">
                <span class="delete-shortcut__mobile">Hapus akun</span>
                <span class="delete-shortcut__desktop">Penghapusan akun</span>
            </a>
        </div>
    </header>

    <main>
        <section class="privacy-hero">
            <div class="privacy-container privacy-hero__inner">
                <div class="privacy-hero__copy">
                    <p class="privacy-eyebrow">
                        Privasi &amp; keamanan data
                    </p>
                    <h1 class="privacy-title">
                        Kebijakan Privasi <span>TRIVA</span>
                    </h1>
                    <p class="privacy-intro">
                        Kebijakan ini menjelaskan bagaimana aplikasi TRIVA, yang dikembangkan dan dikelola oleh RNQ Studio, mengakses, mengumpulkan, menggunakan, membagikan, melindungi, dan menyimpan data Anda.
                    </p>
                    <div class="policy-meta">
                        <span><strong class="font-bold text-slate-700">Berlaku sejak:</strong> 28 Juli 2026</span>
                        <span><strong class="font-bold text-slate-700">Versi:</strong> 1.0</span>
                    </div>
                </div>

                <div class="commitment-strip mt-9 gap-3 sm:mt-10 sm:grid sm:grid-cols-3 lg:mt-12">
                    <div class="commitment-card rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <span class="text-xs font-extrabold uppercase tracking-[0.14em] text-emerald-700">Komitmen 01</span>
                        <p class="mt-2 text-sm font-bold leading-6 text-slate-800">Kami tidak menjual data pribadi Anda.</p>
                    </div>
                    <div class="commitment-card rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <span class="text-xs font-extrabold uppercase tracking-[0.14em] text-blue-700">Komitmen 02</span>
                        <p class="mt-2 text-sm font-bold leading-6 text-slate-800">Data digunakan untuk layanan yang Anda pilih.</p>
                    </div>
                    <div class="commitment-card rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <span class="text-xs font-extrabold uppercase tracking-[0.14em] text-amber-700">Komitmen 03</span>
                        <p class="mt-2 text-sm font-bold leading-6 text-slate-800">Anda dapat meminta penghapusan akun.</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="policy-layout">
            <aside class="policy-sidebar" aria-label="Daftar isi">
                <details class="policy-toc">
                    <summary class="text-sm font-extrabold text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-600">
                        <span>Daftar isi</span>
                        <span class="flex items-center gap-2 text-xs font-bold text-slate-500">
                            10 bagian
                            <span class="policy-toc-chevron text-base" aria-hidden="true">⌄</span>
                        </span>
                    </summary>
                    <nav class="policy-toc-links grid gap-1 border-t border-slate-100 p-2 text-sm font-semibold lg:border-0 lg:p-3">
                        <a href="#ruang-lingkup" class="rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">1. Ruang lingkup</a>
                        <a href="#data-yang-dikumpulkan" class="rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">2. Data yang dikumpulkan</a>
                        <a href="#penggunaan-data" class="rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">3. Penggunaan data</a>
                        <a href="#pembagian-data" class="rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">4. Pembagian data</a>
                        <a href="#penyimpanan-keamanan" class="rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">5. Penyimpanan &amp; keamanan</a>
                        <a href="#hak-pengguna" class="rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">6. Hak pengguna</a>
                        <a href="#penghapusan-akun" class="rounded-xl bg-blue-50 px-3 py-2.5 text-blue-800 transition hover:bg-blue-100">7. Penghapusan akun</a>
                        <a href="#anak-anak" class="rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">8. Privasi anak</a>
                        <a href="#perubahan" class="rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">9. Perubahan kebijakan</a>
                        <a href="#kontak" class="rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">10. Hubungi kami</a>
                    </nav>
                </details>
            </aside>

            <article class="policy-copy">
                <section id="ruang-lingkup">
                    <p class="!mt-0 text-sm font-extrabold uppercase tracking-[0.15em] text-blue-700">01 — Ruang lingkup</p>
                    <h2 class="!mt-3">Tentang kebijakan ini</h2>
                    <p>
                        Kebijakan Privasi ini berlaku saat Anda menggunakan aplikasi seluler TRIVA dan layanan terkait pada situs resmi TRIVA. Dengan menggunakan TRIVA, Anda memahami bahwa data akan diproses sesuai kebijakan ini untuk menyediakan layanan appraisal atau trade-in kendaraan, servis kendaraan, perbaikan, simulasi kredit, komunikasi tindak lanjut, dan fitur pendukung lainnya.
                    </p>
                    <p>
                        Istilah “kami” merujuk pada TRIVA dan RNQ Studio sebagai pengelola aplikasi. Mitra layanan yang menerima data hanya memproses data yang relevan dengan layanan yang Anda minta.
                    </p>
                </section>

                <section id="data-yang-dikumpulkan">
                    <p class="!mt-0 text-sm font-extrabold uppercase tracking-[0.15em] text-blue-700">02 — Data yang dikumpulkan</p>
                    <h2 class="!mt-3">Data yang dapat kami proses</h2>
                    <p>Kami mengumpulkan data yang Anda berikan, data yang timbul ketika fitur digunakan, serta data teknis yang diperlukan untuk menjalankan aplikasi.</p>

                    <h3>Data akun dan identitas</h3>
                    <ul>
                        <li>Nama, alamat email, nomor telepon, kota, dan foto profil.</li>
                        <li>Kredensial akun yang dilindungi, status verifikasi, serta identitas akun Google apabila Anda memilih Masuk dengan Google.</li>
                        <li>Pilihan persetujuan layanan dan pemasaran beserta waktu pencatatannya.</li>
                    </ul>

                    <h3>Data kendaraan dan layanan</h3>
                    <ul>
                        <li>Merek, model, varian, tahun, transmisi, jenis bahan bakar, jarak tempuh, warna, nomor polisi, dan wilayah kendaraan.</li>
                        <li>Detail appraisal, kondisi atau kerusakan kendaraan, keluhan, riwayat permintaan, hasil estimasi, dan keputusan yang terkait dengan layanan.</li>
                        <li>Detail booking servis atau perbaikan, lokasi layanan, jadwal pilihan, alamat penjemputan atau layanan rumah, catatan lokasi, serta saluran kontak yang dipilih.</li>
                        <li>Data simulasi kredit, pilihan program, dan permintaan untuk dihubungi oleh tim atau mitra terkait.</li>
                    </ul>

                    <h3>Foto, kamera, dan galeri</h3>
                    <p>
                        Dengan tindakan Anda, TRIVA dapat mengakses kamera atau foto yang dipilih dari galeri untuk foto profil, dokumentasi kendaraan, appraisal, kerusakan, atau kebutuhan servis. Kami tidak memindai seluruh galeri dan hanya memproses berkas yang Anda pilih atau ambil melalui aplikasi.
                    </p>

                    <h3>Lokasi</h3>
                    <p>
                        Jika Anda memilih fitur layanan berbasis lokasi, seperti Toyota Home Service, TRIVA dapat meminta lokasi perkiraan atau presisi perangkat. Lokasi digunakan untuk menentukan titik layanan dan dikirim bersama permintaan layanan setelah Anda mengonfirmasinya. Anda tetap dapat menolak izin lokasi; beberapa fungsi berbasis lokasi mungkin tidak tersedia atau perlu diisi secara manual.
                    </p>

                    <h3>Data perangkat dan penggunaan</h3>
                    <ul>
                        <li>ID perangkat yang dibuat oleh aplikasi, jenis platform, versi sistem operasi, nama atau kategori perangkat, versi dan nomor build aplikasi.</li>
                        <li>Token notifikasi, status baca notifikasi, waktu aktivitas, alamat IP, catatan keamanan, dan informasi kesalahan yang diperlukan untuk menjaga layanan tetap andal.</li>
                        <li>Preferensi lokal seperti tema, bahasa, draft formulir, dan pengaturan biometrik. Data lokal tersebut disimpan pada perangkat Anda sesuai fungsi yang dipilih.</li>
                    </ul>
                </section>

                <section id="penggunaan-data">
                    <p class="!mt-0 text-sm font-extrabold uppercase tracking-[0.15em] text-blue-700">03 — Penggunaan data</p>
                    <h2 class="!mt-3">Mengapa data digunakan</h2>
                    <p>Kami menggunakan data untuk:</p>
                    <ul>
                        <li>membuat, mengautentikasi, mengamankan, dan memulihkan akun Anda;</li>
                        <li>menyediakan profil kendaraan, appraisal, booking servis, perbaikan, estimasi, dan simulasi kredit;</li>
                        <li>memproses foto serta dokumen yang Anda unggah untuk layanan yang dipilih;</li>
                        <li>menentukan ketersediaan, titik, jadwal, dan mitra layanan yang relevan;</li>
                        <li>mengirim informasi transaksi, perubahan status, pengingat, notifikasi, dan dukungan pengguna;</li>
                        <li>menghubungi Anda melalui saluran yang dipilih setelah Anda memberikan persetujuan;</li>
                        <li>mencegah penyalahgunaan, mendeteksi aktivitas berisiko, menjaga keamanan, dan menyelesaikan gangguan teknis;</li>
                        <li>memenuhi kewajiban hukum, menyelesaikan sengketa, serta menegakkan ketentuan layanan; dan</li>
                        <li>mengirim komunikasi pemasaran hanya jika Anda telah memilih untuk menerimanya. Persetujuan pemasaran dapat ditarik kembali.</li>
                    </ul>
                    <p>Kami tidak menggunakan data pribadi Anda untuk menjual atau memperdagangkannya kepada pihak lain.</p>
                </section>

                <section id="pembagian-data">
                    <p class="!mt-0 text-sm font-extrabold uppercase tracking-[0.15em] text-blue-700">04 — Pembagian data</p>
                    <h2 class="!mt-3">Kapan data dapat dibagikan</h2>
                    <p>Data dapat dibagikan secara terbatas kepada pihak berikut sesuai kebutuhan:</p>
                    <ul>
                        <li><strong>Mitra layanan kendaraan</strong>, termasuk bengkel, dealer, penyedia home service, operator appraisal, estimator, dan mitra Otoxpert, untuk menindaklanjuti layanan yang Anda minta.</li>
                        <li><strong>Mitra pembiayaan atau tim penjualan terkait</strong>, hanya ketika Anda meminta tindak lanjut simulasi kredit dan memberikan persetujuan.</li>
                        <li><strong>Penyedia infrastruktur</strong>, termasuk penyimpanan cloud, hosting, basis data, email, dan sistem keamanan yang membantu kami mengoperasikan TRIVA.</li>
                        <li><strong>Google dan Firebase</strong>, untuk Masuk dengan Google, verifikasi identitas, penyimpanan berkas, pengiriman push notification, serta pelaporan gangguan aplikasi.</li>
                        <li><strong>OpenStreetMap</strong>, untuk memuat peta pada fitur pemilihan lokasi. Permintaan tile peta dapat menyertakan informasi teknis seperti alamat IP sesuai kebijakan penyedia.</li>
                        <li><strong>Otoritas atau pihak yang berwenang</strong>, apabila diwajibkan oleh hukum, proses hukum yang sah, atau diperlukan untuk melindungi keselamatan dan hak pengguna maupun TRIVA.</li>
                    </ul>
                    <p>
                        Kami membatasi data yang dibagikan sesuai tujuan layanan dan mengharuskan penyedia layanan memproses data secara aman. Layanan pihak ketiga dapat memiliki kebijakan privasi tersendiri.
                    </p>
                </section>

                <section id="penyimpanan-keamanan">
                    <p class="!mt-0 text-sm font-extrabold uppercase tracking-[0.15em] text-blue-700">05 — Penyimpanan &amp; keamanan</p>
                    <h2 class="!mt-3">Cara kami menjaga data</h2>
                    <p>
                        Data disimpan selama akun Anda aktif atau selama diperlukan untuk menyediakan layanan, menyelesaikan transaksi, menjalankan audit keamanan, memenuhi kewajiban hukum, dan menyelesaikan sengketa. Masa simpan dapat berbeda menurut jenis data dan konteks layanan.
                    </p>
                    <p>
                        Setelah tidak lagi diperlukan, data akan dihapus, dianonimkan, atau dibatasi aksesnya sesuai prosedur kami. Salinan cadangan dan catatan yang wajib dipertahankan dapat tersimpan untuk jangka waktu terbatas sampai siklus penghapusan selesai atau masa simpan hukum berakhir.
                    </p>
                    <p>
                        Kami menerapkan langkah keamanan yang wajar, termasuk koneksi terenkripsi saat data dikirim, penyimpanan token sensitif pada fasilitas penyimpanan aman perangkat, kata sandi yang di-hash, kontrol akses berbasis peran, pembatasan akses berkas, pencatatan aktivitas, dan pemantauan operasional. Namun, tidak ada sistem elektronik yang sepenuhnya bebas risiko.
                    </p>
                </section>

                <section id="hak-pengguna">
                    <p class="!mt-0 text-sm font-extrabold uppercase tracking-[0.15em] text-blue-700">06 — Hak pengguna</p>
                    <h2 class="!mt-3">Pilihan dan kendali Anda</h2>
                    <p>Sesuai hukum yang berlaku, Anda dapat:</p>
                    <ul>
                        <li>mengakses dan memperbarui data profil melalui aplikasi;</li>
                        <li>meminta koreksi, salinan, pembatasan pemrosesan, atau penghapusan data;</li>
                        <li>menarik persetujuan pemasaran atau persetujuan opsional lainnya;</li>
                        <li>menonaktifkan notifikasi melalui pengaturan perangkat;</li>
                        <li>menolak atau mencabut izin kamera, galeri, lokasi, dan biometrik melalui pengaturan perangkat; dan</li>
                        <li>menghubungi kami untuk pertanyaan atau keberatan terkait pemrosesan data.</li>
                    </ul>
                    <p>
                        Penarikan izin tidak memengaruhi keabsahan pemrosesan yang telah dilakukan sebelumnya, tetapi dapat membatasi fitur yang memerlukan izin tersebut.
                    </p>
                </section>

                <section id="penghapusan-akun" class="deletion-card">
                    <p class="!mt-0 text-sm font-extrabold uppercase tracking-[0.15em] text-blue-700">07 — Penghapusan akun</p>
                    <h2 class="!mt-3">Minta akun dan data TRIVA dihapus</h2>
                    <p>
                        Anda dapat meminta penghapusan akun TRIVA kapan saja. Kirim email dari alamat yang terdaftar pada akun Anda dengan subjek <strong>“Permintaan Penghapusan Akun TRIVA”</strong>. Sertakan nama dan nomor telepon terdaftar agar kami dapat memverifikasi kepemilikan akun tanpa meminta kata sandi atau OTP.
                    </p>
                    <ol>
                        <li>Kirim permintaan melalui tombol di bawah ini.</li>
                        <li>Tim kami akan menghubungi Anda jika verifikasi tambahan diperlukan.</li>
                        <li>Setelah terverifikasi, akun dan data terkait akan dihapus atau dianonimkan dalam waktu yang wajar.</li>
                    </ol>
                    <p>
                        Penghapusan mencakup profil, data kendaraan, foto, token perangkat, serta riwayat layanan yang terhubung dengan akun, kecuali data tertentu yang perlu dipertahankan sementara untuk kewajiban hukum, pencegahan penipuan, keamanan, penyelesaian sengketa, atau transaksi yang belum selesai. Data yang dipertahankan akan dibatasi penggunaannya dan dihapus setelah tujuan atau masa simpan berakhir.
                    </p>
                    <a
                        href="mailto:ramadhanrp.developer@gmail.com?subject=Permintaan%20Penghapusan%20Akun%20TRIVA&body=Nama%3A%0AEmail%20akun%20TRIVA%3A%0ANomor%20telepon%20akun%20TRIVA%3A%0A%0ASaya%20meminta%20penghapusan%20akun%20dan%20data%20terkait%20di%20TRIVA."
                        class="policy-button"
                    >
                        Kirim permintaan penghapusan
                    </a>
                    <p class="!mt-4 text-sm">
                        Jangan pernah mengirim kata sandi, OTP, access token, atau dokumen identitas melalui email.
                    </p>
                </section>

                <section id="anak-anak">
                    <p class="!mt-0 text-sm font-extrabold uppercase tracking-[0.15em] text-blue-700">08 — Privasi anak</p>
                    <h2 class="!mt-3">Layanan tidak ditujukan untuk anak-anak</h2>
                    <p>
                        TRIVA tidak ditujukan untuk anak-anak dan kami tidak dengan sengaja mengumpulkan data pribadi anak tanpa persetujuan yang diwajibkan oleh hukum. Jika Anda meyakini seorang anak memberikan data kepada kami secara tidak semestinya, hubungi kami agar data tersebut dapat ditinjau dan dihapus.
                    </p>
                </section>

                <section id="perubahan">
                    <p class="!mt-0 text-sm font-extrabold uppercase tracking-[0.15em] text-blue-700">09 — Perubahan kebijakan</p>
                    <h2 class="!mt-3">Pembaruan di masa mendatang</h2>
                    <p>
                        Kami dapat memperbarui kebijakan ini untuk mencerminkan perubahan fitur, praktik keamanan, mitra layanan, atau ketentuan hukum. Versi terbaru akan tersedia pada halaman ini dengan tanggal berlaku yang diperbarui. Untuk perubahan material, kami dapat memberikan pemberitahuan tambahan melalui aplikasi atau saluran komunikasi yang tersedia.
                    </p>
                </section>

                <section id="kontak">
                    <p class="!mt-0 text-sm font-extrabold uppercase tracking-[0.15em] text-blue-700">10 — Hubungi kami</p>
                    <h2 class="!mt-3">Pertanyaan tentang privasi</h2>
                    <p>
                        Untuk pertanyaan, permintaan hak data, atau laporan terkait privasi dan keamanan aplikasi TRIVA, hubungi:
                    </p>
                    <div class="contact-card">
                        <p class="!mt-0 font-bold text-slate-900">Tim Privasi TRIVA — RNQ Studio</p>
                        <p class="!mt-2 break-words">Email: <a class="break-all" href="mailto:ramadhanrp.developer@gmail.com">ramadhanrp.developer@gmail.com</a></p>
                        <p class="!mt-2">Situs: <a href="{{ url('/') }}">{{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'triva.ramadhanrosihadi.web.id' }}</a></p>
                    </div>
                </section>

                <div class="back-to-top no-print">
                    <a href="#top" class="text-sm">Kembali ke atas ↑</a>
                </div>
            </article>
        </div>
    </main>

    <footer class="privacy-footer">
        <div class="privacy-footer__inner">
            <div class="privacy-footer__brand">
                <span class="privacy-footer__logo-box">
                    <img src="{{ asset('images/triva-mark.png') }}" alt="TRIVA" class="privacy-footer__logo">
                </span>
                <p class="privacy-footer__copyright">© {{ date('Y') }} RNQ Studio</p>
            </div>
            <div class="privacy-footer__links">
                <a href="/" class="font-semibold text-slate-400 transition hover:text-white">Beranda</a>
                <a href="{{ route('privacy-policy') }}" aria-current="page">Kebijakan Privasi</a>
                <a href="#penghapusan-akun" class="font-semibold text-slate-400 transition hover:text-white">Penghapusan Akun</a>
            </div>
        </div>
    </footer>
</body>
</html>
