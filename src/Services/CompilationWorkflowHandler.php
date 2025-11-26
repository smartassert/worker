<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\CompilationState;
use App\Event\EmittableEvent\JobStartedEvent;
use App\Event\EmittableEvent\SourceCompilationPassedEvent;
use App\Event\JobCompiledEvent;
use App\Message\CompileSourceMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class CompilationWorkflowHandler implements EventSubscriberInterface
{
    public function __construct(
        private CompilationProgress $compilationProgress,
        private EventDispatcherInterface $eventDispatcher,
        private MessageBusInterface $messageBus,
        private SourcePathFinder $sourcePathFinder,
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
            JobStartedEvent::class => [
                ['dispatchNextCompileSourceMessage', -50],
            ],
        ];
    }

    /**
     * @throws ExceptionInterface
     */
    public function dispatchNextCompileSourceMessage(JobStartedEvent|SourceCompilationPassedEvent $event): void
    {
        if (false === CompilationState::isEndState($this->compilationProgress->get())) {
            $sourcePath = $this->sourcePathFinder->findNextNonCompiledPath();

            if (is_string($sourcePath)) {
                $this->messageBus->dispatch(new CompileSourceMessage($sourcePath));
            }
        }
    }

    public function dispatchCompilationCompletedEvent(SourceCompilationPassedEvent $event): void
    {
        if (CompilationState::COMPLETE === $this->compilationProgress->get()) {
            $this->eventDispatcher->dispatch(new JobCompiledEvent());
        }
    }
}
