<?php

namespace App\Enums;

enum LedgerEntryDirection: string
{
    case DEBIT = 'DEBIT';
    case CREDIT = 'CREDIT';
}
