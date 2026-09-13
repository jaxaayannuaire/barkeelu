<?php

namespace App\Enums;

enum LedgerAccountType: string
{
    case ASSET = 'ASSET';
    case LIABILITY = 'LIABILITY';
    case REVENUE = 'REVENUE';
    case EXPENSE = 'EXPENSE';
}
