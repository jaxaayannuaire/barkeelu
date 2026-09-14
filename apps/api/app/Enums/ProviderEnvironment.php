<?php

namespace App\Enums;

enum ProviderEnvironment: string
{
    case TEST = 'TEST';
    case PRODUCTION = 'PRODUCTION';
}
