<?php

namespace Tests\Feature\Api;

use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_their_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);
        Passport::actingAs($user);

        $this->putJson('/api/v1/auth/me', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated',
                'data' => [
                    'name' => 'New Name',
                    'email' => 'new@example.com',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    public function test_user_can_save_gender_and_birth_date_without_locking_older_installs(): void
    {
        $user = User::factory()->create([
            'phone' => '+628123456789',
            'city' => 'Bandung',
            'service_consent_at' => now(),
        ]);
        Passport::actingAs($user);

        // Pemasangan lama tidak mengirim demografi: profil tetap dianggap
        // lengkap supaya gerbang lamanya tidak terkunci.
        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.profile_completed', true)
            ->assertJsonPath('data.demographics_completed', false)
            ->assertJsonPath('data.gender', null)
            ->assertJsonPath('data.age', null);

        $this->putJson('/api/v1/auth/me', [
            'gender' => 'female',
            'birth_date' => '1995-06-15',
        ])
            ->assertOk()
            ->assertJsonPath('data.gender', 'female')
            ->assertJsonPath('data.birth_date', '1995-06-15')
            ->assertJsonPath('data.demographics_completed', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'gender' => 'female',
        ]);
        $this->assertSame('1995-06-15', $user->refresh()->birth_date?->toDateString());
        $this->assertNotNull($user->age());
    }

    public function test_profile_rejects_unknown_gender_and_out_of_range_birth_date(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->putJson('/api/v1/auth/me', ['gender' => 'unknown'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['gender']);

        $this->putJson('/api/v1/auth/me', [
            'birth_date' => now()->subYears(3)->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['birth_date']);

        $this->putJson('/api/v1/auth/me', ['birth_date' => '15-06-1995'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['birth_date']);
    }

    public function test_profile_email_must_be_unique_except_for_current_user(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'current@example.com']);
        Passport::actingAs($user);

        $this->putJson('/api/v1/auth/me', [
            'name' => 'Current User',
            'email' => 'taken@example.com',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email']]);

        $this->putJson('/api/v1/auth/me', [
            'name' => 'Current User',
            'email' => 'current@example.com',
        ])->assertOk();
    }

    public function test_profile_update_requires_authentication(): void
    {
        $this->putJson('/api/v1/auth/me', [
            'name' => 'Guest',
            'email' => 'guest@example.com',
        ])->assertUnauthorized();
    }

    public function test_first_login_profile_records_separate_service_and_marketing_consents(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->putJson('/api/v1/auth/me', [
            'phone' => '+6281234567890',
            'city' => 'Surabaya',
            'service_consent' => true,
            'marketing_consent' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.phone', '+6281234567890')
            ->assertJsonPath('data.city', 'Surabaya')
            ->assertJsonPath('data.profile_completed', true)
            ->assertJsonPath('data.marketing_consent', false);

        $this->assertDatabaseHas('customer_consents', [
            'user_id' => $user->getKey(),
            'type' => 'service',
            'granted' => true,
        ]);
        $this->assertDatabaseHas('customer_consents', [
            'user_id' => $user->getKey(),
            'type' => 'marketing',
            'granted' => false,
        ]);
    }

    public function test_first_login_profile_requires_valid_phone_and_service_consent(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->putJson('/api/v1/auth/me', [
            'phone' => 'not-a-phone',
            'city' => 'Surabaya',
            'service_consent' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'service_consent']);
    }

    public function test_first_login_profile_uses_city_from_selected_indonesian_province(): void
    {
        $user = User::factory()->create();
        [$province, $city] = $this->indonesianProvinceAndCity();
        Passport::actingAs($user);

        $this->putJson('/api/v1/auth/me', [
            'phone' => '+6281234567890',
            'province_id' => $province->getKey(),
            'city_id' => $city->getKey(),
            'service_consent' => true,
            'marketing_consent' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.city', 'KOTA SURABAYA')
            ->assertJsonPath('data.profile_completed', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'city' => 'KOTA SURABAYA',
        ]);
    }

    public function test_first_login_profile_rejects_city_from_another_province(): void
    {
        $user = User::factory()->create();
        [$province] = $this->indonesianProvinceAndCity();
        $otherProvince = Region::query()->create([
            'parent_id' => $province->parent_id,
            'type' => 'state',
            'code' => '34',
            'name' => 'DAERAH ISTIMEWA YOGYAKARTA',
        ]);
        $otherCity = Region::query()->create([
            'parent_id' => $otherProvince->getKey(),
            'type' => 'city',
            'code' => '3471',
            'name' => 'KOTA YOGYAKARTA',
        ]);
        Passport::actingAs($user);

        $this->putJson('/api/v1/auth/me', [
            'phone' => '+6281234567890',
            'province_id' => $province->getKey(),
            'city_id' => $otherCity->getKey(),
            'service_consent' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['city_id']);
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        Passport::actingAs($user);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Password changed']);

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        Passport::actingAs($user);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['current_password']]);

        $this->assertTrue(Hash::check('old-password', $user->refresh()->password));
    }

    /**
     * @return array{Region, Region}
     */
    private function indonesianProvinceAndCity(): array
    {
        $indonesia = Region::query()->create([
            'type' => 'country',
            'code' => 'ID',
            'name' => 'Indonesia',
        ]);
        $province = Region::query()->create([
            'parent_id' => $indonesia->getKey(),
            'type' => 'state',
            'code' => '35',
            'name' => 'JAWA TIMUR',
        ]);
        $city = Region::query()->create([
            'parent_id' => $province->getKey(),
            'type' => 'city',
            'code' => '3578',
            'name' => 'KOTA SURABAYA',
        ]);

        return [$province, $city];
    }
}
