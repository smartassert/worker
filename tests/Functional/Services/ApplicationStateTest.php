<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventState;
use App\Entity\WorkerEventType;
use App\Services\ApplicationState;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;

class ApplicationStateTest extends AbstractBaseFunctionalTest
{
    private ApplicationState $applicationState;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $applicationState = self::getContainer()->get(ApplicationState::class);
        \assert($applicationState instanceof ApplicationState);
        $this->applicationState = $applicationState;

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
    public function testGet(EnvironmentSetup $setup, string $expectedState): void
    {
        $this->environmentFactory->create($setup);

        self::assertSame($expectedState, (string) $this->applicationState);
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'no job, is awaiting' => [
                'setup' => new EnvironmentSetup(),
                'expectedState' => ApplicationState::STATE_AWAITING_JOB,
            ],
            'has job, no sources' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup()),
                'expectedState' => ApplicationState::STATE_AWAITING_SOURCES,
            ],
            'no sources compiled' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ]),
                'expectedState' => ApplicationState::STATE_COMPILING,
            ],
            'first source compiled' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test1.yml'),
                    ]),
                'expectedState' => ApplicationState::STATE_COMPILING,
            ],
            'all sources compiled, no tests running' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test1.yml'),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml'),
                    ]),
                'expectedState' => ApplicationState::STATE_EXECUTING,
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
                            ->withSource('{{ compiler_source_directory }}/Test/test1.yml')
                            ->withState(Test::STATE_COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml'),
                    ]),
                'expectedState' => ApplicationState::STATE_EXECUTING,
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
                            ->withSource('{{ compiler_source_directory }}/Test/test1.yml')
                            ->withState(Test::STATE_COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml'),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedState' => ApplicationState::STATE_EXECUTING,
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
                            ->withSource('{{ compiler_source_directory }}/Test/test1.yml')
                            ->withState(Test::STATE_COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml')
                            ->withState(Test::STATE_COMPLETE),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                        (new WorkerEventSetup())->withState(WorkerEventState::SENDING)
                    ]),
                'expectedState' => ApplicationState::STATE_COMPLETING_EVENT_DELIVERY,
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
                            ->withSource('{{ compiler_source_directory }}/Test/test1.yml')
                            ->withState(Test::STATE_COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml')
                            ->withState(Test::STATE_COMPLETE),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE)
                    ]),
                'expectedState' => ApplicationState::STATE_COMPLETE,
            ],
            'has a job-timeout event delivery' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())
                            ->withType(WorkerEventType::JOB_TIME_OUT)
                            ->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedState' => ApplicationState::STATE_TIMED_OUT,
            ],
        ];
    }

    /**
     * @dataProvider isDataProvider
     *
     * @param array<ApplicationState::STATE_*> $expectedIsStates
     * @param array<ApplicationState::STATE_*> $expectedIsNotStates
     */
    public function testIs(
        EnvironmentSetup $setup,
        array $expectedIsStates,
        array $expectedIsNotStates
    ): void {
        $this->environmentFactory->create($setup);

        self::assertTrue($this->applicationState->is(...$expectedIsStates));
        self::assertFalse($this->applicationState->is(...$expectedIsNotStates));
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
                    ApplicationState::STATE_AWAITING_JOB,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::STATE_AWAITING_SOURCES,
                    ApplicationState::STATE_COMPILING,
                    ApplicationState::STATE_EXECUTING,
                    ApplicationState::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationState::STATE_COMPLETE,
                    ApplicationState::STATE_TIMED_OUT,
                ],
            ],
            'has job, no sources' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup()),
                'expectedIsStates' => [
                    ApplicationState::STATE_AWAITING_SOURCES,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::STATE_AWAITING_JOB,
                    ApplicationState::STATE_COMPILING,
                    ApplicationState::STATE_EXECUTING,
                    ApplicationState::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationState::STATE_COMPLETE,
                    ApplicationState::STATE_TIMED_OUT,
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
                    ApplicationState::STATE_COMPILING,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::STATE_AWAITING_JOB,
                    ApplicationState::STATE_AWAITING_SOURCES,
                    ApplicationState::STATE_EXECUTING,
                    ApplicationState::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationState::STATE_COMPLETE,
                    ApplicationState::STATE_TIMED_OUT,
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
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test1.yml'),
                    ]),
                'expectedIsStates' => [
                    ApplicationState::STATE_COMPILING,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::STATE_AWAITING_JOB,
                    ApplicationState::STATE_AWAITING_SOURCES,
                    ApplicationState::STATE_EXECUTING,
                    ApplicationState::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationState::STATE_COMPLETE,
                    ApplicationState::STATE_TIMED_OUT,
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
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test1.yml'),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml'),
                    ]),
                'expectedIsStates' => [
                    ApplicationState::STATE_EXECUTING,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::STATE_AWAITING_JOB,
                    ApplicationState::STATE_AWAITING_SOURCES,
                    ApplicationState::STATE_COMPILING,
                    ApplicationState::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationState::STATE_COMPLETE,
                    ApplicationState::STATE_TIMED_OUT,
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
                            ->withSource('{{ compiler_source_directory }}/Test/test1.yml')
                            ->withState(Test::STATE_COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml'),
                    ]),
                'expectedIsStates' => [
                    ApplicationState::STATE_EXECUTING,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::STATE_AWAITING_JOB,
                    ApplicationState::STATE_AWAITING_SOURCES,
                    ApplicationState::STATE_COMPILING,
                    ApplicationState::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationState::STATE_COMPLETE,
                    ApplicationState::STATE_TIMED_OUT,
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
                            ->withSource('{{ compiler_source_directory }}/Test/test1.yml')
                            ->withState(Test::STATE_COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml'),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedIsStates' => [
                    ApplicationState::STATE_EXECUTING,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::STATE_AWAITING_JOB,
                    ApplicationState::STATE_AWAITING_SOURCES,
                    ApplicationState::STATE_COMPILING,
                    ApplicationState::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationState::STATE_COMPLETE,
                    ApplicationState::STATE_TIMED_OUT,
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
                            ->withSource('{{ compiler_source_directory }}/Test/test1.yml')
                            ->withState(Test::STATE_COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml')
                            ->withState(Test::STATE_COMPLETE),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                        (new WorkerEventSetup())->withState(WorkerEventState::SENDING)
                    ]),
                'expectedIsStates' => [
                    ApplicationState::STATE_COMPLETING_EVENT_DELIVERY,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::STATE_AWAITING_JOB,
                    ApplicationState::STATE_AWAITING_SOURCES,
                    ApplicationState::STATE_COMPILING,
                    ApplicationState::STATE_EXECUTING,
                    ApplicationState::STATE_COMPLETE,
                    ApplicationState::STATE_TIMED_OUT,
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
                            ->withSource('{{ compiler_source_directory }}/Test/test1.yml')
                            ->withState(Test::STATE_COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml')
                            ->withState(Test::STATE_COMPLETE),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE)
                    ]),
                'expectedIsStates' => [
                    ApplicationState::STATE_COMPLETE,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::STATE_AWAITING_JOB,
                    ApplicationState::STATE_AWAITING_SOURCES,
                    ApplicationState::STATE_COMPILING,
                    ApplicationState::STATE_EXECUTING,
                    ApplicationState::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationState::STATE_TIMED_OUT,
                ],
            ],
            'has a job-timeout event delivery' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())
                            ->withType(WorkerEventType::JOB_TIME_OUT)
                            ->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedIsStates' => [
                    ApplicationState::STATE_TIMED_OUT,
                ],
                'expectedIsNotStates' => [
                    ApplicationState::STATE_AWAITING_JOB,
                    ApplicationState::STATE_AWAITING_SOURCES,
                    ApplicationState::STATE_COMPILING,
                    ApplicationState::STATE_EXECUTING,
                    ApplicationState::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationState::STATE_COMPLETE,
                ],
            ],
        ];
    }
}
