<?php

namespace App\Enums;

enum ProviderInitiationStatus: string
{
    case NOT_SENT = 'NOT_SENT';
    case SENT_CONFIRMED = 'SENT_CONFIRMED';
    case SENT_UNKNOWN = 'SENT_UNKNOWN';
}
