<?php

namespace App\Http\Requests\Api\V1;

class RejectOtoxpertAlternativeRequest extends OtoxpertScheduleRequest
{
    protected function ability(): string
    {
        return 'respondToAlternative';
    }
}
