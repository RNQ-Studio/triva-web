<?php

namespace App\Http\Requests\Api\V1;

use App\Models\OtoxpertBooking;
use Illuminate\Foundation\Http\FormRequest;

abstract class OtoxpertScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof OtoxpertBooking
            && $this->user()?->can($this->ability(), $booking) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'primary_slot' => ['required', 'array:date,time_window'],
            'primary_slot.date' => ['required', 'date_format:Y-m-d'],
            'primary_slot.time_window' => [
                'required',
                'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/',
            ],
            'alternative_slot' => ['required', 'array:date,time_window'],
            'alternative_slot.date' => ['required', 'date_format:Y-m-d'],
            'alternative_slot.time_window' => [
                'required',
                'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/',
            ],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    abstract protected function ability(): string;
}
