<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Auth\FirebaseIdTokenVerifier;
use App\Services\Auth\GoogleIdentity;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Passport\ClientRepository;
use Mockery\MockInterface;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $client = app(ClientRepository::class)
            ->createPasswordGrantClient('Test Password Grant', 'users', true);
        config([
            'passport.password_client.id' => $client->id,
            'passport.password_client.secret' => $client->plainSecret,
        ]);

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_google_login_creates_user_and_returns_tokens(): void
    {
        $this->fakeIdentity();

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-firebase-id-token',
            'device_id' => 'samsung-a56',
            'platform' => 'android',
        ])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Google login successful'])
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'refresh_token',
                    'token_type',
                    'expires_in',
                    'email_verified',
                ],
            ])
            ->assertJsonPath('data.email_verified', true);

        $this->assertDatabaseHas('users', [
            'email' => 'customer@example.com',
            'google_sub' => 'google-subject-123',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('user_devices', [
            'device_id' => 'samsung-a56',
            'platform' => 'android',
        ]);
    }

    public function test_google_login_links_existing_verified_email_without_duplicate(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'google_sub' => null,
        ]);
        $this->fakeIdentity();

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-firebase-id-token',
        ])->assertOk();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'customer@example.com',
            'google_sub' => 'google-subject-123',
        ]);
    }

    public function test_google_login_rejects_invalid_token(): void
    {
        $this->mock(
            FirebaseIdTokenVerifier::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('verifyGoogle')
                    ->once()
                    ->with('invalid-token')
                    ->andThrow(new AuthenticationException('Invalid token.'));
            },
        );

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'invalid-token',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_login_requires_id_token(): void
    {
        $this->postJson('/api/v1/auth/google', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_token']);
    }

    public function test_inactive_google_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'google_sub' => 'google-subject-123',
            'is_active' => false,
        ]);
        $this->fakeIdentity();

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'valid-firebase-id-token',
        ])->assertForbidden();
    }

    private function fakeIdentity(): void
    {
        $this->mock(
            FirebaseIdTokenVerifier::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('verifyGoogle')
                    ->once()
                    ->with('valid-firebase-id-token')
                    ->andReturn(new GoogleIdentity(
                        subject: 'google-subject-123',
                        email: 'customer@example.com',
                        name: 'TRIVA Customer',
                        avatarUrl: 'https://example.com/avatar.jpg',
                    ));
            },
        );
    }
}
