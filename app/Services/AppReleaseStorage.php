<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Mengunggah biner APK ke object storage dan mengembalikan URL unduh absolut.
 *
 * Dipisah dari controller supaya test bisa mengganti implementasinya lewat
 * `app()->instance()` tanpa menyentuh jaringan, pola yang sama dipakai
 * AssetUploadService untuk asset customer.
 */
class AppReleaseStorage
{
    /**
     * @return string URL unduh absolut biner yang diunggah.
     */
    public function upload(string $realPath, string $objectPath): string
    {
        $disk = Storage::disk(config('app_update.disk', 'gcs'));

        $handle = fopen($realPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read APK for upload.');
        }

        try {
            $stored = $disk->put($objectPath, $handle, [
                'visibility' => 'public',
                'ContentType' => 'application/vnd.android.package-archive',
            ]);
        } finally {
            fclose($handle);
        }

        if ($stored === false) {
            throw new RuntimeException('Failed to upload APK to object storage.');
        }

        return $disk->url($objectPath);
    }
}
