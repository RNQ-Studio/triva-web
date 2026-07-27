<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DeveloperAdminSeeder extends Seeder
{
    private const EMAIL = 'ramadhanrp.developer@gmail.com';

    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Ramadhan RP Developer',
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        if (! $user->is_active) {
            $user->forceFill(['is_active' => true])->save();
        }

        // Keep google_sub null until the real Google ID token links this row.
        // assignRole is additive and never revokes another existing role.
        $user->assignRole('admin');
    }
}
