<?php

namespace App\Http\Requests\Api\V1;

use App\Models\VisitEvent;
use Illuminate\Foundation\Http\FormRequest;

class AdminPlayStoreInstallsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Satu izin dengan statistik kunjungan: keduanya angka ringkas yang
        // tampil berdampingan pada dashboard analitik panel admin.
        return $this->user()?->can('viewAny', VisitEvent::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
