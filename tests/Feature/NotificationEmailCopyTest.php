<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationEmailCopyJob;
use App\Jobs\SendPushNotificationJob;
use App\Mail\NotificationCopyMail;
use App\Models\Notification;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Services\PushNotificationService;
use App\Support\NotificationEmailCopy;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationEmailCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notification_email.copy_recipients' => ['owner@example.com'],
            'notification_email.queue' => 'triva',
        ]);
    }

    public function test_new_notification_queues_one_email_copy_job_on_worker_queue(): void
    {
        Bus::fake([SendNotificationEmailCopyJob::class]);

        $notification = Notification::factory()->create();

        Bus::assertDispatchedTimes(SendNotificationEmailCopyJob::class, 1);
        Bus::assertDispatched(
            SendNotificationEmailCopyJob::class,
            fn (SendNotificationEmailCopyJob $job): bool => $job->notificationIds === [(string) $notification->getKey()]
                && $job->queue === 'triva'
                && $job->afterCommit === true
        );
    }

    public function test_nothing_is_queued_when_no_recipient_is_configured(): void
    {
        config(['notification_email.copy_recipients' => []]);
        Bus::fake([SendNotificationEmailCopyJob::class]);

        Notification::factory()->create();

        Bus::assertNotDispatched(SendNotificationEmailCopyJob::class);
        $this->assertFalse(NotificationEmailCopy::enabled());
    }

    public function test_recipients_are_normalised_and_invalid_addresses_are_dropped(): void
    {
        config(['notification_email.copy_recipients' => ['not-an-email', ' Owner@Example.com ', 'owner@example.com', '']]);

        $this->assertSame(['owner@example.com'], NotificationEmailCopy::recipients());
    }

    public function test_push_broadcast_queues_a_single_digest_instead_of_one_email_per_user(): void
    {
        Bus::fake([SendNotificationEmailCopyJob::class, SendPushNotificationJob::class]);
        $users = User::factory()->count(3)->create();

        app(PushNotificationService::class)->send($users, 'Promo akhir pekan', 'Servis hemat 20%.', [], 'promo');

        Bus::assertDispatchedTimes(SendPushNotificationJob::class, 3);
        Bus::assertDispatchedTimes(SendNotificationEmailCopyJob::class, 1);
        Bus::assertDispatched(
            SendNotificationEmailCopyJob::class,
            fn (SendNotificationEmailCopyJob $job): bool => count($job->notificationIds) === 3
        );
        $this->assertFalse(NotificationEmailCopy::autoDispatchSuspended());
    }

    public function test_admin_fan_out_queues_a_single_digest(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Bus::fake([SendNotificationEmailCopyJob::class, SendPushNotificationJob::class]);
        User::factory()->count(2)->create(['is_active' => true])
            ->each(fn (User $admin) => $admin->assignRole('admin'));

        app(AdminNotificationService::class)->notify('credit_simulation', 'Simulasi kredit baru', 'Pelanggan menghitung angsuran.', []);

        Bus::assertDispatchedTimes(SendPushNotificationJob::class, 2);
        Bus::assertDispatchedTimes(SendNotificationEmailCopyJob::class, 1);
        Bus::assertDispatched(
            SendNotificationEmailCopyJob::class,
            fn (SendNotificationEmailCopyJob $job): bool => count($job->notificationIds) === 2
        );
    }

    public function test_job_sends_one_email_to_every_recipient_with_notification_details(): void
    {
        Bus::fake([SendNotificationEmailCopyJob::class]);
        Mail::fake();
        config(['notification_email.copy_recipients' => ['owner@example.com', 'second@example.com']]);

        $user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
        $notification = Notification::factory()->create([
            'user_id' => $user->getKey(),
            'title' => 'Booking dikonfirmasi',
            'body' => 'Servis Anda dijadwalkan besok pukul 09.00.',
            'type' => 'toyota_service_booking',
            'data' => ['type' => 'toyota_service_booking', 'route' => '/toyota-service/bookings/abc'],
            'sent_at' => null,
            'failed_at' => null,
        ]);

        (new SendNotificationEmailCopyJob([(string) $notification->getKey()]))->handle();

        Mail::assertSentCount(1);
        Mail::assertSent(NotificationCopyMail::class, function (NotificationCopyMail $mail): bool {
            $html = $mail->render();

            return $mail->hasTo('owner@example.com')
                && $mail->hasTo('second@example.com')
                && $mail->hasSubject('['.config('app.name').'] Booking Servis Toyota: Booking dikonfirmasi')
                && str_contains($html, 'Servis Anda dijadwalkan besok pukul 09.00.')
                && str_contains($html, 'Budi Santoso')
                && str_contains($html, 'budi@example.com')
                && str_contains($html, '/toyota-service/bookings/abc')
                && str_contains($html, 'WIB');
        });

        $notification->refresh();
        $this->assertNull($notification->sent_at);
        $this->assertNull($notification->failed_at);
    }

    public function test_digest_email_for_broadcast_lists_every_recipient_once(): void
    {
        Bus::fake([SendNotificationEmailCopyJob::class]);
        Mail::fake();

        $first = User::factory()->create(['email' => 'first@example.com']);
        $second = User::factory()->create(['email' => 'second@example.com']);
        $notifications = collect([$first, $second])->map(fn (User $user): Notification => Notification::factory()->create([
            'user_id' => $user->getKey(),
            'title' => 'Promo akhir pekan',
            'body' => 'Servis hemat 20%.',
            'type' => 'promo',
            'data' => ['type' => 'promo'],
        ]));

        (new SendNotificationEmailCopyJob($notifications->map(fn (Notification $n): string => (string) $n->getKey())->all()))->handle();

        Mail::assertSentCount(1);
        Mail::assertSent(NotificationCopyMail::class, function (NotificationCopyMail $mail): bool {
            $html = $mail->render();

            return $mail->hasSubject('['.config('app.name').'] 2 notifikasi: Promo akhir pekan')
                && substr_count($html, 'Servis hemat 20%.') === 1
                && str_contains($html, 'first@example.com')
                && str_contains($html, 'second@example.com');
        });
    }

    public function test_job_is_a_noop_without_recipients_or_notifications(): void
    {
        Mail::fake();

        (new SendNotificationEmailCopyJob(['01J0000000000000000000000X']))->handle();

        config(['notification_email.copy_recipients' => []]);
        Bus::fake([SendNotificationEmailCopyJob::class]);
        $notification = Notification::factory()->create();
        (new SendNotificationEmailCopyJob([(string) $notification->getKey()]))->handle();

        Mail::assertNothingSent();
    }
}
