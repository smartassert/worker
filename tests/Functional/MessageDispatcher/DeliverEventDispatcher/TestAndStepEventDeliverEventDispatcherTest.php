<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher\DeliverEventDispatcher;

use App\Entity\Job;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Event\StepFailedEvent;
use App\Event\StepPassedEvent;
use App\Event\TestFailedEvent;
use App\Event\TestPassedEvent;
use App\Event\TestStartedEvent;
use App\Model\Document\Step;
use App\Model\Document\Test as TestDocument;
use App\Repository\TestRepository;
use App\Services\ApplicationWorkflowHandler;
use App\Services\ExecutionWorkflowHandler;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EventListenerRemover;
use webignition\YamlDocument\Document;

class TestAndStepEventDeliverEventDispatcherTest extends AbstractDeliverEventDispatcherTest
{
    private const RELATIVE_SOURCE = 'Test/test.yml';
    private const ABSOLUTE_SOURCE = '/app/source/' . self::RELATIVE_SOURCE;

    private Test $test;

    protected function setUp(): void
    {
        parent::setUp();

        $eventListenerRemover = self::getContainer()->get(EventListenerRemover::class);
        \assert($eventListenerRemover instanceof EventListenerRemover);
        $eventListenerRemover->remove([
            ApplicationWorkflowHandler::class => [
                TestFailedEvent::class => ['dispatchJobFailedEvent'],
                TestPassedEvent::class => ['dispatchJobCompletedEvent'],
            ],
            ExecutionWorkflowHandler::class => [
                TestPassedEvent::class => [
                    'dispatchExecutionCompletedEvent',
                    'dispatchNextExecuteTestMessageFromTestPassedEvent',
                ],
            ],
        ]);

        $testRepository = self::getContainer()->get(TestRepository::class);
        if ($testRepository instanceof TestRepository) {
            $test = $testRepository->findAll()[0];

            if ($test instanceof Test) {
                $this->test = $test;
            }
        }
    }

    /**
     * @return array<mixed>
     */
    public function createWorkerEventAndDispatchDeliverEventMessageDataProvider(): array
    {
        $testDocument = new Document('type: test' . "\n" . 'payload: { path: "' . self::RELATIVE_SOURCE . '" }');
        $passingStepName = 'passing step';
        $failingStepName = 'failing step';
        $passingStepDocument = new Document('type: step' . "\n" . 'payload: { name: "' . $passingStepName . '" }');
        $failingStepDocument = new Document('type: step' . "\n" . 'payload: { name: "' . $failingStepName . '" }');

        return [
            TestStartedEvent::class => [
                'eventCreator' => function (Test $test) use ($testDocument): TestStartedEvent {
                    return new TestStartedEvent($test, new TestDocument($testDocument));
                },
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::TEST_STARTED,
                    md5(self::JOB_LABEL . self::RELATIVE_SOURCE),
                    [
                        'type' => 'test',
                        'payload' => [
                            'path' => self::RELATIVE_SOURCE,
                        ],
                    ]
                ),
            ],
            TestPassedEvent::class => [
                'eventCreator' => function (Test $test) use ($testDocument): TestPassedEvent {
                    return new TestPassedEvent($test->setState(Test::STATE_COMPLETE), new TestDocument($testDocument));
                },
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::TEST_PASSED,
                    md5(self::JOB_LABEL . self::RELATIVE_SOURCE),
                    [
                        'type' => 'test',
                        'payload' => [
                            'path' => self::RELATIVE_SOURCE,
                        ],
                    ]
                ),
            ],
            TestFailedEvent::class => [
                'eventCreator' => function (Test $test) use ($testDocument): TestFailedEvent {
                    return new TestFailedEvent($test->setState(Test::STATE_FAILED), new TestDocument($testDocument));
                },
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::TEST_FAILED,
                    md5(self::JOB_LABEL . self::RELATIVE_SOURCE),
                    [
                        'type' => 'test',
                        'payload' => [
                            'path' => self::RELATIVE_SOURCE,
                        ],
                    ]
                ),
            ],
            StepPassedEvent::class => [
                'eventCreator' => function (Test $test) use ($passingStepDocument): StepPassedEvent {
                    return new StepPassedEvent($test, new Step($passingStepDocument), self::RELATIVE_SOURCE);
                },
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::STEP_PASSED,
                    md5(self::JOB_LABEL . self::RELATIVE_SOURCE . $passingStepName),
                    [
                        'type' => 'step',
                        'payload' => [
                            'name' => $passingStepName,
                        ],
                    ]
                ),
            ],
            StepFailedEvent::class => [
                'eventCreator' => function (Test $test) use ($failingStepDocument): StepFailedEvent {
                    return new StepFailedEvent(
                        $test->setState(Test::STATE_FAILED),
                        new Step($failingStepDocument),
                        self::RELATIVE_SOURCE
                    );
                },
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::STEP_FAILED,
                    md5(self::JOB_LABEL . self::RELATIVE_SOURCE . $failingStepName),
                    [
                        'type' => 'step',
                        'payload' => [
                            'name' => $failingStepName,
                        ],
                    ]
                ),
            ],
        ];
    }

    protected function getEntityClassNamesToRemove(): array
    {
        return [
            Job::class,
            Test::class,
            WorkerEvent::class,
        ];
    }

    protected function getEnvironmentSetup(): EnvironmentSetup
    {
        return (new EnvironmentSetup())
            ->withJobSetup(
                (new JobSetup())
                    ->withLabel(self::JOB_LABEL)
            )
            ->withTestSetups([
                (new TestSetup())
                    ->withSource(self::ABSOLUTE_SOURCE)
            ])
        ;
    }

    protected function getEventCreatorArguments(): array
    {
        return [
            $this->test,
        ];
    }
}
