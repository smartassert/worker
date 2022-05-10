<?php

declare(strict_types=1);

namespace App\Entity;

enum WorkerEventState: string
{
    case AWAITING = 'awaiting';
    case QUEUED = 'queued';
    case SENDING = 'sending';
    case FAILED = 'failed';
    case COMPLETE = 'complete';
}
