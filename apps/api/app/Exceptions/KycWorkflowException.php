<?php

namespace App\Exceptions;

use App\Enums\KycWorkflowErrorCode;
use DomainException;

class KycWorkflowException extends DomainException
{
    public function __construct(
        private readonly KycWorkflowErrorCode $errorCode,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): KycWorkflowErrorCode
    {
        return $this->errorCode;
    }
}
