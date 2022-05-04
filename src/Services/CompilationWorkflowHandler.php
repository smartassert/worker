<?php

declare(strict_types=1);

namespace App\Services;

use App\Event\JobCompiledEvent;
use App\Event\JobReadyEvent;
use App\Event\SourceCompilation\PassedEvent as SourceCompilationPassedEvent;
use App\Message\CompileSourceMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class CompilationWorkflowHandler implements EventSubscriberInterface
{
    public function __construct(
        private CompilationState $compilationState,
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
        if (false === $this->compilationState->is(...CompilationState::FINISHED_STATES)) {
            $sourcePath = $this->sourcePathFinder->findNextNonCompiledPath();

            if (is_string($sourcePath)) {
                $this->messageBus->dispatch(new CompileSourceMessage($sourcePath));
            }
        }
    }

    public function dispatchCompilationCompletedEvent(): void
    {
        if (CompilationState::STATE_COMPLETE === (string) $this->compilationState) {
            $this->eventDispatcher->dispatch(new JobCompiledEvent());
        }
    }
}
