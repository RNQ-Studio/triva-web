<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $isProtectedVehiclePhoto = in_array(
            $this->string('type')->toString(),
            ['appraisal-photo', 'toyota-service-photo'],
            true,
        );

        // Normalisasi default boolean agar 'is_protected' tidak wajib dikirim klien.
        $this->merge([
            'is_protected' => $isProtectedVehiclePhoto || $this->boolean('is_protected'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isProtectedVehiclePhoto = in_array(
            $this->string('type')->toString(),
            ['appraisal-photo', 'toyota-service-photo'],
            true,
        );

        return [
            'file' => [
                'required',
                'file',
                $isProtectedVehiclePhoto ? 'max:10240' : 'max:51200',
                $isProtectedVehiclePhoto
                    ? 'mimes:jpeg,jpg,png,heic,heif'
                    : 'mimes:jpeg,jpg,png,webp,gif,svg,mp4,mov,avi,webm,mp3,wav,ogg,m4a,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv',
            ],
            'type' => ['required', 'string', 'max:255'], // kategori logis file → folder GCS & kolom category
            'retain_until' => ['nullable', 'date'], // tidak diisi → NULL → file permanen
            'is_protected' => ['boolean'],
        ];
    }
}
