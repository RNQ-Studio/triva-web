<?php

namespace Tests\Unit\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Push\FcmDriverInterface;
use App\Services\PushNotificationService;
use App\Support\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PushNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private PushNotificationService $pushNotificationService;

    private FcmDriverInterface $fcmMock;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fcmMock = Mockery::mock(FcmDriverInterface::class);
        $this->app->instance(FcmDriverInterface::class, $this->fcmMock);
        $this->pushNotificationService = $this->app->make(PushNotificationService::class);

        $this->user = User::factory()->create();
    }

    public function test_send_creates_notification_and_dispatches_to_all_user_devices(): void
    {
        // Set up two devices with push tokens
        $device1 = UserDevice::factory()->create([
            'user_id' => $this->user->id,
            'push_token' => 'token-android-1',
            'platform' => DevicePlatform::Android,
        ]);
        $device2 = UserDevice::factory()->create([
            'user_id' => $this->user->id,
            'push_token' => 'token-ios-1',
            'platform' => DevicePlatform::Ios,
        ]);

        $title = 'Hello Title';
        $body = 'Hello Body';
        $data = ['custom_key' => 'custom_val'];
        $type = 'marketing';
        $expectedData = [...$data, 'type' => $type];

        // Expect both to succeed
        $this->fcmMock->shouldReceive('send')
            ->once()
            ->with('token-android-1', $title, $body, $expectedData)
            ->andReturn(true);

        $this->fcmMock->shouldReceive('send')
            ->once()
            ->with('token-ios-1', $title, $body, $expectedData)
            ->andReturn(true);

        // Act
        $this->pushNotificationService->send($this->user, $title, $body, $data, $type);

        // Assert
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => $title,
            'body' => $body,
            'type' => $type,
        ]);

        $notification = Notification::where('user_id', $this->user->id)->first();
        $this->assertNotNull($notification->sent_at);
        $this->assertNull($notification->failed_at);
        $this->assertEquals($expectedData, $notification->data);

        // Assert tokens are NOT cleared
        $this->assertSame('token-android-1', $device1->refresh()->push_token);
        $this->assertSame('token-ios-1', $device2->refresh()->push_token);
    }

    public function test_send_clears_invalid_fcm_tokens_gracefully(): void
    {
        // Device with invalid token
        $device = UserDevice::factory()->create([
            'user_id' => $this->user->id,
            'push_token' => 'token-invalid',
        ]);

        $title = 'Title';
        $body = 'Body';

        // FCM returns false for invalid token
        $this->fcmMock->shouldReceive('send')
            ->once()
            ->with('token-invalid', $title, $body, ['type' => 'system'])
            ->andReturn(false);

        // Act
        $this->pushNotificationService->send($this->user, $title, $body);

        // Assert: Push token is cleared on device
        $this->assertNull($device->refresh()->push_token);

        // Assert: Notification is marked failed_at because all (one) devices failed
        $notification = Notification::where('user_id', $this->user->id)->first();
        $this->assertNull($notification->sent_at);
        $this->assertNotNull($notification->failed_at);
    }

    public function test_send_updates_sent_at_on_any_fcm_success(): void
    {
        // One success and one fail device
        $device1 = UserDevice::factory()->create([
            'user_id' => $this->user->id,
            'push_token' => 'token-success',
        ]);
        $device2 = UserDevice::factory()->create([
            'user_id' => $this->user->id,
            'push_token' => 'token-fail',
        ]);

        $title = 'Title';
        $body = 'Body';

        $this->fcmMock->shouldReceive('send')
            ->once()
            ->with('token-success', $title, $body, ['type' => 'system'])
            ->andReturn(true);

        $this->fcmMock->shouldReceive('send')
            ->once()
            ->with('token-fail', $title, $body, ['type' => 'system'])
            ->andReturn(false);

        // Act
        $this->pushNotificationService->send($this->user, $title, $body);

        // Assert: device2 token is cleared, device1 is NOT
        $this->assertSame('token-success', $device1->refresh()->push_token);
        $this->assertNull($device2->refresh()->push_token);

        // Assert: Notification is marked sent_at because at least one device succeeded
        $notification = Notification::where('user_id', $this->user->id)->first();
        $this->assertNotNull($notification->sent_at);
        $this->assertNull($notification->failed_at);
    }

    public function test_send_without_active_devices_marks_sent_directly(): void
    {
        // User with no devices with push tokens
        UserDevice::factory()->create([
            'user_id' => $this->user->id,
            'push_token' => null, // No token
        ]);

        // Fcm should not be called at all
        $this->fcmMock->shouldNotReceive('send');

        // Act
        $this->pushNotificationService->send($this->user, 'Title', 'Body');

        // Assert: Notification is created and marked sent_at
        $notification = Notification::where('user_id', $this->user->id)->first();
        $this->assertNotNull($notification);
        $this->assertNotNull($notification->sent_at);
        $this->assertNull($notification->failed_at);
    }

    public function test_send_supports_multiple_recipients(): void
    {
        $user2 = User::factory()->create();

        UserDevice::factory()->create([
            'user_id' => $this->user->id,
            'push_token' => 'token-user1',
        ]);
        UserDevice::factory()->create([
            'user_id' => $user2->id,
            'push_token' => 'token-user2',
        ]);

        $title = 'Broadcast';
        $body = 'Message';

        $this->fcmMock->shouldReceive('send')
            ->once()
            ->with('token-user1', $title, $body, ['type' => 'system'])
            ->andReturn(true);

        $this->fcmMock->shouldReceive('send')
            ->once()
            ->with('token-user2', $title, $body, ['type' => 'system'])
            ->andReturn(true);

        // Act with Eloquent collection
        $recipients = new Collection([$this->user, $user2]);
        $this->pushNotificationService->send($recipients, $title, $body);

        // Assert: Both notifications exist and are sent
        $this->assertSame(2, Notification::count());
        $this->assertSame(2, Notification::whereNotNull('sent_at')->count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
