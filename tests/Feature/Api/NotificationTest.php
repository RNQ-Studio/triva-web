<?php

namespace Tests\Feature\Api;

use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Push\FcmDriverInterface;
use App\Services\PushNotificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Passport\ClientRepository;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $client = app(ClientRepository::class)->createPasswordGrantClient('Test Password Grant', 'users', true);
        config([
            'passport.password_client.id' => $client->id,
            'passport.password_client.secret' => $client->plainSecret,
        ]);

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();

        $this->token = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ])->json('data.access_token');
    }

    // ── API endpoints ────────────────────────────────────────────────────────

    public function test_notifications_list_is_empty_initially(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_notifications_list_returns_users_notifications(): void
    {
        Notification::create([
            'user_id' => $this->user->getKey(),
            'title' => 'Hello',
            'body' => 'World',
            'type' => 'system',
        ]);

        $other = User::factory()->create();
        Notification::create([
            'user_id' => $other->getKey(),
            'title' => 'Other',
            'body' => 'User',
            'type' => 'system',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_notifications_list_preserves_pagination_ownership_and_newest_first_order(): void
    {
        foreach (range(1, 21) as $index) {
            $notification = Notification::query()->create([
                'user_id' => $this->user->getKey(),
                'title' => sprintf('Owned %02d', $index),
                'body' => 'Body',
                'type' => 'toyota_service_booking',
            ]);
            $notification->forceFill([
                'created_at' => now()->addMinutes($index),
                'updated_at' => now()->addMinutes($index),
            ])->saveQuietly();
        }
        $other = User::factory()->create();
        Notification::query()->create([
            'user_id' => $other->getKey(),
            'title' => 'Foreign notification',
            'body' => 'Must never leak.',
            'type' => 'system',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('data.0.title', 'Owned 21')
            ->assertJsonPath('data.19.title', 'Owned 02')
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 20)
            ->assertJsonPath('meta.pagination.total', 21)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonMissing(['title' => 'Foreign notification']);

        $this->withToken($this->token)
            ->getJson('/api/v1/notifications?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Owned 01')
            ->assertJsonPath('meta.pagination.current_page', 2);
    }

    public function test_notifications_list_is_stable_across_pages_when_timestamps_are_equal(): void
    {
        $createdAt = now()->startOfSecond();
        $ids = [];

        foreach (range(1, 21) as $index) {
            $notification = Notification::query()->create([
                'user_id' => $this->user->getKey(),
                'title' => sprintf('Tied %02d', $index),
                'body' => 'Body',
                'type' => 'toyota_service_booking',
            ]);
            $notification->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->saveQuietly();
            $ids[] = $notification->id;
        }

        $expected = $ids;
        rsort($expected, SORT_STRING);

        $pageOne = $this->withToken($this->token)
            ->getJson('/api/v1/notifications?page=1')
            ->assertOk();
        $pageTwo = $this->withToken($this->token)
            ->getJson('/api/v1/notifications?page=2')
            ->assertOk();
        $actual = [
            ...array_column($pageOne->json('data'), 'id'),
            ...array_column($pageTwo->json('data'), 'id'),
        ];

        $this->assertSame($expected, $actual);
        $this->assertCount(21, array_unique($actual));
    }

    public function test_unread_count_is_correct(): void
    {
        Notification::create(['user_id' => $this->user->getKey(), 'title' => 'A', 'body' => 'B', 'type' => 'system']);
        Notification::create(['user_id' => $this->user->getKey(), 'title' => 'C', 'body' => 'D', 'type' => 'system', 'read_at' => now()]);

        $this->withToken($this->token)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 1);
    }

    public function test_mark_single_notification_as_read(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->getKey(),
            'title' => 'Test',
            'body' => 'Body',
            'type' => 'system',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cannot_mark_another_users_notification_as_read(): void
    {
        $other = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $other->getKey(),
            'title' => 'Other',
            'body' => 'Body',
            'type' => 'system',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertNotFound();
    }

    public function test_mark_all_notifications_as_read(): void
    {
        Notification::create(['user_id' => $this->user->getKey(), 'title' => 'A', 'body' => 'B', 'type' => 'system']);
        Notification::create(['user_id' => $this->user->getKey(), 'title' => 'C', 'body' => 'D', 'type' => 'system']);

        $this->withToken($this->token)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $this->assertSame(0, Notification::where('user_id', $this->user->getKey())->unread()->count());
    }

    // ── PushNotificationService ──────────────────────────────────────────────

    public function test_push_notification_is_sent_to_device_with_push_token(): void
    {
        UserDevice::create([
            'user_id' => $this->user->getKey(),
            'device_id' => 'dev-1',
            'platform' => 'android',
            'push_token' => 'fcm-token-valid',
            'last_active_at' => now(),
        ]);

        /** @var MockInterface&FcmDriverInterface $mockFcm */
        $mockFcm = $this->mock(FcmDriverInterface::class);
        $mockFcm->shouldReceive('send')
            ->once()
            ->with('fcm-token-valid', 'Title', 'Body', [])
            ->andReturn(true);

        $service = new PushNotificationService($mockFcm);
        $service->send($this->user, 'Title', 'Body');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->getKey(),
            'title' => 'Title',
        ]);
        $this->assertNotNull(Notification::where('user_id', $this->user->getKey())->first()->sent_at);
    }

    public function test_invalid_fcm_token_is_cleared_after_failed_send(): void
    {
        UserDevice::create([
            'user_id' => $this->user->getKey(),
            'device_id' => 'dev-bad',
            'platform' => 'android',
            'push_token' => 'invalid-token',
            'last_active_at' => now(),
        ]);

        /** @var MockInterface&FcmDriverInterface $mockFcm */
        $mockFcm = $this->mock(FcmDriverInterface::class);
        $mockFcm->shouldReceive('send')->once()->andReturn(false);

        $service = new PushNotificationService($mockFcm);
        $service->send($this->user, 'Title', 'Body');

        $this->assertDatabaseHas('user_devices', ['device_id' => 'dev-bad', 'push_token' => null]);
        $this->assertNotNull(Notification::where('user_id', $this->user->getKey())->first()->failed_at);
    }

    public function test_push_to_user_with_no_devices_records_notification(): void
    {
        /** @var MockInterface&FcmDriverInterface $mockFcm */
        $mockFcm = $this->mock(FcmDriverInterface::class);
        $mockFcm->shouldReceive('send')->never();

        $service = new PushNotificationService($mockFcm);
        $service->send($this->user, 'Title', 'Body');

        $this->assertDatabaseCount('notifications', 1);
        $this->assertNotNull(Notification::first()->sent_at);
    }

    public function test_transient_push_failure_propagates_without_clearing_valid_token(): void
    {
        $device = UserDevice::query()->create([
            'user_id' => $this->user->getKey(),
            'device_id' => 'retry-device',
            'platform' => 'android',
            'push_token' => 'valid-retry-token',
        ]);
        $notification = Notification::query()->create([
            'user_id' => $this->user->getKey(),
            'title' => 'Retry title',
            'body' => 'Retry body',
            'type' => 'toyota_service_booking',
        ]);
        /** @var MockInterface&FcmDriverInterface $mockFcm */
        $mockFcm = $this->mock(FcmDriverInterface::class);
        $mockFcm->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Transient FCM outage.'));
        $job = new SendPushNotificationJob($notification);

        try {
            $job->handle($mockFcm);
            $this->fail('Transient exception must propagate to the queue worker.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Transient FCM outage.', $exception->getMessage());
        }

        $this->assertSame('valid-retry-token', $device->refresh()->push_token);
        $this->assertNull($notification->refresh()->sent_at);
        $this->assertNull($notification->failed_at);
        $this->assertSame(4, $job->tries);
        $this->assertSame([30, 120, 300], $job->backoff());

        $job->failed(new RuntimeException('Retries exhausted.'));
        $this->assertNotNull($notification->refresh()->failed_at);
    }

    public function test_later_push_success_preserves_token_and_clears_failure_marker(): void
    {
        $device = UserDevice::query()->create([
            'user_id' => $this->user->getKey(),
            'device_id' => 'recovered-device',
            'platform' => 'android',
            'push_token' => 'recovered-token',
        ]);
        $notification = Notification::query()->create([
            'user_id' => $this->user->getKey(),
            'title' => 'Recovered title',
            'body' => 'Recovered body',
            'type' => 'toyota_service_booking',
            'failed_at' => now()->subMinute(),
        ]);
        /** @var MockInterface&FcmDriverInterface $mockFcm */
        $mockFcm = $this->mock(FcmDriverInterface::class);
        $mockFcm->shouldReceive('send')->once()->andReturn(true);

        (new SendPushNotificationJob($notification))->handle($mockFcm);

        $this->assertSame('recovered-token', $device->refresh()->push_token);
        $this->assertNotNull($notification->refresh()->sent_at);
        $this->assertNull($notification->failed_at);
    }
}
