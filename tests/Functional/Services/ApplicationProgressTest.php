<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\TestState;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventState;
use App\Entity\WorkerEventType;
use App\Services\ApplicationProgress;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;

class ApplicationProgressTest extends AbstractBaseFunctionalTest
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
    public function testGet(EnvironmentSetup $setup, string $expectedState): void
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
                'expectedState' => ApplicationProgress::STATE_AWAITING_JOB,
            ],
            'has job, no sources' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup()),
                'expectedState' => ApplicationProgress::STATE_AWAITING_SOURCES,
            ],
            'no sources compiled' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ]),
                'expectedState' => ApplicationProgress::STATE_COMPILING,
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
                'expectedState' => ApplicationProgress::STATE_COMPILING,
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
                'expectedState' => ApplicationProgress::STATE_EXECUTING,
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
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml'),
                    ]),
                'expectedState' => ApplicationProgress::STATE_EXECUTING,
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
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml'),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedState' => ApplicationProgress::STATE_EXECUTING,
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
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                        (new WorkerEventSetup())->withState(WorkerEventState::SENDING)
                    ]),
                'expectedState' => ApplicationProgress::STATE_COMPLETING_EVENT_DELIVERY,
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
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE)
                    ]),
                'expectedState' => ApplicationProgress::STATE_COMPLETE,
            ],
            'has a job-timeout event delivery' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())
                            ->withType(WorkerEventType::JOB_TIME_OUT)
                            ->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedState' => ApplicationProgress::STATE_TIMED_OUT,
            ],
        ];
    }

    /**
     * @dataProvider isDataProvider
     *
     * @param array<ApplicationProgress::STATE_*> $expectedIsStates
     * @param array<ApplicationProgress::STATE_*> $expectedIsNotStates
     */
    public function testIs(
        EnvironmentSetup $setup,
        array $expectedIsStates,
        array $expectedIsNotStates
    ): void {
        $this->environmentFactory->create($setup);

        self::assertTrue($this->applicationProgress->is(...$expectedIsStates));
        self::assertFalse($this->applicationProgress->is(...$expectedIsNotStates));
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
                    ApplicationProgress::STATE_AWAITING_JOB,
                ],
                'expectedIsNotStates' => [
                    ApplicationProgress::STATE_AWAITING_SOURCES,
                    ApplicationProgress::STATE_COMPILING,
                    ApplicationProgress::STATE_EXECUTING,
                    ApplicationProgress::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationProgress::STATE_COMPLETE,
                    ApplicationProgress::STATE_TIMED_OUT,
                ],
            ],
            'has job, no sources' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup()),
                'expectedIsStates' => [
                    ApplicationProgress::STATE_AWAITING_SOURCES,
                ],
                'expectedIsNotStates' => [
                    ApplicationProgress::STATE_AWAITING_JOB,
                    ApplicationProgress::STATE_COMPILING,
                    ApplicationProgress::STATE_EXECUTING,
                    ApplicationProgress::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationProgress::STATE_COMPLETE,
                    ApplicationProgress::STATE_TIMED_OUT,
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
                    ApplicationProgress::STATE_COMPILING,
                ],
                'expectedIsNotStates' => [
                    ApplicationProgress::STATE_AWAITING_JOB,
                    ApplicationProgress::STATE_AWAITING_SOURCES,
                    ApplicationProgress::STATE_EXECUTING,
                    ApplicationProgress::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationProgress::STATE_COMPLETE,
                    ApplicationProgress::STATE_TIMED_OUT,
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
                    ApplicationProgress::STATE_COMPILING,
                ],
                'expectedIsNotStates' => [
                    ApplicationProgress::STATE_AWAITING_JOB,
                    ApplicationProgress::STATE_AWAITING_SOURCES,
                    ApplicationProgress::STATE_EXECUTING,
                    ApplicationProgress::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationProgress::STATE_COMPLETE,
                    ApplicationProgress::STATE_TIMED_OUT,
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
                    ApplicationProgress::STATE_EXECUTING,
                ],
                'expectedIsNotStates' => [
                    ApplicationProgress::STATE_AWAITING_JOB,
                    ApplicationProgress::STATE_AWAITING_SOURCES,
                    ApplicationProgress::STATE_COMPILING,
                    ApplicationProgress::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationProgress::STATE_COMPLETE,
                    ApplicationProgress::STATE_TIMED_OUT,
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
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml'),
                    ]),
                'expectedIsStates' => [
                    ApplicationProgress::STATE_EXECUTING,
                ],
                'expectedIsNotStates' => [
                    ApplicationProgress::STATE_AWAITING_JOB,
                    ApplicationProgress::STATE_AWAITING_SOURCES,
                    ApplicationProgress::STATE_COMPILING,
                    ApplicationProgress::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationProgress::STATE_COMPLETE,
                    ApplicationProgress::STATE_TIMED_OUT,
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
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml'),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedIsStates' => [
                    ApplicationProgress::STATE_EXECUTING,
                ],
                'expectedIsNotStates' => [
                    ApplicationProgress::STATE_AWAITING_JOB,
                    ApplicationProgress::STATE_AWAITING_SOURCES,
                    ApplicationProgress::STATE_COMPILING,
                    ApplicationProgress::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationProgress::STATE_COMPLETE,
                    ApplicationProgress::STATE_TIMED_OUT,
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
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                        (new WorkerEventSetup())->withState(WorkerEventState::SENDING)
                    ]),
                'expectedIsStates' => [
                    ApplicationProgress::STATE_COMPLETING_EVENT_DELIVERY,
                ],
                'expectedIsNotStates' => [
                    ApplicationProgress::STATE_AWAITING_JOB,
                    ApplicationProgress::STATE_AWAITING_SOURCES,
                    ApplicationProgress::STATE_COMPILING,
                    ApplicationProgress::STATE_EXECUTING,
                    ApplicationProgress::STATE_COMPLETE,
                    ApplicationProgress::STATE_TIMED_OUT,
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
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('{{ compiler_source_directory }}/Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                    ])
                    ->withWorkerEventSetups([
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE),
                        (new WorkerEventSetup())->withState(WorkerEventState::COMPLETE)
                    ]),
                'expectedIsStates' => [
                    ApplicationProgress::STATE_COMPLETE,
                ],
                'expectedIsNotStates' => [
                    ApplicationProgress::STATE_AWAITING_JOB,
                    ApplicationProgress::STATE_AWAITING_SOURCES,
                    ApplicationProgress::STATE_COMPILING,
                    ApplicationProgress::STATE_EXECUTING,
                    ApplicationProgress::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationProgress::STATE_TIMED_OUT,
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
                    ApplicationProgress::STATE_TIMED_OUT,
                ],
                'expectedIsNotStates' => [
                    ApplicationProgress::STATE_AWAITING_JOB,
                    ApplicationProgress::STATE_AWAITING_SOURCES,
                    ApplicationProgress::STATE_COMPILING,
                    ApplicationProgress::STATE_EXECUTING,
                    ApplicationProgress::STATE_COMPLETING_EVENT_DELIVERY,
                    ApplicationProgress::STATE_COMPLETE,
                ],
            ],
        ];
    }
}
