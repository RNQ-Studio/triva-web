<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePartnerLogoRequest;
use App\Http\Requests\Api\V1\UpdatePartnerLogoRequest;
use App\Http\Resources\Api\V1\AdminPartnerLogoResource;
use App\Models\PartnerLogo;
use App\Services\PublicImageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Manajemen logo "Mitra resmi" beranda dari Admin Panel aplikasi.
 */
class AdminPartnerLogoController extends Controller
{
    public function __construct(
        private readonly PublicImageService $images,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', PartnerLogo::class);

        $logos = PartnerLogo::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(AdminPartnerLogoResource::collection($logos));
    }

    public function store(StorePartnerLogoRequest $request): JsonResponse
    {
        /** @var UploadedFile $logo */
        $logo = $request->file('logo');

        $partner = $this->images->create(
            new PartnerLogo,
            collect($request->validated())->except('logo')->all(),
            $logo,
            'logo_path',
            PartnerLogo::LOGO_DIRECTORY,
        );

        return ApiResponse::success(
            new AdminPartnerLogoResource($partner),
            'Mitra resmi ditambahkan.',
            201,
        );
    }

    public function update(UpdatePartnerLogoRequest $request, PartnerLogo $partnerLogo): JsonResponse
    {
        $logo = $request->file('logo');

        $partner = $this->images->update(
            $partnerLogo,
            collect($request->validated())->except('logo')->all(),
            $logo instanceof UploadedFile ? $logo : null,
            'logo_path',
            PartnerLogo::LOGO_DIRECTORY,
        );

        return ApiResponse::success(
            new AdminPartnerLogoResource($partner),
            'Mitra resmi diperbarui.',
        );
    }

    public function destroy(PartnerLogo $partnerLogo): JsonResponse
    {
        $this->authorize('delete', $partnerLogo);

        $this->images->delete($partnerLogo, 'logo_path');

        return ApiResponse::success(null, 'Mitra resmi dihapus.');
    }
}
