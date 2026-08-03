<?php

namespace App\Http\Requests\Api\V1;

use App\Models\BodyPaintEstimate;
use App\Support\Enums\BodyPaintAdminAction;
use App\Support\Enums\BodyPaintSeverity;
use App\Support\Enums\BodyPaintWorkType;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminBodyPaintEstimateActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $estimate = $this->route('estimate');

        return $estimate instanceof BodyPaintEstimate
            && ($this->user()?->can('manage', $estimate) ?? false);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(BodyPaintAdminAction::class)],
            'estimator_id' => [
                'required_if:action,assign',
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'reason_code' => [
                'required_if:action,request_photos',
                'nullable',
                'string',
                'max:64',
            ],
            'reason' => [
                'required_if:action,request_photos',
                'nullable',
                'string',
                'min:5',
                'max:2000',
            ],
            'rejected_photo_ids' => [
                'required_if:action,request_photos',
                'nullable',
                'array',
                'min:1',
                'max:10',
            ],
            'rejected_photo_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('body_paint_damage_photos', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where(
                            'estimate_id',
                            $this->route('estimate')?->getKey(),
                        )
                        ->where('review_status', '!=', 'rejected'),
                ),
            ],
            'items' => [
                'required_if:action,publish',
                'nullable',
                'array',
                'min:1',
                'max:50',
            ],
            'items.*' => [
                'required',
                'array:damage_id,severity,work_type,labor_low,labor_high,material_low,material_high,parts_low,parts_high,other_low,other_high,duration_min_hours,duration_max_hours,recommendation',
            ],
            'items.*.damage_id' => [
                'required',
                'uuid',
                Rule::exists('body_paint_estimate_damages', 'id')->where(
                    'estimate_id',
                    $this->route('estimate')?->getKey(),
                ),
            ],
            'items.*.severity' => [
                'required',
                Rule::enum(BodyPaintSeverity::class),
                Rule::notIn([BodyPaintSeverity::Unsure->value]),
            ],
            'items.*.work_type' => [
                'required',
                Rule::enum(BodyPaintWorkType::class),
            ],
            'items.*.labor_low' => ['required', 'integer', 'min:0'],
            'items.*.labor_high' => [
                'required',
                'integer',
                'gte:items.*.labor_low',
            ],
            'items.*.material_low' => ['required', 'integer', 'min:0'],
            'items.*.material_high' => [
                'required',
                'integer',
                'gte:items.*.material_low',
            ],
            'items.*.parts_low' => ['required', 'integer', 'min:0'],
            'items.*.parts_high' => [
                'required',
                'integer',
                'gte:items.*.parts_low',
            ],
            'items.*.other_low' => ['required', 'integer', 'min:0'],
            'items.*.other_high' => [
                'required',
                'integer',
                'gte:items.*.other_low',
            ],
            'items.*.duration_min_hours' => [
                'required',
                'integer',
                'min:1',
                'max:1000',
            ],
            'items.*.duration_max_hours' => [
                'required',
                'integer',
                'gte:items.*.duration_min_hours',
                'max:1000',
            ],
            'items.*.recommendation' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'assumptions' => [
                'required_if:action,publish',
                'nullable',
                'array',
                'max:20',
            ],
            'assumptions.*' => ['string', 'max:500'],
            'disclaimer' => [
                'required_if:action,publish',
                'nullable',
                'string',
                'min:20',
                'max:3000',
            ],
            'valid_days' => [
                'required_if:action,publish',
                'nullable',
                'integer',
                'min:1',
                'max:90',
            ],
            'override_reason_code' => [
                'nullable',
                'string',
                'max:64',
            ],
            'override_reason' => ['nullable', 'string', 'min:5', 'max:2000'],
            'inspection_at' => [
                'required_if:action,schedule_inspection',
                'nullable',
                'date',
                'after:now',
            ],
            'inspection_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
