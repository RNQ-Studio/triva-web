<?php

namespace App\Support;

use App\Models\AppConfig;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Throwable;

/**
 * Sumber kebenaran tunggal untuk sakelar maintenance TRIVA.
 *
 * `.env` menang atas database: bila `TRIVA_MAINTENANCE_MODE` di-set, nilainya
 * dipakai apa adanya. Bila tidak di-set sama sekali, sakelar lama di
 * `app_configs` tetap dihormati supaya toggle dari back-office tidak mati
 * begitu fitur ini masuk.
 */
class Maintenance
{
    public static function isEnabled(): bool
    {
        $fromEnv = config('maintenance.enabled');

        if ($fromEnv !== null) {
            return filter_var($fromEnv, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) self::fromDatabase('maintenance_mode', false);
    }

    public static function title(): string
    {
        return (string) config('maintenance.title');
    }

    public static function message(): string
    {
        $fromEnv = config('maintenance.message');

        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return $fromEnv;
        }

        // `.env` tidak menyetel pesan apa pun: hormati pesan kustom yang
        // mungkin sudah diisi lewat back-office sebelum fitur ini ada.
        $fromDatabase = self::fromDatabase('maintenance_message');

        if (is_string($fromDatabase) && trim($fromDatabase) !== '') {
            return $fromDatabase;
        }

        return (string) config('maintenance.default_message');
    }

    /**
     * Perkiraan waktu selesai, atau null bila tidak diumumkan.
     */
    public static function until(): ?CarbonImmutable
    {
        $raw = config('maintenance.until');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw);
        } catch (Throwable) {
            // Format salah tidak boleh menjatuhkan request; sakelarnya tetap
            // berfungsi, hanya perkiraan waktunya yang tidak ditampilkan.
            return null;
        }
    }

    /**
     * Detik untuk header `Retry-After`, minimal 60 supaya klien tidak
     * langsung mencoba lagi saat `until` sudah lewat.
     */
    public static function retryAfterSeconds(): int
    {
        $until = self::until();

        if ($until !== null) {
            $seconds = CarbonImmutable::now()->diffInSeconds($until, false);

            if ($seconds > 0) {
                return (int) $seconds;
            }
        }

        return max(60, (int) config('maintenance.retry_after', 900));
    }

    /**
     * Request ini kebal maintenance? Berlaku juga saat sakelar mati, tapi
     * middleware baru menanyakannya setelah `isEnabled()` bernilai true.
     */
    public static function allows(Request $request): bool
    {
        foreach ((array) config('maintenance.except', []) as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        if (config('maintenance.allow_admin', true) && self::isBackOfficePath($request)) {
            return true;
        }

        $ip = $request->ip();

        return $ip !== null && in_array($ip, (array) config('maintenance.allow_ips', []), true);
    }

    /**
     * Status maintenance yang disajikan lewat `/api/v1/app/config`, endpoint
     * yang sengaja tetap terbuka saat sistem mati.
     *
     * Belum ada build aplikasi yang membacanya hari ini (app/config baru
     * dipakai untuk data kontak); ini disediakan supaya build berikutnya bisa
     * menampilkan halaman maintenance yang sesungguhnya tanpa perlu perubahan
     * backend lagi.
     *
     * @return array{maintenance_mode: bool, maintenance_title: string, maintenance_message: string, maintenance_until: string|null}
     */
    public static function payload(): array
    {
        $until = self::until();

        return [
            'maintenance_mode' => self::isEnabled(),
            'maintenance_title' => self::title(),
            'maintenance_message' => self::message(),
            'maintenance_until' => $until?->toIso8601String(),
        ];
    }

    /**
     * Sakelar ini kini dievaluasi pada setiap request web maupun API, termasuk
     * saat database justru sedang bermasalah. Kegagalan query tidak boleh
     * menjatuhkan halaman: perlakukan sebagai "tidak ada nilai di database"
     * dan biarkan `.env` yang menentukan.
     */
    private static function fromDatabase(string $key, mixed $default = null): mixed
    {
        try {
            return AppConfig::get($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }

    private static function isBackOfficePath(Request $request): bool
    {
        // Filament butuh endpoint Livewire dan aset statisnya, bukan hanya
        // `/admin`, kalau panelnya harus tetap bisa dipakai.
        return $request->is('admin', 'admin/*', 'livewire/*', 'filament/*', 'storage/*');
    }
}
