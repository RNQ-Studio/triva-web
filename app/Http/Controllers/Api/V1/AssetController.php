<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadAssetRequest;
use App\Http\Resources\Api\V1\AssetResource;
use App\Services\AssetUploadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

class AssetController extends Controller
{
    public function __construct(
        private readonly AssetUploadService $uploadService,
    ) {}

    public function upload(UploadAssetRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $isProtectedVehiclePhoto = in_array(
            $request->string('type')->toString(),
            ['appraisal-photo', 'toyota-service-photo'],
            true,
        );
        $retainUntil = $isProtectedVehiclePhoto
            ? Carbon::now()->addDays(180)
            : $request->date('retain_until');

        $asset = $this->uploadService->upload(
            file: $file,
            type: $request->string('type')->toString(),
            userId: $request->user()?->getKey(),
            retainUntil: $retainUntil,
            isProtected: $isProtectedVehiclePhoto || $request->boolean('is_protected'),
        );

        return ApiResponse::success(new AssetResource($asset), 'Asset uploaded', 201);
    }
}
