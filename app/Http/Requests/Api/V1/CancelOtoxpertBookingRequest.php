<?php

namespace App\Http\Requests\Api\V1;

use App\Models\OtoxpertBooking;
use Illuminate\Foundation\Http\FormRequest;

class CancelOtoxpertBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof OtoxpertBooking
            && $this->user()?->can('cancel', $booking) === true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
