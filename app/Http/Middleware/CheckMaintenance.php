<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use App\Support\Enums\ApiErrorCode;
use App\Support\Maintenance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menutup seluruh sistem TRIVA saat sakelar maintenance menyala.
 *
 * Terpasang pada grup `api` dan `web` sekaligus, jadi satu sakelar mematikan
 * API mobile dan halaman web publik. Pengecualiannya eksplisit di
 * `config/maintenance.php` — terutama `api/v1/app/*`, supaya klien tetap punya
 * jalan untuk membaca status maintenance-nya.
 *
 * Web mendapat halaman maintenance yang sebenarnya. Aplikasi mendapat envelope
 * 503; build Android yang beredar saat ini menampilkannya sebagai error
 * generik, yang sudah cukup untuk menghalangi pemakaian tanpa update aplikasi.
 */
class CheckMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Maintenance::isEnabled() || Maintenance::allows($request)) {
            return $next($request);
        }

        $retryAfter = Maintenance::retryAfterSeconds();

        if ($request->is('api/*') || $request->expectsJson()) {
            return ApiResponse::error(
                message: Maintenance::message(),
                status: 503,
                code: ApiErrorCode::MaintenanceMode,
            )->header('Retry-After', (string) $retryAfter);
        }

        return response()
            ->view('maintenance', [
                'title' => Maintenance::title(),
                'message' => Maintenance::message(),
                'until' => Maintenance::until(),
            ], 503)
            ->header('Retry-After', (string) $retryAfter);
    }
}
