<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class ToyotaServiceConflictException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode = 'TOYOTA_SERVICE_STATE_CONFLICT',
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 409, code: $this->errorCode);
    }
}
