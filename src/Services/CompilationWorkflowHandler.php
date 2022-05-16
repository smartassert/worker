<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\CompilationState;
use App\Event\JobCompiledEvent;
use App\Event\JobReadyEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Message\CompileSourceMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class CompilationWorkflowHandler implements EventSubscriberInterface
{
    public function __construct(
        private CompilationProgress $compilationProgress,
        private EventDispatcherInterface $eventDispatcher,
        private MessageBusInterface $messageBus,
        private SourcePathFinder $sourcePathFinder
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SourceCompilationPassedEvent::class => [
                ['dispatchNextCompileSourceMessage', 50],
                ['dispatchCompilationCompletedEvent', 60],
            ],
            JobReadyEvent::class => [
                ['dispatchNextCompileSourceMessage', 50],
            ],
        ];
    }

    public function dispatchNextCompileSourceMessage(): void
    {
        if (false === $this->compilationProgress->is(...CompilationState::getFinishedStates())) {
            $sourcePath = $this->sourcePathFinder->findNextNonCompiledPath();

            if (is_string($sourcePath)) {
                $this->messageBus->dispatch(new CompileSourceMessage($sourcePath));
            }
        }
    }

    public function dispatchCompilationCompletedEvent(): void
    {
        if ($this->compilationProgress->is(CompilationState::COMPLETE)) {
            $this->eventDispatcher->dispatch(new JobCompiledEvent());
        }
    }
}
