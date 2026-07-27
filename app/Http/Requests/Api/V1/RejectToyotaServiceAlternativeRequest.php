<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ToyotaServiceBooking;
use Illuminate\Foundation\Http\FormRequest;

class RejectToyotaServiceAlternativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof ToyotaServiceBooking
            && $booking->user_id === $this->user()?->getKey();
    }

    public function rules(): array
    {
        return [
            'primary_slot' => ['required', 'array:date,time_window'],
            'primary_slot.date' => ['required', 'date_format:Y-m-d'],
            'primary_slot.time_window' => ['required', 'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/'],
            'alternative_slot' => ['required', 'array:date,time_window'],
            'alternative_slot.date' => ['required', 'date_format:Y-m-d'],
            'alternative_slot.time_window' => ['required', 'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
