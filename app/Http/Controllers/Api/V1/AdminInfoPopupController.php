<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInfoPopupRequest;
use App\Http\Requests\Api\V1\UpdateInfoPopupRequest;
use App\Http\Resources\Api\V1\AdminInfoPopupResource;
use App\Models\InfoPopup;
use App\Services\PublicImageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Manajemen popup informasi beranda dari Admin Panel aplikasi.
 */
class AdminInfoPopupController extends Controller
{
    public function __construct(
        private readonly PublicImageService $images,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', InfoPopup::class);

        $popups = InfoPopup::query()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success(AdminInfoPopupResource::collection($popups));
    }

    public function store(StoreInfoPopupRequest $request): JsonResponse
    {
        /** @var UploadedFile $image */
        $image = $request->file('image');

        $popup = $this->images->create(
            new InfoPopup,
            collect($request->validated())->except('image')->all(),
            $image,
            'image_path',
            InfoPopup::IMAGE_DIRECTORY,
        );

        return ApiResponse::success(
            new AdminInfoPopupResource($popup),
            'Popup informasi ditambahkan.',
            201,
        );
    }

    public function update(UpdateInfoPopupRequest $request, InfoPopup $infoPopup): JsonResponse
    {
        $image = $request->file('image');

        $popup = $this->images->update(
            $infoPopup,
            collect($request->validated())->except('image')->all(),
            $image instanceof UploadedFile ? $image : null,
            'image_path',
            InfoPopup::IMAGE_DIRECTORY,
        );

        return ApiResponse::success(
            new AdminInfoPopupResource($popup),
            'Popup informasi diperbarui.',
        );
    }

    public function destroy(InfoPopup $infoPopup): JsonResponse
    {
        $this->authorize('delete', $infoPopup);

        $this->images->delete($infoPopup, 'image_path');

        return ApiResponse::success(null, 'Popup informasi dihapus.');
    }
}
