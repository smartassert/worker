<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\JobCompletedCheckMessage;

class JobCompletedCheckHandler
{
    public function __invoke(JobCompletedCheckMessage $jobCompletedCheck): void
    {
    }
}
