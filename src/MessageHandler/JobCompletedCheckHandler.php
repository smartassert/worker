<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\JobCompletedCheckMessage;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class JobCompletedCheckHandler implements MessageHandlerInterface
{
    public function __invoke(JobCompletedCheckMessage $jobCompletedCheck): void
    {
    }
}
