<?php

namespace App\Enums;

enum KycDocumentStatus: string
{
    case UPLOADED = 'UPLOADED';
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
    case EXPIRED = 'EXPIRED';
}
