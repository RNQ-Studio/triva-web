<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminListUsersRequest;
use App\Http\Requests\Api\V1\AdminShowUserRequest;
use App\Http\Requests\Api\V1\GrantAdminAccessRequest;
use App\Http\Resources\Api\V1\AdminUserResource;
use App\Models\User;
use App\Services\AdminUserAccessService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminUserAccessService $accessService,
    ) {}

    public function index(AdminListUsersRequest $request): JsonResponse
    {
        $query = User::query()
            ->with('roles')
            ->withCount([
                'appraisals',
                'toyotaServiceBookings',
                'otoxpertBookings',
                'creditSimulations',
                'bodyPaintEstimates',
                'vehicles',
                'devices',
            ])
            ->withMax('devices', 'last_active_at');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim()->toString().'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'ilike', $search)
                    ->orWhere('email', 'ilike', $search)
                    ->orWhere('phone', 'ilike', $search);
            });
        }

        if ($request->has('gender')) {
            $gender = $request->string('gender')->toString();
            $gender === 'unknown'
                ? $query->whereNull('gender')
                : $query->where('gender', $gender);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('has_demographics')) {
            $request->boolean('has_demographics')
                ? $query->whereNotNull('gender')->whereNotNull('birth_date')
                : $query->where(function (Builder $builder): void {
                    $builder->whereNull('gender')->orWhereNull('birth_date');
                });
        }

        $sort = $request->has('sort')
            ? $request->string('sort')->toString()
            : 'name';
        $direction = $request->has('direction')
            ? $request->string('direction')->toString()
            : ($sort === 'created_at' ? 'desc' : 'asc');

        $query->orderBy($sort, $direction)->orderBy('id');

        return ApiResponse::success(AdminUserResource::collection(
            $query->paginate($request->integer('per_page', 20))->withQueryString()
        ));
    }

    public function show(AdminShowUserRequest $request, User $user): JsonResponse
    {
        $user->loadCount([
            'appraisals',
            'toyotaServiceBookings',
            'otoxpertBookings',
            'creditSimulations',
            'bodyPaintEstimates',
            'vehicles',
            'devices',
        ]);
        $user->loadMax('devices', 'last_active_at');
        $user->load([
            'roles',
            'devices' => fn ($query) => $query
                ->orderByDesc('last_active_at')
                ->limit(10),
        ]);

        return ApiResponse::success(new AdminUserResource($user));
    }

    public function grantAdmin(
        GrantAdminAccessRequest $request,
        User $user,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $managedUser = $this->accessService->grantAdmin($user, $actor);

        return ApiResponse::success(
            new AdminUserResource($managedUser),
            'Akses admin berhasil diberikan.',
        );
    }
}
