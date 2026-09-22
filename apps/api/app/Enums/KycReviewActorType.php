<?php

namespace App\Enums;

enum KycReviewActorType: string
{
    case HUMAN = 'HUMAN';
    case SYSTEM = 'SYSTEM';
}
