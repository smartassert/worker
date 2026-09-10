<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\EventDispatcher\ExecutionCompletedEventDispatcher;
use App\Message\ExecutionCompletedCheckMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
class ExecutionCompletedCheckHandler
{
    public function __construct(
        private readonly ExecutionCompletedEventDispatcher $jobCompleteEventDispatcher,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(ExecutionCompletedCheckMessage $jobCompletedCheck): void
    {
        $this->jobCompleteEventDispatcher->dispatch();
    }
}
