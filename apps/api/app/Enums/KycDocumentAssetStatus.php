<?php

namespace App\Enums;

enum KycDocumentAssetStatus: string
{
    case PENDING = 'PENDING';
    case READY = 'READY';
    case FAILED = 'FAILED';
}
