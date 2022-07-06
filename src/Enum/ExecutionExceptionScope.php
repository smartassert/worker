<?php

declare(strict_types=1);

namespace App\Enum;

enum ExecutionExceptionScope: string
{
    case TEST = 'test';
    case STEP = 'step';
}
