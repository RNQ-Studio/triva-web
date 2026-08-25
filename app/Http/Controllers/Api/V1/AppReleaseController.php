<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAppReleaseRequest;
use App\Models\AppRelease;
use App\Models\AppVersion;
use App\Services\AppReleaseStorage;
use App\Support\ApiResponse;
use App\Support\Enums\DevicePlatform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Distribusi APK yang dihost sendiri untuk in-app update.
 *
 * - store(): tooling rilis lokal mengunggah build. Biner masuk ke object
 *   storage — bukan disk aplikasi — supaya artifact selamat dari deploy.
 *   Metadata dan sha256 dicatat, rilis menjadi satu-satunya yang aktif untuk
 *   platform tersebut, dan `latest_version` pada AppVersion ikut disinkronkan
 *   agar prompt update tidak perlu langkah manual terpisah.
 * - latest(): aplikasi mengambil metadata rilis aktif untuk dibandingkan
 *   dengan versi yang terpasang, lalu mengunduh dan memverifikasi sha256-nya.
 */
class AppReleaseController extends Controller
{
    public function __construct(
        private readonly AppReleaseStorage $storage,
    ) {}

    public function store(StoreAppReleaseRequest $request): JsonResponse
    {
        if (! $this->hasValidUploadKey($request)) {
            return ApiResponse::error('Kunci unggah rilis tidak valid.', 403);
        }

        $platform = $this->normalizePlatform($request->input('platform'));
        $versionCode = (int) $request->integer('version_code');
        $versionName = trim((string) $request->input('version_name'));
        $file = $request->file('file');

        // Version code wajib naik. Menurunkannya membuat perangkat yang sudah
        // memasang build lebih tinggi ditawari downgrade yang akan ditolak
        // Android, dan itu sulit dipulihkan dari sisi customer.
        $highest = AppRelease::query()
            ->where('platform', $platform->value)
            ->max('version_code');
        if ($highest !== null && $versionCode <= (int) $highest) {
            return ApiResponse::error(
                'Version code '.$versionCode.' harus lebih tinggi dari rilis terakhir '.$highest.'.',
                422,
            );
        }

        // APK adalah arsip ZIP. Menolak yang lain menjaga endpoint ini tidak
        // berubah menjadi file host generik.
        if (! $this->looksLikeApk($file->getRealPath())) {
            return ApiResponse::error('Berkas bukan APK yang valid.', 422);
        }

        $sha256 = hash_file('sha256', $file->getRealPath());
        $sizeBytes = (int) $file->getSize();
        $filename = $this->releaseFilename($versionName, $versionCode);
        $storagePath = 'app-releases/'.$platform->value.'/'.$versionCode.'/'.$filename;

        try {
            $apkUrl = $this->storage->upload($file->getRealPath(), $storagePath);
        } catch (Throwable $e) {
            Log::error('APK upload failed: '.$e->getMessage());

            return ApiResponse::error('Gagal mengunggah APK. Silakan coba lagi.', 502);
        }

        $release = DB::transaction(function () use (
            $platform,
            $versionCode,
            $versionName,
            $request,
            $apkUrl,
            $sha256,
            $sizeBytes,
            $storagePath,
        ): AppRelease {
            // Hanya satu rilis per platform yang ditawarkan sebagai target
            // update, jadi rilis sebelumnya dinonaktifkan.
            AppRelease::query()
                ->where('platform', $platform->value)
                ->update(['is_active' => false]);

            $release = AppRelease::updateOrCreate(
                [
                    'platform' => $platform->value,
                    'version_code' => $versionCode,
                ],
                [
                    'version_name' => $versionName,
                    'apk_url' => $apkUrl,
                    'apk_sha256' => $sha256,
                    'apk_size_bytes' => $sizeBytes,
                    'storage_path' => $storagePath,
                    'is_active' => true,
                    'release_notes' => $this->nullableString($request->input('release_notes')),
                ]
            );

            $this->syncVersionPolicy($release);

            return $release;
        });

        return ApiResponse::success(
            $this->serialize($release),
            'Rilis berhasil diunggah.',
            201,
        );
    }

    /**
     * @unauthenticated
     */
    public function latest(Request $request): JsonResponse
    {
        $platform = $this->normalizePlatform($request->query('platform'));

        $release = AppRelease::query()
            ->where('platform', $platform->value)
            ->where('is_active', true)
            ->orderByDesc('version_code')
            ->first();

        return ApiResponse::success([
            'platform' => $platform->value,
            'release' => $release === null ? null : $this->serialize($release),
        ]);
    }

    /**
     * Isi `latest_version` supaya prompt update in-app ikut terisi tanpa
     * langkah manual. `min_version` dan `force_update` sengaja tidak disentuh:
     * memaksa update tetap keputusan eksplisit lewat admin panel.
     */
    private function syncVersionPolicy(AppRelease $release): void
    {
        $policy = AppVersion::query()
            ->where('platform', $release->platform->value)
            ->first();

        if ($policy !== null) {
            $policy->update(['latest_version' => $release->version_name]);

            return;
        }

        AppVersion::create([
            'platform' => $release->platform->value,
            'min_version' => '1.0.0',
            'latest_version' => $release->version_name,
            'force_update' => false,
        ]);
    }

    private function looksLikeApk(string $realPath): bool
    {
        $handle = fopen($realPath, 'rb');
        if ($handle === false) {
            return false;
        }
        $magic = fread($handle, 4);
        fclose($handle);

        return $magic === "PK\x03\x04";
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AppRelease $release): array
    {
        return [
            'id' => $release->id,
            'platform' => $release->platform->value,
            'version_code' => $release->version_code,
            'version_name' => $release->version_name,
            'apk_filename' => basename($release->storage_path),
            'apk_url' => $release->apk_url,
            'apk_sha256' => $release->apk_sha256,
            'apk_size_bytes' => $release->apk_size_bytes,
            'release_notes' => $release->release_notes,
            'is_active' => $release->is_active,
            'created_at' => $release->created_at?->toIso8601String(),
        ];
    }

    private function releaseFilename(string $versionName, int $versionCode): string
    {
        $safe = preg_replace('/[^a-z0-9._-]+/i', '-', $versionName) ?? '';
        $safe = trim($safe, '.-_');

        return 'triva-'.($safe === '' ? 'version' : $safe).'+'.$versionCode.'.apk';
    }

    /**
     * Rilis biner hanya ada untuk platform mobile; `web` di-deploy lewat
     * hosting, jadi nilai apa pun di luar android/ios jatuh ke android.
     */
    private function normalizePlatform(mixed $value): DevicePlatform
    {
        $platform = DevicePlatform::tryFrom(strtolower(trim((string) ($value ?? ''))));

        return in_array($platform, [DevicePlatform::Android, DevicePlatform::Ios], true)
            ? $platform
            : DevicePlatform::Android;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * Cocokkan header `X-App-Release-Key` dengan konfigurasi secara
     * timing-safe. Kunci kosong mematikan endpoint sepenuhnya.
     */
    private function hasValidUploadKey(Request $request): bool
    {
        $configured = (string) config('app_update.upload_key', '');
        if ($configured === '') {
            return false;
        }

        $provided = (string) $request->header('X-App-Release-Key', '');

        return $provided !== '' && hash_equals($configured, $provided);
    }
}
