<?php

namespace App\Policies;

use App\Models\ToyotaServiceBooking;
use App\Models\User;
use App\Support\Enums\ToyotaServiceBookingStatus;

class ToyotaServiceBookingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ToyotaServiceBooking $booking): bool
    {
        return $booking->user_id === $user->getKey()
            || $user->can('service_bookings.view');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function respondToAlternative(User $user, ToyotaServiceBooking $booking): bool
    {
        return $booking->user_id === $user->getKey()
            && $booking->status === ToyotaServiceBookingStatus::AlternativeProposed;
    }

    public function reschedule(User $user, ToyotaServiceBooking $booking): bool
    {
        return $booking->user_id === $user->getKey()
            && $booking->status === ToyotaServiceBookingStatus::Confirmed;
    }

    public function cancel(User $user, ToyotaServiceBooking $booking): bool
    {
        return $booking->user_id === $user->getKey()
            && $booking->canCustomerCancel();
    }

    public function manage(User $user, ToyotaServiceBooking $booking): bool
    {
        return $user->can('service_bookings.update');
    }
}
