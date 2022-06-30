<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Event\EventInterface;
use App\Event\ExecutionCompletedEvent;
use App\Event\ExecutionStartedEvent;
use App\Event\JobCompletedEvent;
use App\Event\JobEvent;
use App\Event\JobFailedEvent;
use App\Event\JobStartedEvent;
use App\Event\JobTimeoutEvent;
use App\Event\SourceCompilationFailedEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Event\SourceCompilationStartedEvent;
use App\Event\StepEvent;
use App\Event\TestEvent;
use App\Model\Document\Step;
use App\Model\Document\Test as TestDocument;
use App\Repository\JobRepository;
use App\Services\WorkerEventFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockTestManifest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use webignition\BasilCompilerModels\Model\ErrorOutputInterface;
use webignition\BasilCompilerModels\Model\TestManifestCollection;

class WorkerEventFactoryTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const JOB_LABEL = 'label content';

    private WorkerEventFactory $workerEventFactory;
    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $workerEventFactory = self::getContainer()->get(WorkerEventFactory::class);
        \assert($workerEventFactory instanceof WorkerEventFactory);
        $this->workerEventFactory = $workerEventFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(Test::class);
        }

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        if ($environmentFactory instanceof EnvironmentFactory) {
            $environmentFactory->create(
                (new EnvironmentSetup())->withJobSetup(
                    (new JobSetup())->withLabel(self::JOB_LABEL)
                )
            );
        }

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $this->job = $jobRepository->get();
    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(EventInterface $event, WorkerEvent $expected): void
    {
        $workerEvent = $this->workerEventFactory->create($this->job, $event);

        self::assertSame($expected->getScope()->value, $workerEvent->getScope()->value);
        self::assertSame($expected->getOutcome()->value, $workerEvent->getOutcome()->value);
        self::assertSame($expected->getReference(), $workerEvent->getReference());
        self::assertSame($expected->getPayload(), $workerEvent->getPayload());
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        $sourceCompileFailureEventOutput = \Mockery::mock(ErrorOutputInterface::class);
        $sourceCompileFailureEventOutput
            ->shouldReceive('toArray')
            ->andReturn([
                'compile-failure-key' => 'value',
            ])
        ;

        $passingStepDocumentData = [
            'type' => 'step',
            'payload' => [
                'name' => 'passing step',
            ],
        ];

        $failingStepDocumentData = [
            'type' => 'step',
            'payload' => [
                'name' => 'failing step',
            ],
        ];

        $relativeTestSource = 'Test/test.yml';
        $testSource = '/app/source/' . $relativeTestSource;

        $testConfigurationBrowser = 'chrome';
        $testConfigurationUrl = 'http://example.com';

        $genericTest = new Test($testConfigurationBrowser, $testConfigurationUrl, $testSource, '', ['step 1'], 1);

        $testDocumentData = [
            'type' => 'test',
            'payload' => [
                'path' => $relativeTestSource,
                'config' => [
                    'browser' => $testConfigurationBrowser,
                    'url' => $testConfigurationUrl,
                ],
            ],
        ];

        $testDocument = new TestDocument($relativeTestSource, $testDocumentData);

        $sourceCompilationPassedManifestCollection = new TestManifestCollection([
            (new MockTestManifest())
                ->withGetStepNamesCall([
                    'step one',
                    'step two',
                ])
                ->getMock(),
        ]);

        return [
            JobStartedEvent::class => [
                'event' => new JobStartedEvent([
                    'Test/test1.yaml',
                    'Test/test2.yaml',
                ]),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::STARTED,
                    md5(self::JOB_LABEL),
                    [
                        'tests' => [
                            'Test/test1.yaml',
                            'Test/test2.yaml',
                        ],
                        'related_references' => [
                            [
                                'label' => 'Test/test1.yaml',
                                'reference' => md5(self::JOB_LABEL . 'Test/test1.yaml'),
                            ],
                            [
                                'label' => 'Test/test2.yaml',
                                'reference' => md5(self::JOB_LABEL . 'Test/test2.yaml'),
                            ],
                        ],
                    ]
                ),
            ],
            SourceCompilationStartedEvent::class => [
                'event' => new SourceCompilationStartedEvent($relativeTestSource),
                'expected' => new WorkerEvent(
                    WorkerEventScope::COMPILATION,
                    WorkerEventOutcome::STARTED,
                    md5(self::JOB_LABEL . $relativeTestSource),
                    [
                        'source' => $relativeTestSource,
                    ]
                ),
            ],
            SourceCompilationPassedEvent::class => [
                'event' => new SourceCompilationPassedEvent(
                    $relativeTestSource,
                    $sourceCompilationPassedManifestCollection
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::COMPILATION,
                    WorkerEventOutcome::PASSED,
                    md5(self::JOB_LABEL . $relativeTestSource),
                    [
                        'source' => $relativeTestSource,
                        'related_references' => [
                            [
                                'label' => 'step one',
                                'reference' => md5(self::JOB_LABEL . $relativeTestSource . 'step one'),
                            ],
                            [
                                'label' => 'step two',
                                'reference' => md5(self::JOB_LABEL . $relativeTestSource . 'step two'),
                            ],
                        ],
                    ]
                ),
            ],
            SourceCompilationFailedEvent::class => [
                'event' => new SourceCompilationFailedEvent($relativeTestSource, $sourceCompileFailureEventOutput),
                'expected' => new WorkerEvent(
                    WorkerEventScope::COMPILATION,
                    WorkerEventOutcome::FAILED,
                    md5(self::JOB_LABEL . $relativeTestSource),
                    [
                        'source' => $relativeTestSource,
                        'output' => [
                            'compile-failure-key' => 'value',
                        ],
                    ]
                ),
            ],
            'job/compiled' => [
                'event' => new JobEvent(WorkerEventOutcome::COMPILED),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::COMPILED,
                    md5(self::JOB_LABEL),
                    []
                ),
            ],
            ExecutionStartedEvent::class => [
                'event' => new ExecutionStartedEvent(),
                'expected' => new WorkerEvent(
                    WorkerEventScope::EXECUTION,
                    WorkerEventOutcome::STARTED,
                    md5(self::JOB_LABEL),
                    []
                ),
            ],
            'test/started' => [
                'event' => new TestEvent(WorkerEventOutcome::STARTED, $genericTest, $testDocument),
                'expected' => new WorkerEvent(
                    WorkerEventScope::TEST,
                    WorkerEventOutcome::STARTED,
                    md5(self::JOB_LABEL . $relativeTestSource),
                    [
                        'source' => $relativeTestSource,
                        'document' => $testDocumentData,
                        'step_names' => [
                            'step 1',
                        ],
                        'related_references' => [
                            [
                                'label' => 'step 1',
                                'reference' => md5(self::JOB_LABEL . $relativeTestSource . 'step 1'),
                            ],
                        ],
                    ]
                ),
            ],
            'step/passed' => [
                'event' => new StepEvent(
                    WorkerEventOutcome::PASSED,
                    new Step('passing step', $passingStepDocumentData),
                    $relativeTestSource,
                    $genericTest->setState(TestState::RUNNING)
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::STEP,
                    WorkerEventOutcome::PASSED,
                    md5(self::JOB_LABEL . $relativeTestSource . 'passing step'),
                    [
                        'source' => $relativeTestSource,
                        'document' => $passingStepDocumentData,
                        'name' => 'passing step',
                    ]
                ),
            ],
            'step/failed' => [
                'event' => new StepEvent(
                    WorkerEventOutcome::FAILED,
                    new Step('failing step', $failingStepDocumentData),
                    $relativeTestSource,
                    $genericTest->setState(TestState::FAILED)
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::STEP,
                    WorkerEventOutcome::FAILED,
                    md5(self::JOB_LABEL . $relativeTestSource . 'failing step'),
                    [
                        'source' => $relativeTestSource,
                        'document' => $failingStepDocumentData,
                        'name' => 'failing step',
                    ]
                ),
            ],
            'test/passed' => [
                'event' => new TestEvent(
                    WorkerEventOutcome::PASSED,
                    $genericTest->setState(TestState::COMPLETE),
                    $testDocument
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::TEST,
                    WorkerEventOutcome::PASSED,
                    md5(self::JOB_LABEL . $relativeTestSource),
                    [
                        'source' => $relativeTestSource,
                        'document' => $testDocumentData,
                        'step_names' => [
                            'step 1',
                        ],
                        'related_references' => [
                            [
                                'label' => 'step 1',
                                'reference' => md5(self::JOB_LABEL . $relativeTestSource . 'step 1'),
                            ],
                        ],
                    ]
                ),
            ],
            'test/failed' => [
                'event' => new TestEvent(
                    WorkerEventOutcome::FAILED,
                    $genericTest->setState(TestState::FAILED),
                    $testDocument
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::TEST,
                    WorkerEventOutcome::FAILED,
                    md5(self::JOB_LABEL . $relativeTestSource),
                    [
                        'source' => $relativeTestSource,
                        'document' => $testDocumentData,
                        'step_names' => [
                            'step 1',
                        ],
                        'related_references' => [
                            [
                                'label' => 'step 1',
                                'reference' => md5(self::JOB_LABEL . $relativeTestSource . 'step 1'),
                            ],
                        ],
                    ]
                ),
            ],
            JobTimeoutEvent::class => [
                'event' => new JobTimeoutEvent(10),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::TIME_OUT,
                    md5(self::JOB_LABEL),
                    [
                        'maximum_duration_in_seconds' => 10,
                    ]
                ),
            ],
            JobCompletedEvent::class => [
                'event' => new JobCompletedEvent(),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::COMPLETED,
                    md5(self::JOB_LABEL),
                    []
                ),
            ],
            JobFailedEvent::class => [
                'event' => new JobFailedEvent(),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::FAILED,
                    md5(self::JOB_LABEL),
                    []
                ),
            ],
            ExecutionCompletedEvent::class => [
                'event' => new ExecutionCompletedEvent(),
                'expected' => new WorkerEvent(
                    WorkerEventScope::EXECUTION,
                    WorkerEventOutcome::COMPLETED,
                    md5(self::JOB_LABEL),
                    []
                ),
            ],
        ];
    }
}
