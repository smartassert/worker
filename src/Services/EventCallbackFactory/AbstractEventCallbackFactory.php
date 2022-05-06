<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Job;
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
use App\Repository\CallbackRepository;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractEventCallbackFactory implements EventCallbackFactoryInterface
{
    /**
     * @var array<class-string, CallbackEntity::TYPE_*>
     */
    private const EVENT_TO_CALLBACK_TYPE_MAP = [
        CompilationStartedEvent::class => CallbackEntity::TYPE_COMPILATION_STARTED,
        CompilationFailedEvent::class => CallbackEntity::TYPE_COMPILATION_FAILED,
        CompilationPassedEvent::class => CallbackEntity::TYPE_COMPILATION_PASSED,
        JobTimeoutEvent::class => CallbackEntity::TYPE_JOB_TIME_OUT,
        JobReadyEvent::class => CallbackEntity::TYPE_JOB_STARTED,
        JobCompiledEvent::class => CallbackEntity::TYPE_JOB_COMPILED,
        ExecutionStartedEvent::class => CallbackEntity::TYPE_EXECUTION_STARTED,
        ExecutionCompletedEvent::class => CallbackEntity::TYPE_EXECUTION_COMPLETED,
        JobCompletedEvent::class => CallbackEntity::TYPE_JOB_COMPLETED,
        JobFailedEvent::class => CallbackEntity::TYPE_JOB_FAILED,
        StepPassedEvent::class => CallbackEntity::TYPE_STEP_PASSED,
        StepFailedEvent::class => CallbackEntity::TYPE_STEP_FAILED,
        TestStartedEvent::class => CallbackEntity::TYPE_TEST_STARTED,
        TestPassedEvent::class => CallbackEntity::TYPE_TEST_PASSED,
        TestFailedEvent::class => CallbackEntity::TYPE_TEST_FAILED,
    ];

    public function __construct(
        private readonly CallbackRepository $callbackRepository,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    protected function create(Job $job, Event $event, array $data): CallbackEntity
    {
        return $this->callbackRepository->create(
            self::EVENT_TO_CALLBACK_TYPE_MAP[$event::class] ?? CallbackEntity::TYPE_UNKNOWN,
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

        if ($event instanceof TestEventInterface || $event instanceof StepEventInterface) {
            $referenceComponents[] = $event->getTest()->getSource();
        }

        if ($event instanceof StepEventInterface) {
            $referenceComponents[] = $event->getStep()->getName();
        }

        return md5(implode('', $referenceComponents));
    }
}
