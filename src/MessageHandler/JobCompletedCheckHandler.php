<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\EventDispatcher\JobCompleteEventDispatcher;
use App\Exception\JobNotFoundException;
use App\Message\JobCompletedCheckMessage;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class JobCompletedCheckHandler implements MessageHandlerInterface
{
    public function __construct(
        private readonly JobCompleteEventDispatcher $jobCompleteEventDispatcher,
    ) {
    }

    /**
     * @throws JobNotFoundException
     */
    public function __invoke(JobCompletedCheckMessage $jobCompletedCheck): void
    {
        $this->jobCompleteEventDispatcher->dispatch();
    }
}
