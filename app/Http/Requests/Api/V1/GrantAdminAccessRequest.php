<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class GrantAdminAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $managedUser = $this->route('user');

        return $managedUser instanceof User
            && ($this->user()?->can('update', $managedUser) ?? false);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
