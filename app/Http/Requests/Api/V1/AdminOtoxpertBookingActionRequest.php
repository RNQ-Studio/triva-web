<?php

namespace App\Http\Requests\Api\V1;

use App\Models\OtoxpertBooking;
use App\Support\Enums\OtoxpertAdminAction;
use App\Support\Enums\OtoxpertReasonCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminOtoxpertBookingActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof OtoxpertBooking
            && $this->user()?->can('manage', $booking) === true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(OtoxpertAdminAction::class)],
            'operator_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                'required_if:action,assign',
            ],
            'slot' => [
                'nullable',
                'array:date,time_window',
                'required_if:action,confirm,propose_alternative,confirm_reschedule',
            ],
            'slot.date' => [
                'nullable',
                'date_format:Y-m-d',
                'required_with:slot',
            ],
            'slot.time_window' => [
                'nullable',
                'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/',
                'required_with:slot',
            ],
            'reason_code' => [
                'nullable',
                Rule::enum(OtoxpertReasonCode::class),
                'required_if:action,propose_alternative,reject,mark_no_show,cancel',
            ],
            'reason' => [
                'nullable',
                'string',
                'min:5',
                'max:1000',
                'required_if:action,propose_alternative,reject,mark_no_show,cancel',
            ],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'arrival_instructions' => ['nullable', 'string', 'max:2000'],
            'external_booking_number' => [
                'nullable',
                'string',
                'max:100',
            ],
            'quoted_price_min' => [
                'nullable',
                'integer',
                'min:1',
                'max:1000000000',
            ],
            'quoted_price_max' => [
                'nullable',
                'integer',
                'gte:quoted_price_min',
                'max:1000000000',
            ],
            'quoted_price_type' => [
                'nullable',
                Rule::in(['from', 'range']),
                'required_with:quoted_price_min',
            ],
            'quoted_price_valid_until' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'internal_note' => ['nullable', 'string', 'max:3000'],
            'follow_up_outcome' => ['nullable', 'string', 'max:100'],
        ];
    }
}
