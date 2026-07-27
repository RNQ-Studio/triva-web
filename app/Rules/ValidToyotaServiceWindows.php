<?php

namespace App\Rules;

use App\Support\ToyotaServiceWindowRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidToyotaServiceWindows implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $error = ToyotaServiceWindowRules::error($value);
        if ($error !== null) {
            $fail($error);
        }
    }
}
