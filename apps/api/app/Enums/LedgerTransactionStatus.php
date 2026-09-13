<?php

namespace App\Enums;

enum LedgerTransactionStatus: string
{
    case DRAFT = 'DRAFT';
    case POSTED = 'POSTED';
}
