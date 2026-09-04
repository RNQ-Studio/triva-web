# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- Halaman web publik bertoken `GET/POST /booking-servis/{public_token}` untuk PIC cabang memperbarui status booking servis Toyota (menunggu → diproses → selesai) tanpa login; kolom `toyota_service_bookings.public_token` dan field `status_update_url` pada resource booking (revisi 4 September 2026).
- Simulasi kredit cepat ACC: `config/credit_acc.php` (rate card dari lembar kerja Simulasi_ACC), `AccCreditCalculator`, `GET /api/v1/credit/quick/rate-card`, `POST /api/v1/credit/quick`; hasil tersimpan sebagai `CreditSimulation` dan memberi notifikasi in-app + push ke semua admin aktif (`AdminNotificationService`).
- Kolom `credit_programs.unit_key` dan `image_path` (unggah gambar unit di Filament), seed empat unit rekomendasi demo (Veloz Hybrid, Zenix Hybrid, Innova Reborn, Raize); `GET /appraisals/{appraisal}/upgrade-options` kini memilih unit berdasarkan rentang harga appraisal.
- Tabel `sales_contacts` + Filament "Data Sales" (nama, foto, WhatsApp, peran Sales/SPV) + `GET /api/v1/sales-contacts`.
- Tabel `home_banners` + Filament "Banner Beranda" + `GET /api/v1/banners` untuk slider iklan beranda aplikasi.
- Sakelar maintenance mode TRIVA yang bersumber dari `.env` production (`TRIVA_MAINTENANCE_MODE` dan kawan-kawannya, lihat `config/maintenance.php`). Menutup grup middleware `api` dan `web` sekaligus dengan allowlist eksplisit, membalas `503` + `Retry-After` + kode `MAINTENANCE_MODE` untuk API, dan merender halaman maintenance mandiri untuk web. `GET /api/v1/app/config` sengaja tetap terbuka dan kini melaporkan `maintenance_mode`, `maintenance_title`, `maintenance_message`, serta `maintenance_until`, supaya build aplikasi berikutnya bisa menampilkan halaman maintenance sesungguhnya tanpa perubahan backend lagi. Build Android yang beredar saat ini belum membaca field tersebut dan memetakan 503 ke teks error generiknya sendiri — cukup untuk menghalangi pemakaian, tanpa mewajibkan update aplikasi. Nilai `app_configs` lama tetap dihormati sebagai fallback saat `.env` tidak menyetel apa pun.
- `scripts/maintenance.sh` untuk menyalakan/mematikan sakelar tersebut di server production lewat ssh, termasuk `config:cache` yang wajib menyertainya.
- Quotes Management API under the `api/v1` prefix: public (no auth required) full CRUD plus combined search (`filter[search]` across text and author via a `scopeSearch` ILIKE scope), field filters, sorting, and pagination.
- Filament back-office resource for Quotes (modular `Schemas`/`Tables`/`Pages` structure under the `Data Master` navigation group), accessible by `admin` and `staff` roles.
- Automated feature tests for the Quotes API (guest access, search/filter/sort/pagination, full CRUD cycle) and back-office page smoke tests.
- Comprehensive step-by-step tutorials detailing best practices for adding a new API to the project, using a "Quotes" CRUD & search use case, covering migrations, models, factories, RBAC policies, Filament integration, and testing (stored under `docs/tutorial/`, `docs/tutorial_claude_opus/`, and `docs/tutorial_claude_opus4.7/`).
- Excel Import and Export APIs (`GET /api/users/export` and `POST /api/users/import`) using the `maatwebsite/excel` package, with strict row validation and upsert logic.
- Standardized `ApiErrorCode` enum under `App\Support\Enums` to enforce robust API response schemas (`CF-027`).
- GitHub Actions CI pipeline configuration (`.github/workflows/ci.yml`) to validate linting, phpstan, and phpunit against PostgreSQL (`CF-025`).
- Developer shortcuts using a root `Makefile` (`CF-029`).
- Production Deployment Guide under `docs/deployment.md` (`CF-030`).
- GitHub Contribution templates for Bug Reports, Feature Requests, and Pull Requests (`CF-032`).
- Premium Dark Mode logo variation (`public/images/logo-dark.svg`) registered dynamically within Filament AdminPanelProvider (`CF-033`).
- Comprehensive architectural and usage documentation for GCS Assets Management System under `docs/features.md` (`CF-034`).
- Custom premium visual Feature Card for Assets & GCS on Welcome page `resources/views/welcome.blade.php` (`CF-035`).
- Mandatory rules and steps for updating the changelog added to `CONTRIBUTING.md` (`CF-036`).
- Modal-based `EditAction` in both list view (table record actions) and detail view (header actions) in the Filament Back-Office Asset Resource to allow editing asset metadata (`category`, `retain_until`, `is_protected`) while dynamically hiding it when an asset is hard deleted.

