<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GoogleAuthService
{
    public function __construct(
        private readonly FirebaseIdTokenVerifier $tokenVerifier,
        private readonly AuthService $authService,
    ) {}

    /**
     * @param  array{device_id?: string, platform?: string, os_version?: string|null, app_version?: string|null, device_name?: string|null, push_token?: string|null}  $deviceInfo
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     *
     * @throws AuthenticationException|AuthorizationException
     */
    public function login(string $idToken, array $deviceInfo = []): array
    {
        $identity = $this->tokenVerifier->verifyGoogle($idToken);

        $user = DB::transaction(function () use ($identity): User {
            $user = User::query()
                ->where('google_sub', $identity->subject)
                ->lockForUpdate()
                ->first();

            if ($user === null) {
                $user = User::query()
                    ->whereRaw('LOWER(email) = ?', [$identity->email])
                    ->lockForUpdate()
                    ->first();
            }

            if (
                $user !== null
                && $user->google_sub !== null
                && ! hash_equals($user->google_sub, $identity->subject)
            ) {
                throw new AuthenticationException(
                    'This email is already linked to another Google account.'
                );
            }

            if ($user === null) {
                return User::query()->create([
                    'name' => $identity->name,
                    'email' => $identity->email,
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(64)),
                    'google_sub' => $identity->subject,
                    'avatar' => $identity->avatarUrl,
                    'is_active' => true,
                ]);
            }

            if (! $user->is_active) {
                throw new AuthorizationException('Your account is inactive.');
            }

            $updates = [
                'google_sub' => $identity->subject,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ];

            if ($user->avatar === null && $identity->avatarUrl !== null) {
                $updates['avatar'] = $identity->avatarUrl;
            }

            $user->update($updates);

            return $user->refresh();
        });

        return $this->authService->issueTokenForUser($user, $deviceInfo);
    }
}
