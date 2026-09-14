<?php

namespace App\Enums;

enum WebhookEventStatus: string
{
    case RECEIVED = 'RECEIVED';
    case VERIFIED = 'VERIFIED';
    case PROCESSING = 'PROCESSING';
    case PROCESSED = 'PROCESSED';
    case IGNORED = 'IGNORED';
    case FAILED = 'FAILED';
}
