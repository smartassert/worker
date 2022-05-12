<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Test;
use App\Entity\TestConfiguration;
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
use App\Model\Document\Step;
use App\Model\Document\Test as TestDocument;
use App\Services\WorkerEventFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\MockSuiteManifest;
use webignition\BasilCompilerModels\ErrorOutputInterface;
use webignition\ObjectReflector\ObjectReflector;
use webignition\YamlDocument\Document;

class WorkerEventFactoryTest extends AbstractBaseFunctionalTest
{
    private WorkerEventFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(WorkerEventFactory::class);
        \assert($factory instanceof WorkerEventFactory);
        $this->factory = $factory;
    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreateForEvent(EventInterface $event, WorkerEvent $expectedWorkerEvent): void
    {
        $jobLabel = md5((string) rand());
        $job = Job::create($jobLabel, '', 600);

        $workerEvent = $this->factory->createForEvent($job, $event);

        $expectedReferenceSource = str_replace('{{ job_label }}', $jobLabel, $expectedWorkerEvent->getReference());
        ObjectReflector::setProperty(
            $expectedWorkerEvent,
            WorkerEvent::class,
            'reference',
            md5($expectedReferenceSource)
        );

        self::assertInstanceOf(WorkerEvent::class, $workerEvent);
        self::assertNotNull($workerEvent->getId());
        self::assertSame($expectedWorkerEvent->getType(), $workerEvent->getType());
        self::assertSame($expectedWorkerEvent->getReference(), $workerEvent->getReference());
        self::assertSame($expectedWorkerEvent->getPayload(), $workerEvent->getPayload());
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        $sourceDirectory = '/app/source';
        $sourcePath = 'Test/test.yml';
        $passingStepPath = 'Test/passing.yml';
        $failingStepPath = 'Test/failing.yml';

        $source = $sourceDirectory . '/' . $sourcePath;
        $failingStepSource = $sourceDirectory . '/' . $failingStepPath;

        $errorOutputData = [
            'error-output-key' => 'error-output-value',
        ];

        $errorOutput = \Mockery::mock(ErrorOutputInterface::class);
        $errorOutput
            ->shouldReceive('getData')
            ->andReturn($errorOutputData)
        ;

        $testDocumentData = [
            'type' => 'test',
            'payload' => [
                'path' => $sourcePath,
            ],
        ];

        $testDocument = new TestDocument(
            new Document((string) json_encode($testDocumentData))
        );

        $testConfiguration = \Mockery::mock(TestConfiguration::class);

        $test = Test::create($testConfiguration, $source, '', 1, 1);

        $passingStepName = 'passing step';
        $failingStepName = 'failing step';

        $passingStepData = ['type' => 'step', 'payload' => ['name' => $passingStepName]];
        $failingStepData = ['type' => 'step', 'payload' => ['name' => $failingStepName]];

        $passingStepDocument = new Document((string) json_encode($passingStepData));
        $failingStepDocument = new Document((string) json_encode($failingStepData));

        return [
            JobReadyEvent::class => [
                'event' => new JobReadyEvent(),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::JOB_STARTED,
                    '{{ job_label }}',
                    []
                ),
            ],
            JobCompiledEvent::class => [
                'event' => new JobCompiledEvent(),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::JOB_COMPILED,
                    '{{ job_label }}',
                    []
                ),
            ],
            JobTimeoutEvent::class => [
                'event' => new JobTimeoutEvent(150),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::JOB_TIME_OUT,
                    '{{ job_label }}',
                    [
                        'maximum_duration_in_seconds' => 150,
                    ]
                ),
            ],
            JobCompletedEvent::class => [
                'event' => new JobCompletedEvent(),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::JOB_COMPLETED,
                    '{{ job_label }}',
                    []
                ),
            ],
            JobFailedEvent::class => [
                'event' => new JobFailedEvent(),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::JOB_FAILED,
                    '{{ job_label }}',
                    []
                ),
            ],
            SourceCompilationStartedEvent::class => [
                'event' => new SourceCompilationStartedEvent($source),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::COMPILATION_STARTED,
                    '{{ job_label }}' . $source,
                    [
                        'source' => $source,
                    ]
                ),
            ],
            SourceCompilationPassedEvent::class => [
                'event' => new SourceCompilationPassedEvent($source, (new MockSuiteManifest())->getMock()),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::COMPILATION_PASSED,
                    '{{ job_label }}' . $source,
                    [
                        'source' => $source,
                    ]
                ),
            ],
            SourceCompilationFailedEvent::class => [
                'event' => new SourceCompilationFailedEvent($source, $errorOutput),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::COMPILATION_FAILED,
                    '{{ job_label }}' . $source,
                    [
                        'source' => $source,
                        'output' => $errorOutputData,
                    ]
                ),
            ],
            ExecutionStartedEvent::class => [
                'event' => new ExecutionStartedEvent(),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::EXECUTION_STARTED,
                    '{{ job_label }}',
                    []
                ),
            ],
            ExecutionCompletedEvent::class => [
                'event' => new ExecutionCompletedEvent(),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::EXECUTION_COMPLETED,
                    '{{ job_label }}',
                    []
                ),
            ],
            TestStartedEvent::class => [
                'event' => new TestStartedEvent($testDocument),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::TEST_STARTED,
                    '{{ job_label }}' . $sourcePath,
                    $testDocumentData
                ),
            ],
            TestPassedEvent::class => [
                'event' => new TestPassedEvent($testDocument, $test),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::TEST_PASSED,
                    '{{ job_label }}' . $sourcePath,
                    $testDocumentData
                ),
            ],
            TestFailedEvent::class => [
                'event' => new TestFailedEvent($testDocument),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::TEST_FAILED,
                    '{{ job_label }}' . $sourcePath,
                    $testDocumentData
                ),
            ],
            StepPassedEvent::class => [
                'event' => new StepPassedEvent(new Step($passingStepDocument), $passingStepPath),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::STEP_PASSED,
                    '{{ job_label }}' . $passingStepPath . $passingStepName,
                    $passingStepData
                ),
            ],
            StepFailedEvent::class => [
                'event' => new StepFailedEvent(
                    new Step($failingStepDocument),
                    $failingStepPath,
                    Test::create($testConfiguration, $failingStepSource, '', 1, 1),
                ),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::STEP_FAILED,
                    '{{ job_label }}' . $failingStepPath . $failingStepName,
                    $failingStepData
                ),
            ],
        ];
    }
}
