<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminListUsersRequest;
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
            ->orderBy('name')
            ->orderBy('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim()->toString().'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'ilike', $search)
                    ->orWhere('email', 'ilike', $search);
            });
        }

        return ApiResponse::success(AdminUserResource::collection(
            $query->paginate($request->integer('per_page', 20))
        ));
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
