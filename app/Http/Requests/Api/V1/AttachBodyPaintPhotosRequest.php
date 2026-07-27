<?php

namespace App\Http\Requests\Api\V1;

use App\Models\BodyPaintEstimate;
use App\Support\Enums\BodyPaintPhotoType;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachBodyPaintPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $estimate = $this->route('estimate');

        return $estimate instanceof BodyPaintEstimate
            && ($this->user()?->can('updateCustomer', $estimate) ?? false);
    }

    public function rules(): array
    {
        /** @var BodyPaintEstimate|null $estimate */
        $estimate = $this->route('estimate');
        $userId = $this->user()?->getKey();

        return [
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['required', 'array:asset_id,damage_id,photo_type'],
            'photos.*.asset_id' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('assets', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('user_id', $userId)
                        ->where('category', 'body-paint-estimate-photo')
                        ->where('is_protected', true)
                        ->where('status', 'active')
                        ->whereNull('deleted_at'),
                ),
            ],
            'photos.*.damage_id' => [
                'nullable',
                'uuid',
                Rule::exists('body_paint_estimate_damages', 'id')->where(
                    'estimate_id',
                    $estimate?->getKey(),
                ),
            ],
            'photos.*.photo_type' => [
                'required',
                Rule::enum(BodyPaintPhotoType::class),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('photos', []) as $index => $photo) {
                if (! is_array($photo)) {
                    continue;
                }
                $isClose = ($photo['photo_type'] ?? null) === 'close';
                $hasDamage = filled($photo['damage_id'] ?? null);
                if ($isClose !== $hasDamage) {
                    $validator->errors()->add(
                        "photos.{$index}.damage_id",
                        $isClose
                            ? 'Foto dekat wajib terkait dengan satu kerusakan.'
                            : 'Foto konteks tidak boleh terkait dengan satu panel.',
                    );
                }
            }
        });
    }
}
