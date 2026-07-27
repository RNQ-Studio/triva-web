<?php

namespace App\Policies;

use App\Models\OtoxpertBooking;
use App\Models\User;
use App\Support\Enums\OtoxpertBookingStatus;

class OtoxpertBookingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function manageAny(User $user): bool
    {
        return $user->can('service_bookings.viewAny');
    }

    public function view(User $user, OtoxpertBooking $booking): bool
    {
        return $booking->user_id === $user->getKey()
            || ($user->can('service_bookings.view')
                && $booking->workshop->canBeManagedBy($user));
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function respondToAlternative(
        User $user,
        OtoxpertBooking $booking,
    ): bool {
        return $booking->user_id === $user->getKey()
            && $booking->status === OtoxpertBookingStatus::AlternativeProposed;
    }

    public function reschedule(User $user, OtoxpertBooking $booking): bool
    {
        return $booking->user_id === $user->getKey()
            && $booking->status === OtoxpertBookingStatus::Confirmed;
    }

    public function cancel(User $user, OtoxpertBooking $booking): bool
    {
        return $booking->user_id === $user->getKey()
            && $booking->canCustomerCancel();
    }

    public function manage(User $user, OtoxpertBooking $booking): bool
    {
        return $booking->workshop->canBeManagedBy($user);
    }
}
