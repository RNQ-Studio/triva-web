<?php

namespace App\Observers;

use App\Models\Notification;
use App\Support\NotificationEmailCopy;

class NotificationObserver
{
    public function created(Notification $notification): void
    {
        if (NotificationEmailCopy::autoDispatchSuspended()) {
            return;
        }

        NotificationEmailCopy::dispatchFor([(string) $notification->getKey()]);
    }
}
