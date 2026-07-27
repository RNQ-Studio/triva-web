<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ToyotaServiceBooking;
use Illuminate\Foundation\Http\FormRequest;

class AcceptToyotaServiceAlternativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof ToyotaServiceBooking
            && $booking->user_id === $this->user()?->getKey();
    }

    public function rules(): array
    {
        return [];
    }
}
