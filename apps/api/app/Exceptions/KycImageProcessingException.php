<?php

namespace App\Exceptions;

use App\Enums\KycImageProcessingErrorCode;
use RuntimeException;

class KycImageProcessingException extends RuntimeException
{
    public function __construct(
        private readonly KycImageProcessingErrorCode $errorCode,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): KycImageProcessingErrorCode
    {
        return $this->errorCode;
    }
}
