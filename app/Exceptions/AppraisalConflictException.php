<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AppraisalConflictException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 409, code: 'APPRAISAL_STATE_CONFLICT');
    }
}
