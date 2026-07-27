<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ToyotaServiceBooking;
use App\Support\Enums\ToyotaServiceFulfillmentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin ToyotaServiceBooking */
class ToyotaServiceBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timezone = $this->serviceLocation->timezone;
        $advisorVisible = $this->confirmed_at !== null;

        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'status' => $this->status->value,
            'status_label' => $this->status->customerLabel(),
            'allowed_customer_actions' => $this->allowedCustomerActions(),
            'can_cancel' => $this->canCustomerCancel(),
            'cancellation_cutoff_hours' => $this->serviceLocation->cancellation_cutoff_hours,
            'is_confirmed' => $this->status->value === 'confirmed'
                || $this->confirmed_at !== null,
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'service_location' => new ToyotaServiceLocationResource(
                $this->whenLoaded('serviceLocation')
            ),
            'service_type' => new ToyotaServiceTypeResource($this->whenLoaded('serviceType')),
            'fulfillment_type' => $this->fulfillment_type->value,
            'fulfillment_label' => $this->fulfillment_type->label(),
            'current_mileage' => $this->current_mileage,
            'complaint' => $this->complaint,
            'requested_slots' => [
                'primary' => $this->slot(
                    $this->primary_start_at,
                    $this->primary_end_at,
                    $timezone,
                ),
                'alternative' => $this->slot(
                    $this->alternative_start_at,
                    $this->alternative_end_at,
                    $timezone,
                ),
            ],
            'proposed_slot' => $this->proposed_start_at === null ? null : [
                ...$this->slot($this->proposed_start_at, $this->proposed_end_at, $timezone),
                'context' => $this->proposal_context,
                'reason' => $this->proposal_reason,
                'expires_at' => $this->proposal_expires_at?->toIso8601String(),
                'pic_name' => $this->proposed_pic_name,
                'arrival_instructions' => $this->proposed_arrival_instructions,
                'external_booking_number' => $this->proposed_external_booking_number,
            ],
            'confirmed_slot' => $this->slot(
                $this->confirmed_start_at,
                $this->confirmed_end_at,
                $timezone,
            ),
            'reschedule_request' => $this->reschedule_primary_start_at === null ? null : [
                'primary' => $this->slot(
                    $this->reschedule_primary_start_at,
                    $this->reschedule_primary_end_at,
                    $timezone,
                ),
                'alternative' => $this->slot(
                    $this->reschedule_alternative_start_at,
                    $this->reschedule_alternative_end_at,
                    $timezone,
                ),
                'reason' => $this->reschedule_reason,
            ],
            'ths_address' => $this->fulfillment_type === ToyotaServiceFulfillmentType::Ths
                ? $this->ths_address
                : null,
            'ths_city' => $this->fulfillment_type === ToyotaServiceFulfillmentType::Ths
                ? $this->ths_city
                : null,
            'ths_latitude' => $this->fulfillment_type === ToyotaServiceFulfillmentType::Ths
                && $this->ths_latitude !== null
                    ? (float) $this->ths_latitude
                    : null,
            'ths_longitude' => $this->fulfillment_type === ToyotaServiceFulfillmentType::Ths
                && $this->ths_longitude !== null
                    ? (float) $this->ths_longitude
                    : null,
            'ths_location_notes' => $this->fulfillment_type === ToyotaServiceFulfillmentType::Ths
                ? $this->ths_location_notes
                : null,
            'contact_channel' => $this->contact_channel->value,
            'contact_channel_label' => $this->contact_channel->label(),
            'photos' => ToyotaServiceBookingPhotoResource::collection($this->whenLoaded('photos')),
            'benefit_checks' => VehicleBenefitCheckResource::collection(
                $this->whenLoaded('benefitChecks')
            ),
            'timeline' => ToyotaServiceBookingTimelineResource::collection(
                $this->whenLoaded('statusHistories')
            ),
            'service_advisor' => $advisorVisible ? [
                'name' => $this->pic_name ?? $this->assignedServiceAdvisor?->name,
                'phone' => $this->serviceLocation->phone,
                'contact_source' => 'service_location',
            ] : null,
            'arrival_instructions' => $this->arrival_instructions,
            'external_booking_number' => $this->external_booking_number,
            'reason_code' => $this->reason_code,
            'reason' => $this->reason,
            'source' => [
                'appraisal_id' => $this->source_appraisal_id,
                'bp_estimate_id' => $this->source_bp_estimate_id,
            ],
            'submitted_at' => $this->submitted_at->toIso8601String(),
            'due_at' => $this->due_at->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     date: string,
     *     time_window: string,
     *     timezone: string,
     *     start_at: string,
     *     end_at: string
     * }|null
     */
    protected function slot(?Carbon $start, ?Carbon $end, string $timezone): ?array
    {
        if ($start === null || $end === null) {
            return null;
        }

        $localStart = $start->copy()->setTimezone($timezone);
        $localEnd = $end->copy()->setTimezone($timezone);

        return [
            'date' => $localStart->toDateString(),
            'time_window' => $localStart->format('H:i').'-'.$localEnd->format('H:i'),
            'timezone' => $timezone,
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
        ];
    }
}
