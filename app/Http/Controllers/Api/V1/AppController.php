<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use App\Models\AppVersion;
use App\Support\ApiResponse;
use App\Support\Maintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppController extends Controller
{
    /**
     * @unauthenticated
     */
    public function version(Request $request): JsonResponse
    {
        $platform = $request->query('platform', 'android');

        $version = AppVersion::query()
            ->where('platform', $platform)
            ->first();

        if ($version === null) {
            return ApiResponse::error('Platform not found.', 404, code: 'NOT_FOUND');
        }

        return ApiResponse::success([
            'platform' => $version->platform->value,
            'min_version' => $version->min_version,
            'latest_version' => $version->latest_version,
            'force_update' => $version->force_update,
            'store_url' => $version->store_url,
            'release_notes' => $version->release_notes,
        ]);
    }

    /**
     * @unauthenticated
     */
    public function config(): JsonResponse
    {
        // Endpoint ini sengaja dikecualikan dari sakelar maintenance
        // (`config/maintenance.php`): justru inilah satu-satunya cara klien
        // mengetahui bahwa sistem sedang mati beserta alasannya. Status dari
        // `.env` ditimpakan di atas nilai database supaya keduanya tidak
        // pernah saling bertentangan.
        return ApiResponse::success(
            array_merge(AppConfig::allPublic(), Maintenance::payload())
        );
    }
}
