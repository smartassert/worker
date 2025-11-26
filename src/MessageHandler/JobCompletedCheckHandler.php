<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\EventDispatcher\JobCompleteEventDispatcher;
use App\Message\JobCompletedCheckMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
class JobCompletedCheckHandler
{
    public function __construct(
        private readonly JobCompleteEventDispatcher $jobCompleteEventDispatcher,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(JobCompletedCheckMessage $jobCompletedCheck): void
    {
        $this->jobCompleteEventDispatcher->dispatch();
    }
}
