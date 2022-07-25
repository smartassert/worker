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
use App\Event\ExecutionEvent;
use App\Event\JobEvent;
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

        self::assertEquals($expected, $workerEvent);
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

        $testSource = 'Test/test.yml';
        $testConfigurationBrowser = 'chrome';
        $testConfigurationUrl = 'http://example.com';

        $genericTest = new Test(
            $testConfigurationBrowser,
            $testConfigurationUrl,
            $testSource,
            '/app/target/GeneratedTest1234.php',
            ['step 1'],
            1
        );

        $testDocumentData = [
            'type' => 'test',
            'payload' => [
                'path' => $testSource,
                'config' => [
                    'browser' => $testConfigurationBrowser,
                    'url' => $testConfigurationUrl,
                ],
            ],
        ];

        $testDocument = new TestDocument($testSource, $testDocumentData);

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
                'event' => new JobStartedEvent(
                    self::JOB_LABEL,
                    [
                        'Test/test1.yaml',
                        'Test/test2.yaml',
                    ]
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::STARTED,
                    self::JOB_LABEL,
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
                'event' => new SourceCompilationStartedEvent($testSource),
                'expected' => new WorkerEvent(
                    WorkerEventScope::COMPILATION,
                    WorkerEventOutcome::STARTED,
                    $testSource,
                    md5(self::JOB_LABEL . $testSource),
                    [
                        'source' => $testSource,
                    ]
                ),
            ],
            SourceCompilationPassedEvent::class => [
                'event' => new SourceCompilationPassedEvent(
                    $testSource,
                    $sourceCompilationPassedManifestCollection
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::COMPILATION,
                    WorkerEventOutcome::PASSED,
                    $testSource,
                    md5(self::JOB_LABEL . $testSource),
                    [
                        'source' => $testSource,
                        'related_references' => [
                            [
                                'label' => 'step one',
                                'reference' => md5(self::JOB_LABEL . $testSource . 'step one'),
                            ],
                            [
                                'label' => 'step two',
                                'reference' => md5(self::JOB_LABEL . $testSource . 'step two'),
                            ],
                        ],
                    ]
                ),
            ],
            SourceCompilationFailedEvent::class => [
                'event' => new SourceCompilationFailedEvent($testSource, $sourceCompileFailureEventOutput),
                'expected' => new WorkerEvent(
                    WorkerEventScope::COMPILATION,
                    WorkerEventOutcome::FAILED,
                    $testSource,
                    md5(self::JOB_LABEL . $testSource),
                    [
                        'source' => $testSource,
                        'output' => [
                            'compile-failure-key' => 'value',
                        ],
                    ]
                ),
            ],
            'job/compiled' => [
                'event' => new JobEvent(self::JOB_LABEL, WorkerEventOutcome::COMPILED),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::COMPILED,
                    self::JOB_LABEL,
                    md5(self::JOB_LABEL),
                    []
                ),
            ],
            'execution/started' => [
                'event' => new ExecutionEvent(self::JOB_LABEL, WorkerEventOutcome::STARTED),
                'expected' => new WorkerEvent(
                    WorkerEventScope::EXECUTION,
                    WorkerEventOutcome::STARTED,
                    self::JOB_LABEL,
                    md5(self::JOB_LABEL),
                    []
                ),
            ],
            'test/started' => [
                'event' => new TestEvent($genericTest, $testDocument, $testSource, WorkerEventOutcome::STARTED),
                'expected' => new WorkerEvent(
                    WorkerEventScope::TEST,
                    WorkerEventOutcome::STARTED,
                    $testSource,
                    md5(self::JOB_LABEL . $testSource),
                    [
                        'source' => $testSource,
                        'document' => $testDocumentData,
                        'step_names' => [
                            'step 1',
                        ],
                        'related_references' => [
                            [
                                'label' => 'step 1',
                                'reference' => md5(self::JOB_LABEL . $testSource . 'step 1'),
                            ],
                        ],
                    ]
                ),
            ],
            'step/passed' => [
                'event' => new StepEvent(
                    $genericTest->setState(TestState::RUNNING),
                    new Step('passing step', $passingStepDocumentData),
                    $testSource,
                    'passing step',
                    WorkerEventOutcome::PASSED
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::STEP,
                    WorkerEventOutcome::PASSED,
                    'passing step',
                    md5(self::JOB_LABEL . $testSource . 'passing step'),
                    [
                        'source' => $testSource,
                        'document' => $passingStepDocumentData,
                        'name' => 'passing step',
                    ]
                ),
            ],
            'step/failed' => [
                'event' => new StepEvent(
                    $genericTest->setState(TestState::FAILED),
                    new Step('failing step', $failingStepDocumentData),
                    $testSource,
                    'failing step',
                    WorkerEventOutcome::FAILED,
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::STEP,
                    WorkerEventOutcome::FAILED,
                    'failing step',
                    md5(self::JOB_LABEL . $testSource . 'failing step'),
                    [
                        'source' => $testSource,
                        'document' => $failingStepDocumentData,
                        'name' => 'failing step',
                    ]
                ),
            ],
            'test/passed' => [
                'event' => new TestEvent(
                    $genericTest->setState(TestState::COMPLETE),
                    $testDocument,
                    $testSource,
                    WorkerEventOutcome::PASSED
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::TEST,
                    WorkerEventOutcome::PASSED,
                    $testSource,
                    md5(self::JOB_LABEL . $testSource),
                    [
                        'source' => $testSource,
                        'document' => $testDocumentData,
                        'step_names' => [
                            'step 1',
                        ],
                        'related_references' => [
                            [
                                'label' => 'step 1',
                                'reference' => md5(self::JOB_LABEL . $testSource . 'step 1'),
                            ],
                        ],
                    ]
                ),
            ],
            'test/failed' => [
                'event' => new TestEvent(
                    $genericTest->setState(TestState::FAILED),
                    $testDocument,
                    $testSource,
                    WorkerEventOutcome::FAILED
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::TEST,
                    WorkerEventOutcome::FAILED,
                    $testSource,
                    md5(self::JOB_LABEL . $testSource),
                    [
                        'source' => $testSource,
                        'document' => $testDocumentData,
                        'step_names' => [
                            'step 1',
                        ],
                        'related_references' => [
                            [
                                'label' => 'step 1',
                                'reference' => md5(self::JOB_LABEL . $testSource . 'step 1'),
                            ],
                        ],
                    ]
                ),
            ],
            JobTimeoutEvent::class => [
                'event' => new JobTimeoutEvent(self::JOB_LABEL, 10),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::TIME_OUT,
                    self::JOB_LABEL,
                    md5(self::JOB_LABEL),
                    [
                        'maximum_duration_in_seconds' => 10,
                    ]
                ),
            ],
            'job/completed' => [
                'event' => new JobEvent(self::JOB_LABEL, WorkerEventOutcome::COMPLETED),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::COMPLETED,
                    self::JOB_LABEL,
                    md5(self::JOB_LABEL),
                    []
                ),
            ],
            'job/failed' => [
                'event' => new JobEvent(self::JOB_LABEL, WorkerEventOutcome::FAILED),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::FAILED,
                    self::JOB_LABEL,
                    md5(self::JOB_LABEL),
                    []
                ),
            ],
            'execution/completed' => [
                'event' => new ExecutionEvent(self::JOB_LABEL, WorkerEventOutcome::COMPLETED),
                'expected' => new WorkerEvent(
                    WorkerEventScope::EXECUTION,
                    WorkerEventOutcome::COMPLETED,
                    self::JOB_LABEL,
                    md5(self::JOB_LABEL),
                    []
                ),
            ],
        ];
    }
}
