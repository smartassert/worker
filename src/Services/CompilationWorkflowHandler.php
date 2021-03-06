<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\CompilationState;
use App\Enum\WorkerEventOutcome;
use App\Event\JobEvent;
use App\Event\JobStartedEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Exception\JobNotFoundException;
use App\Message\CompileSourceMessage;
use App\Repository\JobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class CompilationWorkflowHandler implements EventSubscriberInterface
{
    public function __construct(
        private CompilationProgress $compilationProgress,
        private EventDispatcherInterface $eventDispatcher,
        private MessageBusInterface $messageBus,
        private SourcePathFinder $sourcePathFinder,
        private JobRepository $jobRepository,
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

    public function dispatchNextCompileSourceMessage(): void
    {
        if (false === $this->compilationProgress->is(...CompilationState::getFinishedStates())) {
            $sourcePath = $this->sourcePathFinder->findNextNonCompiledPath();

            if (is_string($sourcePath)) {
                $this->messageBus->dispatch(new CompileSourceMessage($sourcePath));
            }
        }
    }

    /**
     * @throws JobNotFoundException
     */
    public function dispatchCompilationCompletedEvent(): void
    {
        if ($this->compilationProgress->is(CompilationState::COMPLETE)) {
            $job = $this->jobRepository->get();
            $this->eventDispatcher->dispatch(new JobEvent($job->getLabel(), WorkerEventOutcome::COMPILED));
        }
    }
}
