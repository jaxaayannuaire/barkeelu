<?php

namespace App\Enums;

enum ReconciliationResult: string
{
    case MATCHED = 'MATCHED';
    case MISSING_INTERNAL = 'MISSING_INTERNAL';
    case MISSING_PROVIDER = 'MISSING_PROVIDER';
    case AMOUNT_MISMATCH = 'AMOUNT_MISMATCH';
    case STATUS_MISMATCH = 'STATUS_MISMATCH';
    case REVIEW_REQUIRED = 'REVIEW_REQUIRED';
}
