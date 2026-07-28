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
            overflow-x: hidden;
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
            scrollbar-width: none;
        }

        .commitment-strip::-webkit-scrollbar {
            display: none;
        }

        @media (min-width: 1024px) {
            .policy-toc summary {
                display: none;
            }

            .policy-toc > .policy-toc-links {
                display: grid !important;
            }
        }

        @media (max-width: 639px) {
            .commitment-strip {
                display: flex;
                margin-left: -1.25rem;
                margin-right: -1.25rem;
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
        }

        @media print {
            header,
            aside,
            footer,
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
    <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:h-18 sm:px-8">
            <a href="/" class="flex min-w-0 items-center gap-3 rounded-xl py-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2" aria-label="TRIVA — kembali ke beranda">
                <img src="{{ asset('images/triva-mark.png') }}" alt="TRIVA" class="h-8 w-auto shrink-0 sm:h-9">
                <span class="hidden border-l border-slate-200 pl-3 text-xs font-bold text-slate-500 sm:block">Pusat Privasi</span>
            </a>

            <a href="#penghapusan-akun" class="inline-flex shrink-0 items-center rounded-full border border-blue-200 bg-blue-50 px-3.5 py-2 text-xs font-extrabold text-blue-800 transition hover:border-blue-300 hover:bg-blue-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 sm:px-4 sm:text-sm">
                <span class="sm:hidden">Hapus akun</span>
                <span class="hidden sm:inline">Penghapusan akun</span>
            </a>
        </div>
    </header>

    <main>
        <section class="border-b border-slate-200/80">
            <div class="mx-auto max-w-7xl px-5 pb-12 pt-12 sm:px-8 sm:pb-16 sm:pt-16 lg:pb-20 lg:pt-20">
                <div class="max-w-3xl">
                    <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white px-3 py-1.5 text-[0.68rem] font-extrabold uppercase tracking-[0.14em] text-blue-700 shadow-sm sm:mb-5 sm:px-3.5 sm:text-xs">
                        Privasi &amp; keamanan data
                    </p>
                    <h1 class="text-3xl font-black tracking-[-0.04em] text-slate-950 sm:text-5xl lg:text-6xl">
                        Kebijakan Privasi <span class="text-[#0758b5]">TRIVA</span>
                    </h1>
                    <p class="mt-5 max-w-2xl text-base leading-7 text-slate-600 sm:mt-6 sm:text-lg sm:leading-8">
                        Kebijakan ini menjelaskan bagaimana aplikasi TRIVA, yang dikembangkan dan dikelola oleh RNQ Studio, mengakses, mengumpulkan, menggunakan, membagikan, melindungi, dan menyimpan data Anda.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-500 sm:mt-7 sm:text-sm">
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

        <div class="mx-auto grid max-w-7xl gap-8 px-5 py-10 sm:px-8 sm:py-14 lg:grid-cols-[17rem_minmax(0,1fr)] lg:gap-14 lg:py-20">
            <aside class="self-start lg:sticky lg:top-24" aria-label="Daftar isi">
                <details class="policy-toc overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
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

            <article class="policy-copy min-w-0 max-w-3xl lg:pt-1">
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

                <section id="penghapusan-akun" class="scroll-mt-28 rounded-3xl border border-blue-200 bg-[#f3f8ff] p-5 shadow-sm sm:p-8">
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
                        class="policy-button mt-6 inline-flex w-full items-center justify-center rounded-xl bg-[#0758b5] px-5 py-3 text-center text-sm font-extrabold text-white shadow-lg shadow-blue-900/10 transition hover:bg-[#064993] focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 sm:w-auto"
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
                    <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
                        <p class="!mt-0 font-bold text-slate-900">Tim Privasi TRIVA — RNQ Studio</p>
                        <p class="!mt-2 break-words">Email: <a class="break-all" href="mailto:ramadhanrp.developer@gmail.com">ramadhanrp.developer@gmail.com</a></p>
                        <p class="!mt-2">Situs: <a href="{{ url('/') }}">{{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'triva.ramadhanrosihadi.web.id' }}</a></p>
                    </div>
                </section>

                <div class="no-print mt-14 border-t border-slate-200 pt-8">
                    <a href="#top" class="text-sm">Kembali ke atas ↑</a>
                </div>
            </article>
        </div>
    </main>

    <footer class="border-t border-slate-200 bg-slate-950">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-8 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-9">
            <div class="flex items-center gap-3">
                <span class="inline-flex rounded-lg bg-white px-2 py-1.5">
                    <img src="{{ asset('images/triva-mark.png') }}" alt="TRIVA" class="h-6 w-auto">
                </span>
                <p class="font-semibold text-slate-300">© {{ date('Y') }} RNQ Studio</p>
            </div>
            <div class="flex flex-wrap gap-x-5 gap-y-2 sm:justify-end">
                <a href="/" class="font-semibold text-slate-400 transition hover:text-white">Beranda</a>
                <a href="{{ route('privacy-policy') }}" class="font-semibold text-white">Kebijakan Privasi</a>
                <a href="#penghapusan-akun" class="font-semibold text-slate-400 transition hover:text-white">Penghapusan Akun</a>
            </div>
        </div>
    </footer>
</body>
</html>
