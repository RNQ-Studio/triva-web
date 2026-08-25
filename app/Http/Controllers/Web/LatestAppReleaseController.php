<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use App\Support\Enums\DevicePlatform;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tautan unduh permanen untuk APK rilis terbaru.
 *
 * URL biner mengandung nomor versi sehingga berubah tiap rilis; route ini
 * memberi satu alamat tetap yang bisa dibagikan sekali dan tetap benar
 * seterusnya. Responsnya redirect, bukan stream, supaya berkas 75 MB itu
 * dilayani web server dan tidak melewati PHP.
 */
class LatestAppReleaseController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $release = AppRelease::query()
            ->where('platform', DevicePlatform::Android->value)
            ->where('is_active', true)
            ->orderByDesc('version_code')
            ->first();

        if ($release === null) {
            throw new NotFoundHttpException('Belum ada rilis Android yang aktif.');
        }

        return redirect()->away($release->apk_url);
    }
}
