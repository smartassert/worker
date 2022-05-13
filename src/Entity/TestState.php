<?php

declare(strict_types=1);

namespace App\Entity;

enum TestState: string
{
    case AWAITING = 'awaiting';
    case RUNNING = 'running';
    case FAILED = 'failed';
    case COMPLETE = 'complete';
    case CANCELLED = 'cancelled';
}
