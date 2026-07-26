# Codex Instructions — TRIVA Laravel

Baca `../AGENTS.md` sebelum bekerja. Instruksi root tersebut tetap berlaku.

## Stack dan Struktur

- PHP 8.3+, Laravel 13, PostgreSQL, Redis.
- API mobile berada di `routes/api.php` dengan prefix `/api/v1`.
- Auth API memakai Laravel Passport; back-office `/admin` memakai session
  Filament 5.
- RBAC memakai `spatie/laravel-permission`; authorization harus melalui
  Policy/Gate dan tetap berlaku pada API maupun Filament.
- Controller harus tipis. Validasi berada di Form Request, bentuk output di API
  Resource/`ApiResponse`, dan logic non-trivial di Service/Model yang tepat.
- Schema hanya berubah lewat migration additive. Jangan mengedit migration yang
  sudah pernah masuk production untuk mengubah sejarah schema.

## Kontrak API

- Gunakan `App\Support\ApiResponse` untuk envelope standar.
- Gunakan API Resource untuk output entity dan paginator untuk list.
- Field JSON memakai `snake_case`; waktu dikirim ISO-8601 dan backend berjalan
  dalam UTC.
- Perubahan list harus whitelist filter/sort/include. Hindari query bebas,
  N+1, dan pagination yang berubah diam-diam.
- Mutasi wajib memiliki Form Request, policy/authorization, status HTTP yang
  tepat, dan test untuk 401/403/404/422 sesuai relevansi.
- Jangan mengekspos model atau kolom internal secara langsung hanya karena
  Eloquent dapat men-serialize-nya.
- Audit consumer di `../triva-flutter` sebelum mengubah nama, nullability,
  nesting, enum, auth flow, atau pagination.

## Database, Queue, dan Scheduler

- Production memakai PostgreSQL; test utama juga harus merepresentasikan
  PostgreSQL untuk perilaku yang database-specific.
- Migration harus aman untuk data yang sudah ada: nullable/backfill/enforce
  secara bertahap untuk kolom wajib pada tabel besar.
- Queue production memakai koneksi Redis dan queue bernama `triva`.
- Job yang harus diproses worker production harus masuk queue yang sesuai atau
  mengikuti `REDIS_QUEUE=triva`.
- Jadwal ada di `routes/console.php`. Menambah schedule belum selesai sebelum
  trigger `schedule:run` di server juga diverifikasi.
- Seeder production harus idempotent dan eksplisit. Jangan menjalankan
  `db:seed` di production tanpa review dampak setiap seeder.

## Security

- Jangan log password, OTP, access/refresh token, Passport secret, Firebase
  credential, signed URL, atau data pribadi penuh.
- Cegah mass assignment, IDOR, unrestricted upload, path traversal, SQL
  injection, dan authorization yang hanya dilakukan di UI.
- Upload harus memvalidasi ukuran, MIME/content, ownership, storage disk, dan
  lifecycle cleanup.
- Endpoint publik harus disengaja dan dicatat di
  `../triva-docs/API_CONTRACT.md`.
- Jangan menambah debug endpoint atau mengaktifkan `APP_DEBUG` di production.

## Quality Gates

Jalankan dari root `triva-web`:

```powershell
vendor\bin\pint --test
vendor\bin\phpstan analyse --memory-limit=2G
php artisan test
npm run build
```

Gunakan gate sempit selama iterasi:

```powershell
php artisan test --filter=NamaTest
vendor\bin\pint --test path\ke\File.php
vendor\bin\phpstan analyse path\ke\File.php --memory-limit=1G
```

Untuk perubahan route:

```powershell
php artisan route:list --path=api
```

Untuk perubahan schedule:

```powershell
php artisan schedule:list
```

Jangan mengubah baseline test atau menurunkan static-analysis rule hanya untuk
membuat gate hijau tanpa menjelaskan akar masalah.

## Production

Production berada di `/var/www/triva-web`, dimiliki `www-data`. Gunakan
`../triva-docs/PRODUCTION_RUNBOOK.md`.

Hal wajib:

- periksa perubahan lokal server sebelum pull/deploy;
- jalankan Git/Composer/Artisan sebagai `www-data`;
- jangan menimpa `.env`, OAuth keys, storage, atau upload;
- migration harus dijalankan dengan `--force` hanya setelah code dan backup
  path siap;
- restart queue lalu cek Supervisor dan log;
- verifikasi `/api/v1/health` dan endpoint yang berubah;
- bila asset Vite berubah, gunakan artifact build karena server saat ini tidak
  menyediakan Node/NPM.
