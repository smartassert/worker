<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventReference;
use App\Enum\ExecutionExceptionScope;
use App\Enum\JobEndState;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Event\EmittableEventInterface;
use App\Event\ExecutionEmittableEvent;
use App\Event\JobEndedEmittableEvent;
use App\Event\JobStartedEmittableEvent;
use App\Event\JobTimeoutEmittableEvent;
use App\Event\SourceCompilationFailedEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Event\SourceCompilationStartedEvent;
use App\Event\StepEmittableEvent;
use App\Event\TestEmittableEvent;
use App\Model\Document\Exception;
use App\Model\Document\Step;
use App\Model\Document\StepException;
use App\Model\Document\Test as TestDocument;
use App\Model\WorkerEventReferenceCollection;
use App\Repository\JobRepository;
use App\Services\WorkerEventFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockTestManifest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Doctrine\Common\Collections\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use webignition\BasilCompilerModels\Model\TestManifestCollection;
use webignition\ObjectReflector\ObjectReflector;

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
            $entityRemover->removeForEntity(WorkerEvent::class);
            $entityRemover->removeForEntity(WorkerEventReference::class);
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
    public function testCreate(EmittableEventInterface $event, WorkerEvent $expected): void
    {
        $actual = $this->workerEventFactory->create($this->job, $event);

        self::assertSame($expected->getState(), $actual->getState());
        self::assertSame(
            ObjectReflector::getProperty($expected, 'scope'),
            ObjectReflector::getProperty($actual, 'scope')
        );
        self::assertSame(
            ObjectReflector::getProperty($expected, 'outcome'),
            ObjectReflector::getProperty($actual, 'outcome')
        );
        self::assertSame(
            ObjectReflector::getProperty($expected, 'label'),
            ObjectReflector::getProperty($actual, 'label')
        );
        self::assertSame(
            ObjectReflector::getProperty($expected, 'payload'),
            ObjectReflector::getProperty($actual, 'payload')
        );

        $expectedReference = ObjectReflector::getProperty($expected, 'reference');
        self::assertInstanceOf(WorkerEventReference::class, $expectedReference);

        $actualReference = ObjectReflector::getProperty($actual, 'reference');
        self::assertInstanceOf(WorkerEventReference::class, $actualReference);

        self::assertSame($expectedReference->toArray(), $actualReference->toArray());

        $expectedRelatedReferences = ObjectReflector::getProperty($expected, 'relatedReferences');
        self::assertInstanceOf(Collection::class, $expectedRelatedReferences);

        $actualRelatedReferences = ObjectReflector::getProperty($actual, 'relatedReferences');
        self::assertInstanceOf(Collection::class, $actualRelatedReferences);

        self::assertCount($expectedRelatedReferences->count(), $actualRelatedReferences);

        $actualRelatedReferencesAsArray = [];
        foreach ($actualRelatedReferences as $actualRelatedReference) {
            $actualRelatedReferencesAsArray[] = $actualRelatedReference;
        }

        $expectedRelatedReferenceIndex = 0;

        foreach ($expectedRelatedReferences as $expectedRelatedReference) {
            \assert($expectedRelatedReference instanceof WorkerEventReference);

            $actualRelatedReference = $actualRelatedReferencesAsArray[$expectedRelatedReferenceIndex];
            \assert($actualRelatedReference instanceof WorkerEventReference);

            self::assertSame(
                ObjectReflector::getProperty($expectedRelatedReference, 'label'),
                ObjectReflector::getProperty($actualRelatedReference, 'label')
            );

            self::assertSame(
                ObjectReflector::getProperty($expectedRelatedReference, 'reference'),
                ObjectReflector::getProperty($actualRelatedReference, 'reference')
            );

            ++$expectedRelatedReferenceIndex;
        }
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
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

        $exceptionStepDocumentData = [
            'type' => 'exception',
            'payload' => [
                'step' => 'step name',
                'class' => self::class,
                'message' => 'step-scope exception message',
                'code' => 456,
            ],
        ];

        $exceptionTestDocumentData = [
            'type' => 'exception',
            'payload' => [
                'step' => null,
                'class' => self::class,
                'message' => 'test-scope exception message',
                'code' => 123,
            ],
        ];

        $testSource = 'Test/test.yml';
        $testConfigurationBrowser = 'chrome';
        $testConfigurationUrl = 'http://example.com';

        $genericTest = new Test(
            $testConfigurationBrowser,
            $testConfigurationUrl,
            $testSource,
            'GeneratedTest1234.php',
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
            JobStartedEmittableEvent::class => [
                'event' => new JobStartedEmittableEvent(
                    self::JOB_LABEL,
                    [
                        'Test/test1.yaml',
                        'Test/test2.yaml',
                    ]
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::STARTED,
                    new WorkerEventReference(self::JOB_LABEL, md5(self::JOB_LABEL)),
                    [
                        'tests' => [
                            'Test/test1.yaml',
                            'Test/test2.yaml',
                        ],
                    ],
                    new WorkerEventReferenceCollection([
                        new WorkerEventReference('Test/test1.yaml', md5(self::JOB_LABEL . 'Test/test1.yaml')),
                        new WorkerEventReference('Test/test2.yaml', md5(self::JOB_LABEL . 'Test/test2.yaml')),
                    ])
                ),
            ],
            SourceCompilationStartedEvent::class => [
                'event' => new SourceCompilationStartedEvent($testSource),
                'expected' => new WorkerEvent(
                    WorkerEventScope::COMPILATION,
                    WorkerEventOutcome::STARTED,
                    new WorkerEventReference($testSource, md5(self::JOB_LABEL . $testSource)),
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
                    new WorkerEventReference($testSource, md5(self::JOB_LABEL . $testSource)),
                    [
                        'source' => $testSource,
                    ],
                    new WorkerEventReferenceCollection([
                        new WorkerEventReference('step one', md5(self::JOB_LABEL . $testSource . 'step one')),
                        new WorkerEventReference('step two', md5(self::JOB_LABEL . $testSource . 'step two')),
                    ]),
                ),
            ],
            SourceCompilationFailedEvent::class => [
                'event' => new SourceCompilationFailedEvent(
                    $testSource,
                    [
                        'compile-failure-key' => 'value',
                    ]
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::COMPILATION,
                    WorkerEventOutcome::FAILED,
                    new WorkerEventReference($testSource, md5(self::JOB_LABEL . $testSource)),
                    [
                        'source' => $testSource,
                        'output' => [
                            'compile-failure-key' => 'value',
                        ],
                    ]
                ),
            ],
            'execution/started' => [
                'event' => new ExecutionEmittableEvent(self::JOB_LABEL, WorkerEventOutcome::STARTED),
                'expected' => new WorkerEvent(
                    WorkerEventScope::EXECUTION,
                    WorkerEventOutcome::STARTED,
                    new WorkerEventReference(self::JOB_LABEL, md5(self::JOB_LABEL)),
                    []
                ),
            ],
            'test/started' => [
                'event' => new TestEmittableEvent(
                    $genericTest,
                    $testDocument,
                    $testSource,
                    WorkerEventOutcome::STARTED
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::TEST,
                    WorkerEventOutcome::STARTED,
                    new WorkerEventReference($testSource, md5(self::JOB_LABEL . $testSource)),
                    [
                        'source' => $testSource,
                        'document' => $testDocumentData,
                        'step_names' => [
                            'step 1',
                        ],
                    ],
                    new WorkerEventReferenceCollection([
                        new WorkerEventReference('step 1', md5(self::JOB_LABEL . $testSource . 'step 1')),
                    ])
                ),
            ],
            'step/passed' => [
                'event' => new StepEmittableEvent(
                    $genericTest->setState(TestState::RUNNING),
                    new Step('passing step', $passingStepDocumentData),
                    $testSource,
                    'passing step',
                    WorkerEventOutcome::PASSED
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::STEP,
                    WorkerEventOutcome::PASSED,
                    new WorkerEventReference(
                        'passing step',
                        md5(self::JOB_LABEL . $testSource . 'passing step')
                    ),
                    [
                        'source' => $testSource,
                        'document' => $passingStepDocumentData,
                        'name' => 'passing step',
                    ]
                ),
            ],
            'step/failed' => [
                'event' => new StepEmittableEvent(
                    $genericTest->setState(TestState::FAILED),
                    new Step('failing step', $failingStepDocumentData),
                    $testSource,
                    'failing step',
                    WorkerEventOutcome::FAILED,
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::STEP,
                    WorkerEventOutcome::FAILED,
                    new WorkerEventReference(
                        'failing step',
                        md5(self::JOB_LABEL . $testSource . 'failing step')
                    ),
                    [
                        'source' => $testSource,
                        'document' => $failingStepDocumentData,
                        'name' => 'failing step',
                    ]
                ),
            ],
            'test/passed' => [
                'event' => new TestEmittableEvent(
                    $genericTest->setState(TestState::COMPLETE),
                    $testDocument,
                    $testSource,
                    WorkerEventOutcome::PASSED
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::TEST,
                    WorkerEventOutcome::PASSED,
                    new WorkerEventReference($testSource, md5(self::JOB_LABEL . $testSource)),
                    [
                        'source' => $testSource,
                        'document' => $testDocumentData,
                        'step_names' => [
                            'step 1',
                        ],
                    ],
                    new WorkerEventReferenceCollection([
                        new WorkerEventReference('step 1', md5(self::JOB_LABEL . $testSource . 'step 1')),
                    ])
                ),
            ],
            'test/failed' => [
                'event' => new TestEmittableEvent(
                    $genericTest->setState(TestState::FAILED),
                    $testDocument,
                    $testSource,
                    WorkerEventOutcome::FAILED
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::TEST,
                    WorkerEventOutcome::FAILED,
                    new WorkerEventReference($testSource, md5(self::JOB_LABEL . $testSource)),
                    [
                        'source' => $testSource,
                        'document' => $testDocumentData,
                        'step_names' => [
                            'step 1',
                        ],
                    ],
                    new WorkerEventReferenceCollection([
                        new WorkerEventReference('step 1', md5(self::JOB_LABEL . $testSource . 'step 1')),
                    ])
                ),
            ],
            JobTimeoutEmittableEvent::class => [
                'event' => new JobTimeoutEmittableEvent(self::JOB_LABEL, 10),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::TIME_OUT,
                    new WorkerEventReference(self::JOB_LABEL, md5(self::JOB_LABEL)),
                    [
                        'maximum_duration_in_seconds' => 10,
                    ]
                ),
            ],
            'execution/completed' => [
                'event' => new ExecutionEmittableEvent(self::JOB_LABEL, WorkerEventOutcome::COMPLETED),
                'expected' => new WorkerEvent(
                    WorkerEventScope::EXECUTION,
                    WorkerEventOutcome::COMPLETED,
                    new WorkerEventReference(self::JOB_LABEL, md5(self::JOB_LABEL)),
                    []
                ),
            ],
            'test/exception' => [
                'event' => new TestEmittableEvent(
                    $genericTest,
                    new Exception(ExecutionExceptionScope::TEST, $exceptionTestDocumentData),
                    $testSource,
                    WorkerEventOutcome::EXCEPTION
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::TEST,
                    WorkerEventOutcome::EXCEPTION,
                    new WorkerEventReference($testSource, md5(self::JOB_LABEL . $testSource)),
                    [
                        'source' => $testSource,
                        'document' => $exceptionTestDocumentData,
                        'step_names' => [
                            'step 1',
                        ],
                    ],
                    new WorkerEventReferenceCollection([
                        new WorkerEventReference('step 1', md5(self::JOB_LABEL . $testSource . 'step 1')),
                    ])
                ),
            ],
            'step/exception' => [
                'event' => new StepEmittableEvent(
                    $genericTest,
                    new StepException('step name', $exceptionStepDocumentData),
                    $testSource,
                    'step name',
                    WorkerEventOutcome::EXCEPTION,
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::STEP,
                    WorkerEventOutcome::EXCEPTION,
                    new WorkerEventReference('step name', md5(self::JOB_LABEL . $testSource . 'step name')),
                    [
                        'source' => $testSource,
                        'document' => $exceptionStepDocumentData,
                        'name' => 'step name',
                    ]
                ),
            ],
            'job/ended, complete' => [
                'event' => new JobEndedEmittableEvent(
                    self::JOB_LABEL,
                    JobEndState::COMPLETE,
                    true
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::ENDED,
                    new WorkerEventReference(self::JOB_LABEL, md5(self::JOB_LABEL)),
                    [
                        'end_state' => JobEndState::COMPLETE->value,
                        'success' => true,
                    ]
                ),
            ],
            'job/ended, timed out' => [
                'event' => new JobEndedEmittableEvent(
                    self::JOB_LABEL,
                    JobEndState::TIMED_OUT,
                    false
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::ENDED,
                    new WorkerEventReference(self::JOB_LABEL, md5(self::JOB_LABEL)),
                    [
                        'end_state' => JobEndState::TIMED_OUT->value,
                        'success' => false,
                    ]
                ),
            ],
            'job/ended, failed compilation' => [
                'event' => new JobEndedEmittableEvent(
                    self::JOB_LABEL,
                    JobEndState::FAILED_COMPILATION,
                    false
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::ENDED,
                    new WorkerEventReference(self::JOB_LABEL, md5(self::JOB_LABEL)),
                    [
                        'end_state' => JobEndState::FAILED_COMPILATION->value,
                        'success' => false,
                    ]
                ),
            ],
            'job/ended, test failure' => [
                'event' => new JobEndedEmittableEvent(
                    self::JOB_LABEL,
                    JobEndState::FAILED_TEST_FAILURE,
                    false
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::ENDED,
                    new WorkerEventReference(self::JOB_LABEL, md5(self::JOB_LABEL)),
                    [
                        'end_state' => JobEndState::FAILED_TEST_FAILURE->value,
                        'success' => false,
                    ]
                ),
            ],
            'job/ended, test exception' => [
                'event' => new JobEndedEmittableEvent(
                    self::JOB_LABEL,
                    JobEndState::FAILED_TEST_EXCEPTION,
                    false
                ),
                'expected' => new WorkerEvent(
                    WorkerEventScope::JOB,
                    WorkerEventOutcome::ENDED,
                    new WorkerEventReference(self::JOB_LABEL, md5(self::JOB_LABEL)),
                    [
                        'end_state' => JobEndState::FAILED_TEST_EXCEPTION->value,
                        'success' => false,
                    ]
                ),
            ],
        ];
    }
}
