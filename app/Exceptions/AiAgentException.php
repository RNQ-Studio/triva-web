<?php

namespace App\Exceptions;

use Throwable;

class AiAgentException extends MarketDataProviderException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
