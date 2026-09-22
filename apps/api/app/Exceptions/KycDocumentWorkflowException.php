<?php

namespace App\Exceptions;

use App\Enums\KycDocumentWorkflowErrorCode;
use DomainException;

class KycDocumentWorkflowException extends DomainException
{
    public function __construct(
        private readonly KycDocumentWorkflowErrorCode $errorCode,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): KycDocumentWorkflowErrorCode
    {
        return $this->errorCode;
    }
}
