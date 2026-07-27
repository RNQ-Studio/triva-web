<?php

namespace App\Http\Requests\Api\V1;

class RescheduleOtoxpertBookingRequest extends OtoxpertScheduleRequest
{
    protected function ability(): string
    {
        return 'reschedule';
    }
}
