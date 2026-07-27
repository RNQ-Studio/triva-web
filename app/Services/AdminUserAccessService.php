<?php

namespace App\Services;

use App\Exceptions\AdminUserConflictException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminUserAccessService
{
    public function grantAdmin(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user, $actor): User {
            /** @var User $locked */
            $locked = User::query()
                ->with('roles')
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            if (! $locked->is_active) {
                throw new AdminUserConflictException(
                    'Aktifkan akun user sebelum memberikan akses admin.',
                    'ADMIN_USER_INACTIVE',
                );
            }

            if ($locked->hasAnyRole(['admin', 'super-admin'])) {
                return $locked;
            }

            $locked->assignRole('admin');

            activity('access-management')
                ->causedBy($actor)
                ->performedOn($locked)
                ->withProperties([
                    'granted_role' => 'admin',
                    'target_user_id' => $locked->getKey(),
                ])
                ->log('Admin access granted');

            return $locked->load('roles');
        });
    }
}
