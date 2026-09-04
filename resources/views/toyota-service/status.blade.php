<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex, nofollow">

    <title>Status Booking {{ $booking->reference_no }} | TRIVA</title>
    <meta name="theme-color" content="#062a66">

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    {{--
        Halaman ini dibuka PIC cabang dari WhatsApp di ponsel, jadi dibuat
        berdiri sendiri (tanpa @vite) supaya ringan dan langsung tampil.
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
            --success: #1f8a4c;
            --success-soft: #dff5e7;
            --danger: #b3261e;
            --danger-soft: #fbe4e2;
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
                --success: #5ad48a;
                --success-soft: #16382a;
                --danger: #ff8a80;
                --danger-soft: #4a1f1c;
                --shadow: 0 24px 70px rgba(0, 0, 0, 0.34);
            }
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 24px 16px 48px;
            background: var(--surface);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                "Helvetica Neue", Arial, sans-serif;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }

        .card {
            width: 100%;
            max-width: 560px;
            padding: 28px 24px 24px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: var(--surface-elevated);
            box-shadow: var(--shadow);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        h1 {
            margin: 14px 0 2px;
            font-size: 1.45rem;
            line-height: 1.25;
            letter-spacing: -0.01em;
        }

        .ref {
            margin: 0;
            color: var(--text-soft);
            font-size: 0.95rem;
        }

        .alert {
            margin-top: 18px;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .alert-success { background: var(--success-soft); color: var(--success); }
        .alert-error { background: var(--danger-soft); color: var(--danger); }

        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin: 22px 0 0;
            padding: 0;
            list-style: none;
        }

        .step {
            padding: 12px 8px;
            border: 1px solid var(--line);
            border-radius: 12px;
            text-align: center;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-soft);
        }

        .step .num {
            display: block;
            margin: 0 auto 6px;
            width: 28px;
            height: 28px;
            line-height: 28px;
            border-radius: 50%;
            background: var(--line);
            color: var(--text);
            font-size: 0.85rem;
        }

        .step.done { border-color: var(--success); color: var(--success); }
        .step.done .num { background: var(--success); color: #fff; }
        .step.current { border-color: var(--accent); background: var(--accent-soft); color: var(--accent); }
        .step.current .num { background: var(--accent); color: #fff; }

        dl {
            margin: 22px 0 0;
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .row {
            display: flex;
            gap: 12px;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--line);
            font-size: 0.95rem;
        }

        .row dt { margin: 0; color: var(--text-soft); flex: 0 0 38%; }
        .row dd { margin: 0; text-align: right; font-weight: 500; overflow-wrap: anywhere; }

        .actions {
            margin-top: 22px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 14px 18px;
            border: 0;
            border-radius: 12px;
            font: inherit;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
        }

        .btn-primary { background: var(--surface-deep); color: #f7fbff; }
        .btn-success { background: var(--success); color: #fff; }

        .note {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
            font-size: 0.85rem;
            color: var(--text-soft);
        }
    </style>
</head>
<body>
    <main class="card">
        <span class="badge">Booking Servis Toyota</span>

        <h1>Perbarui Status Booking</h1>
        <p class="ref">Nomor referensi <strong>{{ $booking->reference_no }}</strong></p>

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error" role="alert">{{ session('error') }}</div>
        @endif
        @error('stage')
            <div class="alert alert-error" role="alert">{{ $message }}</div>
        @enderror

        @php
            $order = array_keys($stages);
            $currentIndex = array_search($stage, $order, true);
        @endphp
        <ol class="steps" aria-label="Tahapan booking">
            @foreach ($stages as $key => $label)
                @php $index = $loop->index; @endphp
                <li class="step {{ $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : '') }}">
                    <span class="num">{{ $index + 1 }}</span>{{ $label }}
                </li>
            @endforeach
        </ol>

        @if ($isClosed)
            <div class="alert alert-error" role="status">
                Booking ini sudah ditutup ({{ $booking->status->customerLabel() }}) dan tidak bisa diperbarui lagi.
            </div>
        @endif

        <dl>
            <div class="row">
                <dt>Status sekarang</dt>
                <dd>{{ $booking->status->customerLabel() }}</dd>
            </div>
            <div class="row">
                <dt>Pelanggan</dt>
                <dd>{{ $booking->user?->name ?? '-' }}</dd>
            </div>
            <div class="row">
                <dt>Kendaraan</dt>
                <dd>
                    @if ($booking->vehicle)
                        {{ $booking->vehicle->make }} {{ $booking->vehicle->model }} {{ $booking->vehicle->year }}
                        @if ($booking->vehicle->license_plate)
                            &middot; {{ $booking->vehicle->license_plate }}
                        @endif
                    @else
                        -
                    @endif
                </dd>
            </div>
            <div class="row">
                <dt>Jenis servis</dt>
                <dd>{{ $booking->serviceType?->name ?? '-' }}</dd>
            </div>
            <div class="row">
                <dt>Lokasi</dt>
                <dd>{{ $booking->serviceLocation?->name ?? '-' }}</dd>
            </div>
            <div class="row">
                <dt>Jadwal</dt>
                <dd>{{ $schedule ?? '-' }}</dd>
            </div>
            <div class="row">
                <dt>Keluhan</dt>
                <dd>{{ $booking->complaint }}</dd>
            </div>
        </dl>

        @if (count($availableStages) > 0)
            <div class="actions">
                @if (in_array('processing', $availableStages, true))
                    <form method="post" action="{{ route('toyota-service.status.update', $booking->public_token) }}">
                        @csrf
                        <input type="hidden" name="stage" value="processing">
                        <button type="submit" class="btn btn-primary">Tandai Sedang Diproses</button>
                    </form>
                @endif
                @if (in_array('completed', $availableStages, true))
                    <form method="post" action="{{ route('toyota-service.status.update', $booking->public_token) }}"
                          onsubmit="return confirm('Tandai booking ini sebagai selesai?');">
                        @csrf
                        <input type="hidden" name="stage" value="completed">
                        <button type="submit" class="btn btn-success">Tandai Selesai</button>
                    </form>
                @endif
            </div>
        @endif

        <div class="note">
            Setiap perubahan status langsung terlihat oleh pelanggan di aplikasi TRIVA
            dan dikirim sebagai notifikasi. Tautan ini bersifat rahasia; jangan
            diteruskan ke pihak lain.
        </div>
    </main>
</body>
</html>
