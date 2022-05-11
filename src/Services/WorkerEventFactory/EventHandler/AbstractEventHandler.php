<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Event\ExecutionCompletedEvent;
use App\Event\ExecutionStartedEvent;
use App\Event\JobCompiledEvent;
use App\Event\JobCompletedEvent;
use App\Event\JobFailedEvent;
use App\Event\JobReadyEvent;
use App\Event\JobTimeoutEvent;
use App\Event\SourceCompilation\EventInterface;
use App\Event\SourceCompilation\FailedEvent as CompilationFailedEvent;
use App\Event\SourceCompilation\PassedEvent as CompilationPassedEvent;
use App\Event\SourceCompilation\StartedEvent as CompilationStartedEvent;
use App\Event\StepEventInterface;
use App\Event\StepFailedEvent;
use App\Event\StepPassedEvent;
use App\Event\TestEventInterface;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use App\Repository\WorkerEventRepository;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractEventHandler implements EventHandlerInterface
{
    /**
     * @var array<class-string, WorkerEventType>
     */
    private const EVENT_TO_TYPE_MAP = [
        CompilationStartedEvent::class => WorkerEventType::COMPILATION_STARTED,
        CompilationFailedEvent::class => WorkerEventType::COMPILATION_FAILED,
        CompilationPassedEvent::class => WorkerEventType::COMPILATION_PASSED,
        JobTimeoutEvent::class => WorkerEventType::JOB_TIME_OUT,
        JobReadyEvent::class => WorkerEventType::JOB_STARTED,
        JobCompiledEvent::class => WorkerEventType::JOB_COMPILED,
        ExecutionStartedEvent::class => WorkerEventType::EXECUTION_STARTED,
        ExecutionCompletedEvent::class => WorkerEventType::EXECUTION_COMPLETED,
        JobCompletedEvent::class => WorkerEventType::JOB_COMPLETED,
        JobFailedEvent::class => WorkerEventType::JOB_FAILED,
        StepPassedEvent::class => WorkerEventType::STEP_PASSED,
        StepFailedEvent::class => WorkerEventType::STEP_FAILED,
        TestStartedEvent::class => WorkerEventType::TEST_STARTED,
        TestPassedEvent::class => WorkerEventType::TEST_PASSED,
        TestFailedEvent::class => WorkerEventType::TEST_FAILED,
    ];

    public function __construct(
        private readonly WorkerEventRepository $workerEventRepository,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    protected function create(Job $job, Event $event, array $data): WorkerEvent
    {
        return $this->workerEventRepository->create(
            self::EVENT_TO_TYPE_MAP[$event::class] ?? WorkerEventType::UNKNOWN,
            $this->createReference($job, $event),
            $data
        );
    }

    /**
     * @return non-empty-string
     */
    private function createReference(Job $job, Event $event): string
    {
        $referenceComponents = [$job->getLabel()];

        if ($event instanceof EventInterface) {
            $referenceComponents[] = $event->getSource();
        }

        if ($event instanceof TestEventInterface) {
            $referenceComponents[] = $event->getDocument()->getPath();
        }

        if ($event instanceof StepEventInterface) {
            $referenceComponents[] = $event->getPath();
            $referenceComponents[] = $event->getDocument()->getName();
        }

        return md5(implode('', $referenceComponents));
    }
}
