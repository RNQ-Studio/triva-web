<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Asset;
use App\Support\Enums\AppraisalPhotoAngle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachAppraisalPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appraisal = $this->route('appraisal');

        return $appraisal !== null
            && ($this->user()?->can('update', $appraisal) ?? false);
    }

    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*.angle' => [
                'required',
                Rule::enum(AppraisalPhotoAngle::class),
                'distinct',
            ],
            'photos.*.asset_id' => [
                'required',
                'uuid',
                'distinct',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $owned = Asset::query()
                        ->whereKey($value)
                        ->where('user_id', $this->user()?->getKey())
                        ->where('category', 'appraisal-photo')
                        ->where('mime_type', 'like', 'image/%')
                        ->exists();

                    if (! $owned) {
                        $fail('Foto appraisal tidak valid atau bukan milik Anda.');
                    }
                },
            ],
        ];
    }
}
