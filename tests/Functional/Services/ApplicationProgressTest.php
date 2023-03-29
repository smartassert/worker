<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Enum\ApplicationState;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventState;
use App\Services\ApplicationProgress;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;

class ApplicationProgressTest extends AbstractBaseFunctionalTestCase
{
    private ApplicationProgress $applicationProgress;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $applicationProgress = self::getContainer()->get(ApplicationProgress::class);
        \assert($applicationProgress instanceof ApplicationProgress);
        $this->applicationProgress = $applicationProgress;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(Source::class);
            $entityRemover->removeForEntity(Test::class);
        }
    }

    /**
     * @dataProvider getDataProvider
     */
    public function testGet(EnvironmentSetup $setup, ApplicationState $expectedState): void
    {
        $this->environmentFactory->create($setup);

        self::assertSame($expectedState, $this->applicationProgress->get());
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'no job, is awaiting' => [
                'setup' => new EnvironmentSetup(),
                'expectedState' => ApplicationState::AWAITING_JOB,
            ],
            'no sources compiled' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ]),
                'expectedState' => ApplicationState::COMPILING,
            ],
            'first source compiled' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())->withSource('Test/test1.yml'),
                    ]),
                'expectedState' => ApplicationState::COMPILING,
            ],
            'all sources compiled, no tests running' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())->withSource('Test/test1.yml'),
                        (new TestSetup())->withSource('Test/test2.yml'),
                    ]),
                'expectedState' => ApplicationState::EXECUTING,
            ],
            'first test complete, no event deliveries' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('Test/test2.yml'),
                    ]),
                'expectedState' => ApplicationState::EXECUTING,
            ],
            'first test complete, event delivery for first test complete' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('Test/test2.yml'),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedState' => ApplicationState::EXECUTING,
            ],
            'all tests complete, first event delivery complete, second event delivery running' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                        (new WorkerEventSetup())->withState(WorkerEventState::SENDING)
                    ]),
                'expectedState' => ApplicationState::COMPLETING_EVENT_DELIVERY,
            ],
            'all tests complete, all event deliveries complete' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE)
                    ]),
                'expectedState' => ApplicationState::COMPLETE,
            ],
            'has a job-timeout event delivery' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())
                            ->withScope(WorkerEventScope::JOB)
                            ->withOutcome(WorkerEventOutcome::TIME_OUT)
                            ->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedState' => ApplicationState::TIMED_OUT,
            ],
            'compilation failed' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())
                            ->withScope(WorkerEventScope::SOURCE_COMPILATION)
                            ->withOutcome(WorkerEventOutcome::FAILED),
                    ]),
                'expectedState' => ApplicationState::COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider isDataProvider
     *
     * @param ApplicationState[] $expectedIsStates
     * @param ApplicationState[] $expectedIsNotStates
     */
    public function testIs(
        EnvironmentSetup $setup,
        array $expectedIsStates,
        array $expectedIsNotStates
    ): void {
        $this->environmentFactory->create($setup);

        self::assertTrue($this->applicationProgress->is($expectedIsStates));
        self::assertFalse($this->applicationProgress->is($expectedIsNotStates));
    }

    /**
     * @return array<mixed>
     */
    public function isDataProvider(): array
    {
        return [
            'no job, is awaiting' => [
                'setup' => new EnvironmentSetup(),
                'expectedIsStates' => [
                    ApplicationState::AWAITING_JOB,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::COMPILING,
                    ApplicationState::EXECUTING,
                    ApplicationState::COMPLETING_EVENT_DELIVERY,
                    ApplicationState::COMPLETE,
                    ApplicationState::TIMED_OUT,
                ],
            ],
            'no sources compiled' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ]),
                'expectedIsStates' => [
                    ApplicationState::COMPILING,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::AWAITING_JOB,
                    ApplicationState::EXECUTING,
                    ApplicationState::COMPLETING_EVENT_DELIVERY,
                    ApplicationState::COMPLETE,
                    ApplicationState::TIMED_OUT,
                ],
            ],
            'first source compiled' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())->withSource('Test/test1.yml'),
                    ]),
                'expectedIsStates' => [
                    ApplicationState::COMPILING,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::AWAITING_JOB,
                    ApplicationState::EXECUTING,
                    ApplicationState::COMPLETING_EVENT_DELIVERY,
                    ApplicationState::COMPLETE,
                    ApplicationState::TIMED_OUT,
                ],
            ],
            'all sources compiled, no tests running' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())->withSource('Test/test1.yml'),
                        (new TestSetup())->withSource('Test/test2.yml'),
                    ]),
                'expectedIsStates' => [
                    ApplicationState::EXECUTING,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::AWAITING_JOB,
                    ApplicationState::COMPILING,
                    ApplicationState::COMPLETING_EVENT_DELIVERY,
                    ApplicationState::COMPLETE,
                    ApplicationState::TIMED_OUT,
                ],
            ],
            'first test complete, no event deliveries' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('Test/test2.yml'),
                    ]),
                'expectedIsStates' => [
                    ApplicationState::EXECUTING,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::AWAITING_JOB,
                    ApplicationState::COMPILING,
                    ApplicationState::COMPLETING_EVENT_DELIVERY,
                    ApplicationState::COMPLETE,
                    ApplicationState::TIMED_OUT,
                ],
            ],
            'first test complete, event delivery for first test complete' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('Test/test2.yml'),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedIsStates' => [
                    ApplicationState::EXECUTING,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::AWAITING_JOB,
                    ApplicationState::COMPILING,
                    ApplicationState::COMPLETING_EVENT_DELIVERY,
                    ApplicationState::COMPLETE,
                    ApplicationState::TIMED_OUT,
                ],
            ],
            'all tests complete, first event delivery complete, second event delivery running' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                        (new WorkerEventSetup())->withState(WorkerEventState::SENDING)
                    ]),
                'expectedIsStates' => [
                    ApplicationState::COMPLETING_EVENT_DELIVERY,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::AWAITING_JOB,
                    ApplicationState::COMPILING,
                    ApplicationState::EXECUTING,
                    ApplicationState::COMPLETE,
                    ApplicationState::TIMED_OUT,
                ],
            ],
            'all tests complete, all event deliveries complete' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE)
                    ]),
                'expectedIsStates' => [
                    ApplicationState::COMPLETE,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::AWAITING_JOB,
                    ApplicationState::COMPILING,
                    ApplicationState::EXECUTING,
                    ApplicationState::COMPLETING_EVENT_DELIVERY,
                    ApplicationState::TIMED_OUT,
                ],
            ],
            'has a job-timeout event delivery' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())
                            ->withScope(WorkerEventScope::JOB)
                            ->withOutcome(WorkerEventOutcome::TIME_OUT)
                            ->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedIsStates' => [
                    ApplicationState::TIMED_OUT,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::AWAITING_JOB,
                    ApplicationState::COMPILING,
                    ApplicationState::EXECUTING,
                    ApplicationState::COMPLETING_EVENT_DELIVERY,
                    ApplicationState::COMPLETE,
                ],
            ],
        ];
    }
}
