<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\WorkerEvent;
use App\Repository\WorkerEventRepository;
use App\Request\CreateJobRequest;
use App\Tests\Services\CreateJobSourceFactory;
use App\Tests\Services\EntityRemover;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\CallbackReceiverLogReader\Parser;
use Symfony\Component\Process\Process;
use webignition\WaitFor\WaitFor;

class CreateCompileExecuteTest extends AbstractIntegrationTestCase
{
    private CreateJobSourceFactory $createJobSourceFactory;
    private WorkerEventRepository $workerEventRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $createJobSourceFactory = self::getContainer()->get(CreateJobSourceFactory::class);
        \assert($createJobSourceFactory instanceof CreateJobSourceFactory);
        $this->createJobSourceFactory = $createJobSourceFactory;

        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($workerEventRepository instanceof WorkerEventRepository);
        $this->workerEventRepository = $workerEventRepository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        \assert($entityRemover instanceof EntityRemover);
        $entityRemover->removeAll();
    }

    /**
     * @param non-empty-string[]                  $manifestPaths
     * @param string[]                            $sourcePaths
     * @param array<mixed>                        $expectedApplicationState
     * @param array<mixed>                        $expectedCompilationState
     * @param array<mixed>                        $expectedExecutionState
     * @param array<int, array<mixed>>            $expectedTestDataCollection
     * @param callable(int, string): array<mixed> $expectedRequestsCreator
     */
    #[DataProvider('createCompileExecuteDataProvider')]
    public function testCreateCompileExecute(
        array $manifestPaths,
        array $sourcePaths,
        string $jobLabel,
        array $expectedApplicationState,
        array $expectedCompilationState,
        array $expectedExecutionState,
        array $expectedTestDataCollection,
        int $expectedDispatchedNotificationsCount,
        callable $expectedRequestsCreator,
    ): void {
        $jobMaximumDurationInSeconds = 60;

        $createResponse = self::makeCreateJobRequest([
            CreateJobRequest::KEY_LABEL => $jobLabel,
            CreateJobRequest::KEY_EVENT_NOTIFY_URL => 'http://callback-receiver:8080',
            CreateJobRequest::KEY_MAXIMUM_DURATION => $jobMaximumDurationInSeconds,
            CreateJobRequest::KEY_SOURCE => $this->createJobSourceFactory->create($manifestPaths, $sourcePaths),
            CreateJobRequest::STATE_NOTIFY_URL => 'http://callback-receiver:8080',
        ]);

        self::assertSame(200, $createResponse->getStatusCode());
        self::assertSame('application/json', $createResponse->getHeaderLine('content-type'));

        new WaitFor()->waitFor(
            $jobMaximumDurationInSeconds,
            function () {
                return self::isApplicationComplete();
            },
            'application to finish.'
        );

        $jobData = self::fetchJob();

        self::assertArrayHasKey('event_ids', $jobData);
        $eventIds = $jobData['event_ids'];
        self::assertNotEmpty($eventIds);
        self::assertSame($eventIds, $this->workerEventRepository->findAllIds());

        $applicationStateData = self::fetchApplicationState();
        self::assertSame($expectedApplicationState, $applicationStateData['application']);
        self::assertSame($expectedCompilationState, $applicationStateData['compilation']);
        self::assertSame($expectedExecutionState, $applicationStateData['execution']);
        self::assertSame(
            [
                'state' => 'complete',
                'meta_state' => [
                    'pending' => false,
                    'ended' => true,
                    'succeeded' => true,
                ],
                'previous_states' => [
                    'awaiting',
                    'running',
                    'complete',
                ],
            ],
            $applicationStateData['event_delivery']
        );

        $testDataCollection = $jobData['tests'];
        self::assertIsArray($testDataCollection);

        self::assertCount(count($expectedTestDataCollection), $testDataCollection);

        foreach ($testDataCollection as $index => $testData) {
            self::assertIsArray($testData);
            $expectedTestData = $expectedTestDataCollection[$index];

            self::assertSame($expectedTestData['browser'], $testData['browser']);
            self::assertSame($expectedTestData['url'], $testData['url']);
            self::assertSame($expectedTestData['source'], $testData['source']);
            self::assertSame($expectedTestData['step_names'], $testData['step_names']);
            self::assertSame($expectedTestData['state'], $testData['state']);
            self::assertSame($expectedTestData['position'], $testData['position']);
            self::assertIsString($testData['target']);
            self::assertMatchesRegularExpression('/^Generated.{32}Test\.php$/', $testData['target']);
        }

        $firstEvent = $this->workerEventRepository->findOneBy([], ['id' => 'ASC']);
        \assert($firstEvent instanceof WorkerEvent);

        $expectedRequests = $expectedRequestsCreator($firstEvent->getId(), $jobLabel);

        $process = Process::fromShellCommandline('docker logs callback-receiver');
        $process->run();

        $output = $process->getOutput();
        $parser = new Parser();

        $requests = $parser->parse($output, $expectedDispatchedNotificationsCount);
        self::assertCount($expectedDispatchedNotificationsCount, $requests);
        self::assertSame(count($requests), count($expectedRequests));

        foreach ($expectedRequests as $requestIndex => $expectedRequestData) {
            $request = $requests[$requestIndex];
            \assert(is_array($expectedRequestData));

            self::assertSame('POST', $request->getMethod());
            self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
            self::assertEquals($expectedRequestData['uri'], (string) $request->getUri());

            $payload = json_decode($request->getBody()->getContents(), true);
            self::assertIsArray($payload);
            self::assertEquals($expectedRequestData['payload'], $payload);
        }
    }

    /**
     * @return array<mixed>
     */
    public static function createCompileExecuteDataProvider(): array
    {
        $jobLabel = md5((string) rand());

        $awaitingComponent = [
            'state' => 'awaiting',
            'meta_state' => [
                'pending' => true,
                'ended' => false,
                'succeeded' => false,
            ],
            'previous_states' => [
                'awaiting',
            ],
        ];

        $applicationCompiling = [
            'state' => 'compiling',
            'meta_state' => [
                'pending' => false,
                'ended' => false,
                'succeeded' => false,
            ],
            'previous_states' => [
                'awaiting',
                'compiling',
            ],
        ];

        $applicationExecuting = [
            'state' => 'executing',
            'meta_state' => [
                'pending' => false,
                'ended' => false,
                'succeeded' => false,
            ],
            'previous_states' => [
                'awaiting',
                'compiling',
                'executing',
            ],
        ];

        $runningComponent = [
            'state' => 'running',
            'meta_state' => [
                'pending' => false,
                'ended' => false,
                'succeeded' => false,
            ],
            'previous_states' => [
                'awaiting',
                'running',
            ],
        ];

        $applicationCompletingEventDelivery = [
            'state' => 'completing-event-delivery',
            'meta_state' => [
                'pending' => false,
                'ended' => false,
                'succeeded' => false,
            ],
            'previous_states' => [
                'awaiting',
                'compiling',
                'executing',
                'completing-event-delivery',
            ],
        ];

        $applicationComplete = [
            'state' => 'complete',
            'meta_state' => [
                'pending' => false,
                'ended' => true,
                'succeeded' => true,
            ],
            'previous_states' => [
                'awaiting',
                'compiling',
                'executing',
                'completing-event-delivery',
                'complete',
            ],
        ];

        $applicationFailed = [
            'state' => 'failed',
            'meta_state' => [
                'pending' => false,
                'ended' => true,
                'succeeded' => false,
            ],
            'previous_states' => [
                'awaiting',
                'compiling',
                'executing',
                'completing-event-delivery',
                'failed',
            ],
        ];

        $completeComponent = [
            'state' => 'complete',
            'meta_state' => [
                'pending' => false,
                'ended' => true,
                'succeeded' => true,
            ],
            'previous_states' => [
                'awaiting',
                'running',
                'complete',
            ],
        ];

        $failedComponent = [
            'state' => 'failed',
            'meta_state' => [
                'pending' => false,
                'ended' => true,
                'succeeded' => false,
            ],
            'previous_states' => [
                'awaiting',
                'running',
                'failed',
            ],
        ];

        $cancelledComponent = [
            'state' => 'cancelled',
            'meta_state' => [
                'pending' => false,
                'ended' => true,
                'succeeded' => false,
            ],
            'previous_states' => [
                'awaiting',
                'running',
                'cancelled',
            ],
        ];

        return [
            'compilation failed on second test' => [
                'manifestPaths' => [
                    'Test/chrome-open-index.yml',
                    'Test/chrome-open-index-compilation-failure.yml',
                ],
                'sourcePaths' => [
                    'Page/index.yml',
                    'Test/chrome-open-index.yml',
                    'Test/chrome-open-index-compilation-failure.yml',
                ],
                'jobLabel' => $jobLabel,
                'expectedApplicationState' => [
                    'state' => 'failed',
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => false,
                    ],
                    'previous_states' => [
                        'awaiting',
                        'compiling',
                        'executing',
                        'completing-event-delivery',
                        'failed',
                    ],
                ],
                'expectedCompilationState' => [
                    'state' => 'failed',
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => false,
                    ],
                    'previous_states' => [
                        'awaiting',
                        'running',
                        'failed',
                    ],
                ],
                'expectedExecutionState' => [
                    'state' => 'awaiting',
                    'meta_state' => [
                        'pending' => true,
                        'ended' => false,
                        'succeeded' => false,
                    ],
                    'previous_states' => [
                        'awaiting',
                    ],
                ],
                'expectedTestDataCollection' => [
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-open-index.yml',
                        'step_names' => ['verify page is open'],
                        'state' => 'awaiting',
                        'position' => 1,
                    ],
                ],
                'expectedDispatchedNotificationsCount' => 11,
                'expectedRequestsCreator' => function (
                    int $firstSequenceNumber,
                    string $workerJobLabel
                ) use (
                    $awaitingComponent,
                    $runningComponent,
                    $failedComponent,
                    $completeComponent,
                    $applicationCompiling,
                    $applicationFailed,
                ) {
                    return [
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $awaitingComponent,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $awaitingComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $awaitingComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $awaitingComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => $firstSequenceNumber,
                                'type' => 'job/started',
                                'body' => [
                                    'tests' => [
                                        'Test/chrome-open-index.yml',
                                        'Test/chrome-open-index-compilation-failure.yml',
                                    ],
                                ],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                                'related_references' => [
                                    [
                                        'label' => 'Test/chrome-open-index.yml',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-index.yml'
                                        ),
                                    ],
                                    [
                                        'label' => 'Test/chrome-open-index-compilation-failure.yml',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-index-compilation-failure.yml',
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'lifecycle/compilation-started',
                                'body' => [],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'compilation/started',
                                'body' => [
                                    'source' => 'Test/chrome-open-index.yml',
                                ],
                                'label' => 'Test/chrome-open-index.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index.yml'
                                ),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'compilation/passed',
                                'body' => [
                                    'source' => 'Test/chrome-open-index.yml',
                                ],
                                'label' => 'Test/chrome-open-index.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index.yml'
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-index.yml'
                                            . 'verify page is open',
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'compilation/started',
                                'body' => [
                                    'source' => 'Test/chrome-open-index-compilation-failure.yml',
                                ],
                                'label' => 'Test/chrome-open-index-compilation-failure.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index-compilation-failure.yml'
                                ),
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationFailed,
                                    'compilation' => $failedComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'compilation/failed',
                                'body' => [
                                    'source' => 'Test/chrome-open-index-compilation-failure.yml',
                                    'output' => [
                                        'message' => sprintf(
                                            'Invalid test at path "%s": test-step-invalid',
                                            'Test/chrome-open-index-compilation-failure.yml',
                                        ),
                                        'code' => 204,
                                        'context' => [
                                            'test_path' => 'Test/chrome-open-index-compilation-failure.yml',
                                            'validation_result' => [
                                                'type' => 'test',
                                                'reason' => 'test-step-invalid',
                                                'context' => [
                                                    'step-name' => 'verify page is open',
                                                ],
                                                'previous' => [
                                                    'type' => 'step',
                                                    'reason' => 'step-no-assertions',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'label' => 'Test/chrome-open-index-compilation-failure.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index-compilation-failure.yml'
                                ),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => $firstSequenceNumber + 1,
                                'type' => 'job/ended',
                                'body' => [
                                    'end_state' => 'failed/compilation',
                                    'success' => false,
                                    'event_count' => 7,
                                ],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationFailed,
                                    'compilation' => $failedComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationFailed,
                                    'compilation' => $failedComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $completeComponent,
                                ],
                            ],
                        ],
                    ];
                },
            ],
            'compilation failed on first test' => [
                'manifestPaths' => [
                    'Test/chrome-open-index-compilation-failure.yml',
                ],
                'sourcePaths' => [
                    'Test/chrome-open-index-compilation-failure.yml',
                ],
                'jobLabel' => $jobLabel,
                'expectedApplicationState' => [
                    'state' => 'failed',
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => false,
                    ],
                    'previous_states' => [
                        'awaiting',
                        'compiling',
                        'executing',
                        'completing-event-delivery',
                        'failed',
                    ],
                ],
                'expectedCompilationState' => [
                    'state' => 'failed',
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => false,
                    ],
                    'previous_states' => [
                        'awaiting',
                        'running',
                        'failed',
                    ],
                ],
                'expectedExecutionState' => [
                    'state' => 'awaiting',
                    'meta_state' => [
                        'pending' => true,
                        'ended' => false,
                        'succeeded' => false,
                    ],
                    'previous_states' => [
                        'awaiting',
                    ],
                ],
                'expectedTestDataCollection' => [],
                'expectedDispatchedNotificationsCount' => 9,
                'expectedRequestsCreator' => function (
                    int $firstSequenceNumber,
                    string $workerJobLabel
                ) use (
                    $awaitingComponent,
                    $runningComponent,
                    $failedComponent,
                    $completeComponent,
                    $applicationCompiling,
                    $applicationFailed,
                ) {
                    return [
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $awaitingComponent,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $awaitingComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $awaitingComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $awaitingComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => $firstSequenceNumber,
                                'type' => 'job/started',
                                'body' => [
                                    'tests' => [
                                        'Test/chrome-open-index-compilation-failure.yml',
                                    ],
                                ],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                                'related_references' => [
                                    [
                                        'label' => 'Test/chrome-open-index-compilation-failure.yml',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-index-compilation-failure.yml'
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'lifecycle/compilation-started',
                                'body' => [],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'compilation/started',
                                'body' => [
                                    'source' => 'Test/chrome-open-index-compilation-failure.yml',
                                ],
                                'label' => 'Test/chrome-open-index-compilation-failure.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index-compilation-failure.yml'
                                ),
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationFailed,
                                    'compilation' => $failedComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'compilation/failed',
                                'body' => [
                                    'source' => 'Test/chrome-open-index-compilation-failure.yml',
                                    'output' => [
                                        'message' => sprintf(
                                            'Invalid test at path "%s": test-step-invalid',
                                            'Test/chrome-open-index-compilation-failure.yml',
                                        ),
                                        'code' => 204,
                                        'context' => [
                                            'test_path' => 'Test/chrome-open-index-compilation-failure.yml',
                                            'validation_result' => [
                                                'type' => 'test',
                                                'reason' => 'test-step-invalid',
                                                'context' => [
                                                    'step-name' => 'verify page is open',
                                                ],
                                                'previous' => [
                                                    'type' => 'step',
                                                    'reason' => 'step-no-assertions',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'label' => 'Test/chrome-open-index-compilation-failure.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index-compilation-failure.yml'
                                ),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => $firstSequenceNumber + 1,
                                'type' => 'job/ended',
                                'body' => [
                                    'end_state' => 'failed/compilation',
                                    'success' => false,
                                    'event_count' => 5,
                                ],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationFailed,
                                    'compilation' => $failedComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationFailed,
                                    'compilation' => $failedComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $completeComponent,
                                ],
                            ],
                        ],
                    ];
                },
            ],
            'three successful tests' => [
                'manifestPaths' => [
                    'Test/chrome-open-index.yml',
                    'Test/chrome-firefox-open-index.yml',
                    'Test/chrome-open-form.yml',
                ],
                'sourcePaths' => [
                    'Page/index.yml',
                    'Test/chrome-open-index.yml',
                    'Test/chrome-firefox-open-index.yml',
                    'Test/chrome-open-form.yml',
                ],
                'jobLabel' => $jobLabel,
                'expectedApplicationState' => [
                    'state' => 'complete',
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => true,
                    ],
                    'previous_states' => [
                        'awaiting',
                        'compiling',
                        'executing',
                        'completing-event-delivery',
                        'complete',
                    ],
                ],
                'expectedCompilationState' => [
                    'state' => 'complete',
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => true,
                    ],
                    'previous_states' => [
                        'awaiting',
                        'running',
                        'complete',
                    ],
                ],
                'expectedExecutionState' => [
                    'state' => 'complete',
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => true,
                    ],
                    'previous_states' => [
                        'awaiting',
                        'running',
                        'complete',
                    ],
                ],
                'expectedTestDataCollection' => [
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-open-index.yml',
                        'step_names' => ['verify page is open'],
                        'state' => 'complete',
                        'position' => 1,
                    ],
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-firefox-open-index.yml',
                        'step_names' => ['verify page is open'],
                        'state' => 'complete',
                        'position' => 2,
                    ],
                    [
                        'browser' => 'firefox',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-firefox-open-index.yml',
                        'step_names' => ['verify page is open'],
                        'state' => 'complete',
                        'position' => 3,
                    ],
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/form.html',
                        'source' => 'Test/chrome-open-form.yml',
                        'step_names' => ['verify page is open'],
                        'state' => 'complete',
                        'position' => 4,
                    ],
                ],
                'expectedDispatchedNotificationsCount' => 30,
                'expectedRequestsCreator' => function (
                    int $firstSequenceNumber,
                    string $workerJobLabel
                ) use (
                    $awaitingComponent,
                    $runningComponent,
                    $completeComponent,
                    $applicationCompiling,
                    $applicationExecuting,
                    $applicationCompletingEventDelivery,
                    $applicationComplete,
                ) {
                    return [
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $awaitingComponent,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $awaitingComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $awaitingComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $awaitingComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => $firstSequenceNumber,
                                'type' => 'job/started',
                                'body' => [
                                    'tests' => [
                                        'Test/chrome-open-index.yml',
                                        'Test/chrome-firefox-open-index.yml',
                                        'Test/chrome-open-form.yml',
                                    ],
                                ],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                                'related_references' => [
                                    [
                                        'label' => 'Test/chrome-open-index.yml',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-index.yml'
                                        ),
                                    ],
                                    [
                                        'label' => 'Test/chrome-firefox-open-index.yml',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-firefox-open-index.yml'
                                        ),
                                    ],
                                    [
                                        'label' => 'Test/chrome-open-form.yml',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-form.yml'
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'lifecycle/compilation-started',
                                'body' => [],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'compilation/started',
                                'body' => [
                                    'source' => 'Test/chrome-open-index.yml',
                                ],
                                'label' => 'Test/chrome-open-index.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index.yml'
                                ),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'compilation/passed',
                                'body' => [
                                    'source' => 'Test/chrome-open-index.yml',
                                ],
                                'label' => 'Test/chrome-open-index.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index.yml'
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-index.yml'
                                            . 'verify page is open'
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'compilation/started',
                                'body' => [
                                    'source' => 'Test/chrome-firefox-open-index.yml',
                                ],
                                'label' => 'Test/chrome-firefox-open-index.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-firefox-open-index.yml'
                                ),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'compilation/passed',
                                'body' => [
                                    'source' => 'Test/chrome-firefox-open-index.yml',
                                ],
                                'label' => 'Test/chrome-firefox-open-index.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-firefox-open-index.yml'
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-firefox-open-index.yml'
                                            . 'verify page is open'
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'compilation/started',
                                'body' => [
                                    'source' => 'Test/chrome-open-form.yml',
                                ],
                                'label' => 'Test/chrome-open-form.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-form.yml'
                                ),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'compilation/passed',
                                'body' => [
                                    'source' => 'Test/chrome-open-form.yml',
                                ],
                                'label' => 'Test/chrome-open-form.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-form.yml'
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-form.yml'
                                            . 'verify page is open'
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationExecuting,
                                    'compilation' => $completeComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'lifecycle/compilation-completed',
                                'body' => [],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'lifecycle/execution-started',
                                'body' => [],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'test/started',
                                'body' => [
                                    'source' => 'Test/chrome-open-index.yml',
                                    'document' => [
                                        'type' => 'test',
                                        'payload' => [
                                            'path' => 'Test/chrome-open-index.yml',
                                            'config' => [
                                                'browser' => 'chrome',
                                                'url' => 'http://html-fixtures/index.html',
                                            ],
                                        ],
                                    ],
                                    'step_names' => [
                                        'verify page is open',
                                    ],
                                ],
                                'label' => 'Test/chrome-open-index.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index.yml'
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-index.yml'
                                            . 'verify page is open',
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationExecuting,
                                    'compilation' => $completeComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationExecuting,
                                    'compilation' => $completeComponent,
                                    'execution' => $runningComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'step/passed',
                                'body' => [
                                    'source' => 'Test/chrome-open-index.yml',
                                    'document' => [
                                        'type' => 'step',
                                        'payload' => [
                                            'name' => 'verify page is open',
                                            'status' => 'passed',
                                            'statements' => [
                                                [
                                                    'type' => 'assertion',
                                                    'source' => '$page.url is "http://html-fixtures/index.html"',
                                                    'status' => 'passed',
                                                    'transformations' => [
                                                        [
                                                            'type' => 'resolution',
                                                            'source' => '$page.url is $index.url',
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'name' => 'verify page is open',
                                ],
                                'label' => 'verify page is open',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index.ymlverify page is open',
                                ),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'test/passed',
                                'body' => [
                                    'source' => 'Test/chrome-open-index.yml',
                                    'document' => [
                                        'type' => 'test',
                                        'payload' => [
                                            'path' => 'Test/chrome-open-index.yml',
                                            'config' => [
                                                'browser' => 'chrome',
                                                'url' => 'http://html-fixtures/index.html',
                                            ],
                                        ],
                                    ],
                                    'step_names' => [
                                        'verify page is open',
                                    ],
                                ],
                                'label' => 'Test/chrome-open-index.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index.yml'
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-index.yml'
                                            . 'verify page is open',
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'test/started',
                                'body' => [
                                    'source' => 'Test/chrome-firefox-open-index.yml',
                                    'document' => [
                                        'type' => 'test',
                                        'payload' => [
                                            'path' => 'Test/chrome-firefox-open-index.yml',
                                            'config' => [
                                                'browser' => 'chrome',
                                                'url' => 'http://html-fixtures/index.html',
                                            ],
                                        ],
                                    ],
                                    'step_names' => [
                                        'verify page is open',
                                    ],
                                ],
                                'label' => 'Test/chrome-firefox-open-index.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-firefox-open-index.yml'
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-firefox-open-index.yml'
                                            . 'verify page is open',
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'step/passed',
                                'body' => [
                                    'source' => 'Test/chrome-firefox-open-index.yml',
                                    'document' => [
                                        'type' => 'step',
                                        'payload' => [
                                            'name' => 'verify page is open',
                                            'status' => 'passed',
                                            'statements' => [
                                                [
                                                    'type' => 'assertion',
                                                    'source' => '$page.url is "http://html-fixtures/index.html"',
                                                    'status' => 'passed',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'name' => 'verify page is open',
                                ],
                                'label' => 'verify page is open',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-firefox-open-index.yml'
                                    . 'verify page is open',
                                ),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'test/passed',
                                'body' => [
                                    'source' => 'Test/chrome-firefox-open-index.yml',
                                    'document' => [
                                        'type' => 'test',
                                        'payload' => [
                                            'path' => 'Test/chrome-firefox-open-index.yml',
                                            'config' => [
                                                'browser' => 'chrome',
                                                'url' => 'http://html-fixtures/index.html',
                                            ],
                                        ],
                                    ],
                                    'step_names' => [
                                        'verify page is open',
                                    ],
                                ],
                                'label' => 'Test/chrome-firefox-open-index.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-firefox-open-index.yml'
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-firefox-open-index.yml'
                                            . 'verify page is open',
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'test/started',
                                'body' => [
                                    'source' => 'Test/chrome-firefox-open-index.yml',
                                    'document' => [
                                        'type' => 'test',
                                        'payload' => [
                                            'path' => 'Test/chrome-firefox-open-index.yml',
                                            'config' => [
                                                'browser' => 'firefox',
                                                'url' => 'http://html-fixtures/index.html',
                                            ],
                                        ],
                                    ],
                                    'step_names' => [
                                        'verify page is open',
                                    ],
                                ],
                                'label' => 'Test/chrome-firefox-open-index.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-firefox-open-index.yml'
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-firefox-open-index.yml'
                                            . 'verify page is open',
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'step/passed',
                                'body' => [
                                    'source' => 'Test/chrome-firefox-open-index.yml',
                                    'document' => [
                                        'type' => 'step',
                                        'payload' => [
                                            'name' => 'verify page is open',
                                            'status' => 'passed',
                                            'statements' => [
                                                [
                                                    'type' => 'assertion',
                                                    'source' => '$page.url is "http://html-fixtures/index.html"',
                                                    'status' => 'passed',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'name' => 'verify page is open',
                                ],
                                'label' => 'verify page is open',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-firefox-open-index.yml'
                                    . 'verify page is open',
                                ),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'test/passed',
                                'body' => [
                                    'source' => 'Test/chrome-firefox-open-index.yml',
                                    'document' => [
                                        'type' => 'test',
                                        'payload' => [
                                            'path' => 'Test/chrome-firefox-open-index.yml',
                                            'config' => [
                                                'browser' => 'firefox',
                                                'url' => 'http://html-fixtures/index.html',
                                            ],
                                        ],
                                    ],
                                    'step_names' => [
                                        'verify page is open',
                                    ],
                                ],
                                'label' => 'Test/chrome-firefox-open-index.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-firefox-open-index.yml'
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-firefox-open-index.yml'
                                            . 'verify page is open',
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'test/started',
                                'body' => [
                                    'source' => 'Test/chrome-open-form.yml',
                                    'document' => [
                                        'type' => 'test',
                                        'payload' => [
                                            'path' => 'Test/chrome-open-form.yml',
                                            'config' => [
                                                'browser' => 'chrome',
                                                'url' => 'http://html-fixtures/form.html',
                                            ],
                                        ],
                                    ],
                                    'step_names' => [
                                        'verify page is open',
                                    ],
                                ],
                                'label' => 'Test/chrome-open-form.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-form.yml'
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-form.yml'
                                            . 'verify page is open',
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'step/passed',
                                'body' => [
                                    'source' => 'Test/chrome-open-form.yml',
                                    'document' => [
                                        'type' => 'step',
                                        'payload' => [
                                            'name' => 'verify page is open',
                                            'status' => 'passed',
                                            'statements' => [
                                                [
                                                    'type' => 'assertion',
                                                    'source' => '$page.url is "http://html-fixtures/form.html"',
                                                    'status' => 'passed',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'name' => 'verify page is open',
                                ],
                                'label' => 'verify page is open',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-form.yml'
                                    . 'verify page is open',
                                ),
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationExecuting,
                                    'compilation' => $completeComponent,
                                    'execution' => $runningComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationCompletingEventDelivery,
                                    'compilation' => $completeComponent,
                                    'execution' => $completeComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'test/passed',
                                'body' => [
                                    'source' => 'Test/chrome-open-form.yml',
                                    'document' => [
                                        'type' => 'test',
                                        'payload' => [
                                            'path' => 'Test/chrome-open-form.yml',
                                            'config' => [
                                                'browser' => 'chrome',
                                                'url' => 'http://html-fixtures/form.html',
                                            ],
                                        ],
                                    ],
                                    'step_names' => [
                                        'verify page is open',
                                    ],
                                ],
                                'label' => 'Test/chrome-open-form.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-form.yml'
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $workerJobLabel . 'Test/chrome-open-form.yml'
                                            . 'verify page is open',
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'lifecycle/execution-completed',
                                'body' => [],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => $firstSequenceNumber + 1,
                                'type' => 'job/ended',
                                'body' => [
                                    'end_state' => 'complete',
                                    'success' => true,
                                    'event_count' => 24,
                                ],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationCompletingEventDelivery,
                                    'compilation' => $completeComponent,
                                    'execution' => $completeComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationComplete,
                                    'compilation' => $completeComponent,
                                    'execution' => $completeComponent,
                                    'event_delivery' => $completeComponent,
                                ],
                            ],
                        ],
                    ];
                },
            ],
            'step failed' => [
                'manifestPaths' => [
                    'Test/chrome-open-index-with-step-failure.yml',
                ],
                'sourcePaths' => [
                    'Test/chrome-open-index-with-step-failure.yml',
                ],
                'jobLabel' => $jobLabel,
                'expectedApplicationState' => [
                    'state' => 'failed',
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => false,
                    ],
                    'previous_states' => [
                        'awaiting',
                        'compiling',
                        'executing',
                        'completing-event-delivery',
                        'failed',
                    ],
                ],
                'expectedCompilationState' => [
                    'state' => 'complete',
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => true,
                    ],
                    'previous_states' => [
                        'awaiting',
                        'running',
                        'complete',
                    ],
                ],
                'expectedExecutionState' => [
                    'state' => 'cancelled',
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => false,
                    ],
                    'previous_states' => [
                        'awaiting',
                        'running',
                        'cancelled',
                    ],
                ],
                'expectedTestDataCollection' => [
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-open-index-with-step-failure.yml',
                        'step_names' => ['verify page is open', 'fail on intentionally-missing element'],
                        'state' => 'failed',
                        'position' => 1,
                    ],
                ],
                'expectedDispatchedNotificationsCount' => 17,
                'expectedRequestsCreator' => function (
                    int $firstSequenceNumber,
                    string $workerJobLabel
                ) use (
                    $awaitingComponent,
                    $runningComponent,
                    $completeComponent,
                    $cancelledComponent,
                    $applicationCompiling,
                    $applicationExecuting,
                    $applicationFailed,
                ) {
                    return [
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $awaitingComponent,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $awaitingComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $awaitingComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $awaitingComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => $firstSequenceNumber,
                                'type' => 'job/started',
                                'body' => [
                                    'tests' => [
                                        'Test/chrome-open-index-with-step-failure.yml',
                                    ],
                                ],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                                'related_references' => [
                                    [
                                        'label' => 'Test/chrome-open-index-with-step-failure.yml',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-index-with-step-failure.yml',
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'lifecycle/compilation-started',
                                'body' => [],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'compilation/started',
                                'body' => [
                                    'source' => 'Test/chrome-open-index-with-step-failure.yml',
                                ],
                                'label' => 'Test/chrome-open-index-with-step-failure.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index-with-step-failure.yml',
                                ),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'compilation/passed',
                                'body' => [
                                    'source' => 'Test/chrome-open-index-with-step-failure.yml',
                                ],
                                'label' => 'Test/chrome-open-index-with-step-failure.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index-with-step-failure.yml',
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-index-with-step-failure.yml'
                                            . 'verify page is open',
                                        ),
                                    ],
                                    [
                                        'label' => 'fail on intentionally-missing element',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-index-with-step-failure.yml'
                                            . 'fail on intentionally-missing element',
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationCompiling,
                                    'compilation' => $runningComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationExecuting,
                                    'compilation' => $completeComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'lifecycle/compilation-completed',
                                'body' => [],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'lifecycle/execution-started',
                                'body' => [],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'test/started',
                                'body' => [
                                    'source' => 'Test/chrome-open-index-with-step-failure.yml',
                                    'document' => [
                                        'type' => 'test',
                                        'payload' => [
                                            'path' => 'Test/chrome-open-index-with-step-failure.yml',
                                            'config' => [
                                                'browser' => 'chrome',
                                                'url' => 'http://html-fixtures/index.html',
                                            ],
                                        ],
                                    ],
                                    'step_names' => [
                                        'verify page is open',
                                        'fail on intentionally-missing element',
                                    ],
                                ],
                                'label' => 'Test/chrome-open-index-with-step-failure.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index-with-step-failure.yml'
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-index-with-step-failure.yml'
                                            . 'verify page is open',
                                        ),
                                    ],
                                    [
                                        'label' => 'fail on intentionally-missing element',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-index-with-step-failure.yml'
                                            . 'fail on intentionally-missing element',
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationExecuting,
                                    'compilation' => $completeComponent,
                                    'execution' => $awaitingComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationExecuting,
                                    'compilation' => $completeComponent,
                                    'execution' => $runningComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'step/passed',
                                'body' => [
                                    'source' => 'Test/chrome-open-index-with-step-failure.yml',
                                    'document' => [
                                        'type' => 'step',
                                        'payload' => [
                                            'name' => 'verify page is open',
                                            'status' => 'passed',
                                            'statements' => [
                                                [
                                                    'type' => 'assertion',
                                                    'status' => 'passed',
                                                    'source' => '$page.url is "http://html-fixtures/index.html"',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'name' => 'verify page is open',
                                ],
                                'label' => 'verify page is open',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index-with-step-failure.yml'
                                    . 'verify page is open',
                                ),
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'step/failed',
                                'body' => [
                                    'source' => 'Test/chrome-open-index-with-step-failure.yml',
                                    'document' => [
                                        'type' => 'step',
                                        'payload' => [
                                            'name' => 'fail on intentionally-missing element',
                                            'status' => 'failed',
                                            'statements' => [
                                                [
                                                    'type' => 'assertion',
                                                    'status' => 'failed',
                                                    'source' => '$".non-existent" exists',
                                                    'summary' => [
                                                        'operator' => 'exists',
                                                        'source' => [
                                                            'type' => 'node',
                                                            'body' => [
                                                                'type' => 'element',
                                                                'identifier' => [
                                                                    'source' => '$".non-existent"',
                                                                    'properties' => [
                                                                        'type' => 'css',
                                                                        'locator' => '.non-existent',
                                                                        'position' => 1,
                                                                    ],
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'name' => 'fail on intentionally-missing element',
                                ],
                                'label' => 'fail on intentionally-missing element',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index-with-step-failure.yml'
                                    . 'fail on intentionally-missing element',
                                ),
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationExecuting,
                                    'compilation' => $completeComponent,
                                    'execution' => $runningComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationFailed,
                                    'compilation' => $completeComponent,
                                    'execution' => $cancelledComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => ++$firstSequenceNumber,
                                'type' => 'test/failed',
                                'body' => [
                                    'source' => 'Test/chrome-open-index-with-step-failure.yml',
                                    'document' => [
                                        'type' => 'test',
                                        'payload' => [
                                            'path' => 'Test/chrome-open-index-with-step-failure.yml',
                                            'config' => [
                                                'browser' => 'chrome',
                                                'url' => 'http://html-fixtures/index.html',
                                            ],
                                        ],
                                    ],
                                    'step_names' => [
                                        'verify page is open',
                                        'fail on intentionally-missing element',
                                    ],
                                ],
                                'label' => 'Test/chrome-open-index-with-step-failure.yml',
                                'reference' => md5(
                                    $workerJobLabel
                                    . 'Test/chrome-open-index-with-step-failure.yml'
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-index-with-step-failure.yml'
                                            . 'verify page is open',
                                        ),
                                    ],
                                    [
                                        'label' => 'fail on intentionally-missing element',
                                        'reference' => md5(
                                            $workerJobLabel
                                            . 'Test/chrome-open-index-with-step-failure.yml'
                                            . 'fail on intentionally-missing element',
                                        ),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'uri' => '/',
                            'payload' => [
                                'job' => $workerJobLabel,
                                'sequence_number' => $firstSequenceNumber + 1,
                                'type' => 'job/ended',
                                'body' => [
                                    'end_state' => 'failed/test/failure',
                                    'success' => false,
                                    'event_count' => 11,
                                ],
                                'label' => $workerJobLabel,
                                'reference' => md5($workerJobLabel),
                            ],
                        ],
                        [
                            'uri' => '/worker.application.state_changed',
                            'payload' => [
                                'previous_state' => [
                                    'application' => $applicationFailed,
                                    'compilation' => $completeComponent,
                                    'execution' => $cancelledComponent,
                                    'event_delivery' => $runningComponent,
                                ],
                                'new_state' => [
                                    'application' => $applicationFailed,
                                    'compilation' => $completeComponent,
                                    'execution' => $cancelledComponent,
                                    'event_delivery' => $completeComponent,
                                ],
                            ],
                        ],
                    ];
                },
            ],
        ];
    }

    private static function isApplicationComplete(): bool
    {
        $state = self::fetchApplicationState();

        return true === self::isComponentEndState($state, 'application');
    }

    /**
     * @param array<mixed>     $data
     * @param non-empty-string $key
     */
    private static function isComponentEndState(array $data, string $key): bool
    {
        $state = $data[$key] ?? [];
        $state = is_array($state) ? $state : [];

        $metaState = $state['meta_state'] ?? [];
        $metaState = is_array($metaState) ? $metaState : [];

        $metaStateEnded = $metaState['ended'] ?? false;

        return is_bool($metaStateEnded) && $metaStateEnded;
    }
}
