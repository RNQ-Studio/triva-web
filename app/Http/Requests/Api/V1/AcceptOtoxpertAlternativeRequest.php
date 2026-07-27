<?php

namespace App\Http\Requests\Api\V1;

use App\Models\OtoxpertBooking;
use Illuminate\Foundation\Http\FormRequest;

class AcceptOtoxpertAlternativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof OtoxpertBooking
            && $this->user()?->can('respondToAlternative', $booking) === true;
    }

    public function rules(): array
    {
        return [];
    }
}
