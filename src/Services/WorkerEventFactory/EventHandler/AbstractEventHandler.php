<?php

declare(strict_types=1);

namespace App\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Event\EventInterface;
use App\Event\ExecutionCompletedEvent;
use App\Event\ExecutionStartedEvent;
use App\Event\JobCompiledEvent;
use App\Event\JobCompletedEvent;
use App\Event\JobFailedEvent;
use App\Event\JobReadyEvent;
use App\Event\JobTimeoutEvent;
use App\Event\SourceCompilationFailedEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Event\SourceCompilationStartedEvent;
use App\Event\StepFailedEvent;
use App\Event\StepPassedEvent;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use App\Repository\WorkerEventRepository;

abstract class AbstractEventHandler
{
    /**
     * @var array<class-string, WorkerEventType>
     */
    private const EVENT_TO_TYPE_MAP = [
        SourceCompilationStartedEvent::class => WorkerEventType::COMPILATION_STARTED,
        SourceCompilationFailedEvent::class => WorkerEventType::COMPILATION_FAILED,
        SourceCompilationPassedEvent::class => WorkerEventType::COMPILATION_PASSED,
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
    protected function create(Job $job, EventInterface $event, array $data): WorkerEvent
    {
        $referenceComponents = $event->getReferenceComponents();
        array_unshift($referenceComponents, $job->getLabel());

        return $this->workerEventRepository->create(
            self::EVENT_TO_TYPE_MAP[$event::class] ?? WorkerEventType::UNKNOWN,
            md5(implode('', $referenceComponents)),
            $data
        );
    }
}