### Changed
- Jawaban kondisi appraisal: `service_history` menerima `authorized|general`, `ownership` menerima `second_or_more` (nilai lama tetap diterima); label penyesuaian dan panel admin memakai deskripsi kondisi, bukan "Grade"; ban `damaged` dilabeli "Aus".
- `GET /toyota-service/options` hanya menawarkan `contact_channels` WhatsApp; `GET /admin/toyota-service/options` hanya menawarkan `benefit_types` SSC.
- Displayed `hard_deleted_at` timestamp in Filament Back-Office detail view infolist schema (`AssetInfolist`) under the Metadata & Audit section to show when an asset was hard deleted.
- Resolved `status` badge visual inconsistency in Filament Back-Office detail view infolist schema by mapping status colors (`success`, `warning`, `danger`) to match the list table.

---

## [1.2.0] - 2026-05-24

### Added
- Independent User Registration API endpoint (`POST /api/v1/auth/register`) protected by rate-limiting (`CF-016`).
- Standard Password Reset API endpoints (`POST /api/v1/auth/forgot-password` and `POST /api/v1/auth/reset-password`) (`CF-017`).
- "Logout All Devices" API endpoint (`POST /api/v1/auth/logout-all`) (`CF-018`).
- Formal security policies and vulnerability reporting pathways in `SECURITY.md` (`CF-019`).
- Interactive database diagram mapping all models and relationship schemas in `docs/erd/database_erd.md` (`CF-020`).

### Changed
- Refactored `ApiResponse::success` parameter `$data` type-hinting and docblocks to be fully compliant with strict PHP and PHPStan validation (`CF-023`).
- Published and configured `config/cors.php` explicitly to ease API integration with frontend clients (`CF-024`).

### Fixed
- Fixed concurrent device registration race condition inside `AuthService->upsertDevice()` using DB transactions and database-level constraint handling (`CF-021`).
- Resolved inconsistency in OTP token sessions by providing OTP logins with standard OAuth Refresh Token capabilities (`CF-022`).

---

## [1.1.0] - 2026-05-24

### Added
- Isolated unit test coverage for core business services (`AuthService`, `OtpService`, `PushNotificationService`, `FileUploadService`) (`CF-015`).
- Official MIT `LICENSE` file (`CF-013`).

### Changed
- Restored Email Verification flow (`MustVerifyEmail`) on the `User` model, including endpoints for resending verification mails and confirming registration verification (`CF-011`).
- Enforced Spatie Role-Based Access Control (RBAC) across all backend Filament resources, restricting unauthorized actions and sidebar entries for standard users (`CF-014`).

### Fixed
- Corrected test suite settings to execute against PostgreSQL by default for parity with production environments (`CF-012`).

---

## [1.0.0] - 2026-05-24

### Added
- Enforced HTTPS-only schema redirect rules under production environments in `AppServiceProvider` (`CF-001`).
- Installed Laravel Sail for containerized PostgreSQL, Redis, and Mailpit services (`CF-005`).
- Created a developer-friendly agent navigation manual in `CLAUDE.md` (`CF-006`).
- Implemented model factories for all secondary schemas (`UserDevice`, `AppConfig`, `AppVersion`, `Notification`, `OtpCode`) to boost test development velocity (`CF-009`).
- Premium styling makeover for Filament Back-Office, utilizing indigo color configurations and customized favicons/logos (`CF-010`).

### Changed
- Automatic pagination metadata resolution inside `ApiResponse::success()` for pagination lists (`CF-007`).
- Offline local JSON fixtures for Regional seeders to eliminate internet dependencies in CI build environments (`CF-008`).

### Fixed
- Cleared Passport client secret masking inside `AuthService` when running under debug mode to streamline developer setup diagnosis (`CF-002`).
- Fixed missing instructions for creating password grant clients in `.env.example` and `README.md` (`CF-003`).
