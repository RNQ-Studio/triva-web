<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ToyotaServiceBooking;
use App\Models\User;
use App\Support\Enums\BenefitVerificationSource;
use App\Support\Enums\ToyotaServiceAdminAction;
use App\Support\Enums\ToyotaServiceReasonCode;
use App\Support\Enums\VehicleBenefitStatus;
use App\Support\Enums\VehicleBenefitType;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminToyotaServiceBookingActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof ToyotaServiceBooking
            && ($this->user()?->can('manage', $booking) ?? false);
    }

    public function rules(): array
    {
        $action = $this->input('action');
        $requiresConfirmedSlot = in_array($action, ['confirm', 'confirm_reschedule'], true);
        $requiresConfirmationDetail = $requiresConfirmedSlot || $action === 'propose_alternative';

        return [
            'action' => ['required', Rule::enum(ToyotaServiceAdminAction::class)],
            'advisor_id' => [
                Rule::requiredIf($action === 'assign'),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('is_active', true),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $advisor = User::query()->find($value);
                    if ($advisor === null || ! $advisor->can('service_bookings.update')) {
                        $fail('User yang dipilih bukan Service Advisor/staf booking berwenang.');
                    }
                },
            ],
            'confirmed_slot' => [Rule::requiredIf($requiresConfirmedSlot), 'nullable', 'array:date,time_window'],
            'confirmed_slot.date' => [Rule::requiredIf($requiresConfirmedSlot), 'nullable', 'date_format:Y-m-d'],
            'confirmed_slot.time_window' => [
                Rule::requiredIf($requiresConfirmedSlot),
                'nullable',
                'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/',
            ],
            'proposed_slot' => [
                Rule::requiredIf($action === 'propose_alternative'),
                'nullable',
                'array:date,time_window',
            ],
            'proposed_slot.date' => [
                Rule::requiredIf($action === 'propose_alternative'),
                'nullable',
                'date_format:Y-m-d',
            ],
            'proposed_slot.time_window' => [
                Rule::requiredIf($action === 'propose_alternative'),
                'nullable',
                'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/',
            ],
            'proposal_reason' => [
                Rule::requiredIf($action === 'propose_alternative'),
                'nullable',
                'string',
                'min:5',
                'max:1000',
            ],
            'proposal_expires_at' => [
                Rule::requiredIf($action === 'propose_alternative'),
                'nullable',
                'date',
                'after:now',
            ],
            'pic_name' => [
                Rule::requiredIf($requiresConfirmationDetail),
                'nullable',
                'string',
                'max:120',
            ],
            'arrival_instructions' => [
                Rule::requiredIf($requiresConfirmationDetail),
                'nullable',
                'string',
                'min:5',
                'max:2000',
            ],
            'external_booking_number' => ['nullable', 'string', 'max:120'],
            'reason_code' => [
                Rule::requiredIf(in_array($action, ['reject', 'cancel', 'mark_no_show'], true)),
                'nullable',
                Rule::enum(ToyotaServiceReasonCode::class),
            ],
            'reason' => [
                Rule::requiredIf(in_array($action, ['reject', 'cancel', 'mark_no_show'], true)),
                'nullable',
                'string',
                'min:5',
                'max:1000',
            ],
            'note' => ['nullable', 'string', 'max:1000'],
            'benefit_type' => [
                Rule::requiredIf($action === 'verify_benefit'),
                'nullable',
                Rule::enum(VehicleBenefitType::class),
            ],
            'benefit_status' => [
                Rule::requiredIf($action === 'verify_benefit'),
                'nullable',
                Rule::enum(VehicleBenefitStatus::class),
                Rule::notIn([VehicleBenefitStatus::Unknown->value]),
            ],
            'verification_source' => [
                Rule::requiredIf(
                    $action === 'verify_benefit'
                    && in_array($this->input('benefit_status'), ['active', 'inactive'], true)
                ),
                'nullable',
                Rule::enum(BenefitVerificationSource::class),
            ],
            'benefit_valid_until' => ['nullable', 'date', 'after:now'],
            'benefit_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
