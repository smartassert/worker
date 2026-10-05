<?php

declare(strict_types=1);

namespace App\Tests\Integration\EndToEnd;

use App\Entity\WorkerEvent;
use App\Enum\ApplicationState;
use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;
use App\Enum\StateInterface;
use App\Enum\TestState;
use App\Repository\WorkerEventRepository;
use App\Request\CreateJobRequest;
use App\Services\ApplicationProgress;
use App\Tests\Integration\AbstractBaseIntegrationTestCase;
use App\Tests\Services\Asserter\JsonResponseAsserter;
use App\Tests\Services\ClientRequestSender;
use App\Tests\Services\CreateJobSourceFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\CallbackReceiverLogReader\Parser;
use Symfony\Component\Process\Process;

class CreateCompileExecuteTest extends AbstractBaseIntegrationTestCase
{
    private const int MAX_DURATION_IN_SECONDS = 30;

    private ClientRequestSender $clientRequestSender;
    private JsonResponseAsserter $jsonResponseAsserter;
    private CreateJobSourceFactory $createJobSourceFactory;
    private ApplicationProgress $applicationProgress;
    private WorkerEventRepository $workerEventRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $clientRequestSender = self::getContainer()->get(ClientRequestSender::class);
        \assert($clientRequestSender instanceof ClientRequestSender);
        $this->clientRequestSender = $clientRequestSender;

        $jsonResponseAsserter = self::getContainer()->get(JsonResponseAsserter::class);
        \assert($jsonResponseAsserter instanceof JsonResponseAsserter);
        $this->jsonResponseAsserter = $jsonResponseAsserter;

        $createJobSourceFactory = self::getContainer()->get(CreateJobSourceFactory::class);
        \assert($createJobSourceFactory instanceof CreateJobSourceFactory);
        $this->createJobSourceFactory = $createJobSourceFactory;

        $applicationProgress = self::getContainer()->get(ApplicationProgress::class);
        \assert($applicationProgress instanceof ApplicationProgress);
        $this->applicationProgress = $applicationProgress;

        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($workerEventRepository instanceof WorkerEventRepository);
        $this->workerEventRepository = $workerEventRepository;
    }

    /**
     * @param non-empty-string[]                  $manifestPaths
     * @param string[]                            $sourcePaths
     * @param array{state: non-empty-string}      $expectedCompilationEndState
     * @param array{state: non-empty-string}      $expectedExecutionEndState
     * @param array<int, array<mixed>>            $expectedTestDataCollection
     * @param callable(int, string): array<mixed> $expectedRequestBodiesCreator
     */
    #[DataProvider('createAddSourcesCompileExecuteDataProvider')]
    public function testCreateCompileExecute(
        array $manifestPaths,
        array $sourcePaths,
        string $jobLabel,
        StateInterface $expectedApplicationState,
        array $expectedCompilationEndState,
        array $expectedExecutionEndState,
        array $expectedTestDataCollection,
        int $expectedDispatchedNotificationsCount,
        callable $expectedRequestBodiesCreator,
    ): void {
        $jobMaximumDurationInSeconds = 60;

        $jobStatusResponse = $this->clientRequestSender->getJobStatus();
        $this->jsonResponseAsserter->assertJsonResponse(400, [], $jobStatusResponse);

        $requestPayload = [
            CreateJobRequest::KEY_LABEL => $jobLabel,
            CreateJobRequest::KEY_EVENT_NOTIFY_URL => 'http://localhost:8080',
            CreateJobRequest::KEY_MAXIMUM_DURATION => $jobMaximumDurationInSeconds,
            CreateJobRequest::KEY_SOURCE => $this->createJobSourceFactory->create($manifestPaths, $sourcePaths),
        ];

        $timerStart = microtime(true);

        $createResponse = $this->clientRequestSender->createJob($requestPayload);

        $timerEnd = microtime(true);
        $duration = $timerEnd - $timerStart;

        self::assertLessThanOrEqual(self::MAX_DURATION_IN_SECONDS, $duration);

        self::assertSame(200, $createResponse->getStatusCode());
        self::assertSame('application/json', $createResponse->headers->get('content-type'));

        $createData = json_decode((string) $createResponse->getContent(), true);
        self::assertIsArray($createData);
        self::assertArrayHasKey('event_ids', $createData);
        $createEventIds = $createData['event_ids'];
        self::assertNotEmpty($createEventIds);
        self::assertSame($createEventIds, $this->workerEventRepository->findAllIds());

        $jobStatusResponse = $this->clientRequestSender->getJobStatus();
        self::assertSame(200, $jobStatusResponse->getStatusCode());
        self::assertSame('application/json', $jobStatusResponse->headers->get('content-type'));

        $jobStatusData = json_decode((string) $jobStatusResponse->getContent(), true);
        self::assertIsArray($jobStatusData);
        self::assertSame($jobLabel, $jobStatusData['label']);
        self::assertSame($jobMaximumDurationInSeconds, $jobStatusData['maximum_duration_in_seconds']);
        self::assertSame($sourcePaths, $jobStatusData['sources']);
        self::assertArrayHasKey('event_ids', $jobStatusData);

        $applicationStateResponse = $this->clientRequestSender->getApplicationState();
        self::assertSame(200, $applicationStateResponse->getStatusCode());
        self::assertSame('application/json', $applicationStateResponse->headers->get('content-type'));

        $applicationStateData = json_decode((string) $applicationStateResponse->getContent(), true);
        self::assertIsArray($applicationStateData);
        self::assertSame($expectedCompilationEndState, $applicationStateData['compilation']);
        self::assertSame($expectedExecutionEndState, $applicationStateData['execution']);
        self::assertSame(
            [
                'state' => EventDeliveryState::COMPLETE->value,
                'meta_state' => [
                    'pending' => false,
                    'ended' => true,
                    'succeeded' => true,
                ],
                'previous_states' => [
                    EventDeliveryState::AWAITING->value,
                    EventDeliveryState::RUNNING->value,
                    EventDeliveryState::COMPLETE->value,
                ],
            ],
            $applicationStateData['event_delivery']
        );

        $testDataCollection = $jobStatusData['tests'];
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

        self::assertSame($expectedApplicationState, $this->applicationProgress->get());

        $firstEvent = $this->workerEventRepository->findOneBy([], ['id' => 'ASC']);
        \assert($firstEvent instanceof WorkerEvent);

        $expectedRequestBodies = $expectedRequestBodiesCreator($firstEvent->getId(), $jobLabel);

        $process = Process::fromShellCommandline('docker logs callback-receiver');
        $process->run();

        $output = $process->getOutput();
        $parser = new Parser();

        $requests = $parser->parse($output, $expectedDispatchedNotificationsCount);
        self::assertCount($expectedDispatchedNotificationsCount, $requests);
        self::assertSame(count($requests), count($expectedRequestBodies));

        foreach ($expectedRequestBodies as $requestIndex => $expectedRequestBody) {
            $request = $requests[$requestIndex];

            self::assertSame('POST', $request->getMethod());
            self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
            self::assertEquals('/', (string) $request->getUri());

            $requestData = json_decode($request->getBody()->getContents(), true);
            self::assertIsArray($requestData);
            self::assertEquals($expectedRequestBody, $requestData);
        }
    }

    /**
     * @return array<mixed>
     */
    public static function createAddSourcesCompileExecuteDataProvider(): array
    {
        $jobLabel = md5((string) rand());

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
                'expectedApplicationState' => ApplicationState::FAILED,
                'expectedCompilationEndState' => [
                    'state' => CompilationState::FAILED->value,
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => false,
                    ],
                    'previous_states' => [
                        CompilationState::AWAITING->value,
                        CompilationState::RUNNING->value,
                        CompilationState::FAILED->value,
                    ],
                ],
                'expectedExecutionEndState' => [
                    'state' => ExecutionState::AWAITING->value,
                    'meta_state' => [
                        'pending' => true,
                        'ended' => false,
                        'succeeded' => false,
                    ],
                    'previous_states' => [
                        ExecutionState::AWAITING->value,
                    ],
                ],
                'expectedTestDataCollection' => [
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-open-index.yml',
                        'step_names' => ['verify page is open'],
                        'state' => TestState::AWAITING->value,
                        'position' => 1,
                    ],
                ],
                'expectedDispatchedNotificationsCount' => 7,
                'expectedRequestBodiesCreator' => function (int $firstSequenceNumber, string $workerJobLabel) {
                    return [
                        [
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
                        [
                            'job' => $workerJobLabel,
                            'sequence_number' => ++$firstSequenceNumber,
                            'type' => 'lifecycle/compilation-started',
                            'body' => [],
                            'label' => $workerJobLabel,
                            'reference' => md5($workerJobLabel),
                        ],
                        [
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
                        [
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
                        [
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
                        [
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
                        [
                            'job' => $workerJobLabel,
                            'sequence_number' => ++$firstSequenceNumber,
                            'type' => 'job/ended',
                            'body' => [
                                'end_state' => 'failed/compilation',
                                'success' => false,
                                'event_count' => 7,
                            ],
                            'label' => $workerJobLabel,
                            'reference' => md5($workerJobLabel),
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
                'expectedApplicationState' => ApplicationState::FAILED,
                'expectedCompilationEndState' => [
                    'state' => CompilationState::FAILED->value,
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => false,
                    ],
                    'previous_states' => [
                        CompilationState::AWAITING->value,
                        CompilationState::RUNNING->value,
                        CompilationState::FAILED->value,
                    ],
                ],
                'expectedExecutionEndState' => [
                    'state' => ExecutionState::AWAITING->value,
                    'meta_state' => [
                        'pending' => true,
                        'ended' => false,
                        'succeeded' => false,
                    ],
                    'previous_states' => [
                        ExecutionState::AWAITING->value,
                    ],
                ],
                'expectedTestDataCollection' => [],
                'expectedDispatchedNotificationsCount' => 5,
                'expectedRequestBodiesCreator' => function (int $firstSequenceNumber, string $workerJobLabel) {
                    return [
                        [
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
                        [
                            'job' => $workerJobLabel,
                            'sequence_number' => ++$firstSequenceNumber,
                            'type' => 'lifecycle/compilation-started',
                            'body' => [],
                            'label' => $workerJobLabel,
                            'reference' => md5($workerJobLabel),
                        ],
                        [
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
                        [
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
                        [
                            'job' => $workerJobLabel,
                            'sequence_number' => ++$firstSequenceNumber,
                            'type' => 'job/ended',
                            'body' => [
                                'end_state' => 'failed/compilation',
                                'success' => false,
                                'event_count' => 5,
                            ],
                            'label' => $workerJobLabel,
                            'reference' => md5($workerJobLabel),
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
                'expectedApplicationState' => ApplicationState::COMPLETE,
                'expectedCompilationEndState' => [
                    'state' => CompilationState::COMPLETE->value,
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => true,
                    ],
                    'previous_states' => [
                        CompilationState::AWAITING->value,
                        CompilationState::RUNNING->value,
                        CompilationState::COMPLETE->value,
                    ],
                ],
                'expectedExecutionEndState' => [
                    'state' => ExecutionState::COMPLETE->value,
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => true,
                    ],
                    'previous_states' => [
                        ExecutionState::AWAITING->value,
                        ExecutionState::RUNNING->value,
                        ExecutionState::COMPLETE->value,
                    ],
                ],
                'expectedTestDataCollection' => [
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-open-index.yml',
                        'step_names' => ['verify page is open'],
                        'state' => TestState::COMPLETE->value,
                        'position' => 1,
                    ],
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-firefox-open-index.yml',
                        'step_names' => ['verify page is open'],
                        'state' => TestState::COMPLETE->value,
                        'position' => 2,
                    ],
                    [
                        'browser' => 'firefox',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-firefox-open-index.yml',
                        'step_names' => ['verify page is open'],
                        'state' => TestState::COMPLETE->value,
                        'position' => 3,
                    ],
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/form.html',
                        'source' => 'Test/chrome-open-form.yml',
                        'step_names' => ['verify page is open'],
                        'state' => TestState::COMPLETE->value,
                        'position' => 4,
                    ],
                ],
                'expectedDispatchedNotificationsCount' => 24,
                'expectedRequestBodiesCreator' => function (int $firstSequenceNumber, string $workerJobLabel) {
                    return [
                        [
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
                        [
                            'job' => $workerJobLabel,
                            'sequence_number' => ++$firstSequenceNumber,
                            'type' => 'lifecycle/compilation-started',
                            'body' => [],
                            'label' => $workerJobLabel,
                            'reference' => md5($workerJobLabel),
                        ],
                        [
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
                        [
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
                        [
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
                        [
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
                        [
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
                        [
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
                        [
                            'job' => $workerJobLabel,
                            'sequence_number' => ++$firstSequenceNumber,
                            'type' => 'lifecycle/compilation-completed',
                            'body' => [],
                            'label' => $workerJobLabel,
                            'reference' => md5($workerJobLabel),
                        ],
                        [
                            'job' => $workerJobLabel,
                            'sequence_number' => ++$firstSequenceNumber,
                            'type' => 'lifecycle/execution-started',
                            'body' => [],
                            'label' => $workerJobLabel,
                            'reference' => md5($workerJobLabel),
                        ],
                        [
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
                        [
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
                        [
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
                        [
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
                        [
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
                        [
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
                        [
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
                        [
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
                        [
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
                        [
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
                        [
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
                        [
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
                        [
                            'job' => $workerJobLabel,
                            'sequence_number' => ++$firstSequenceNumber,
                            'type' => 'lifecycle/execution-completed',
                            'body' => [],
                            'label' => $workerJobLabel,
                            'reference' => md5($workerJobLabel),
                        ],
                        [
                            'job' => $workerJobLabel,
                            'sequence_number' => ++$firstSequenceNumber,
                            'type' => 'job/ended',
                            'body' => [
                                'end_state' => 'complete',
                                'success' => true,
                                'event_count' => 24,
                            ],
                            'label' => $workerJobLabel,
                            'reference' => md5($workerJobLabel),
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
                'expectedApplicationState' => ApplicationState::FAILED,
                'expectedCompilationEndState' => [
                    'state' => CompilationState::COMPLETE->value,
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => true,
                    ],
                    'previous_states' => [
                        CompilationState::AWAITING->value,
                        CompilationState::RUNNING->value,
                        CompilationState::COMPLETE->value,
                    ],
                ],
                'expectedExecutionEndState' => [
                    'state' => ExecutionState::CANCELLED->value,
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => false,
                    ],
                    'previous_states' => [
                        ExecutionState::AWAITING->value,
                        ExecutionState::RUNNING->value,
                        ExecutionState::CANCELLED->value,
                    ],
                ],
                'expectedTestDataCollection' => [
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-open-index-with-step-failure.yml',
                        'step_names' => ['verify page is open', 'fail on intentionally-missing element'],
                        'state' => TestState::FAILED->value,
                        'position' => 1,
                    ],
                ],
                'expectedDispatchedNotificationsCount' => 11,
                'expectedRequestBodiesCreator' => function (int $firstSequenceNumber, string $workerJobLabel) {
                    return [
                        [
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
                        [
                            'job' => $workerJobLabel,
                            'sequence_number' => ++$firstSequenceNumber,
                            'type' => 'lifecycle/compilation-started',
                            'body' => [],
                            'label' => $workerJobLabel,
                            'reference' => md5($workerJobLabel),
                        ],
                        [
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
                        [
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
                        [
                            'job' => $workerJobLabel,
                            'sequence_number' => ++$firstSequenceNumber,
                            'type' => 'lifecycle/compilation-completed',
                            'body' => [],
                            'label' => $workerJobLabel,
                            'reference' => md5($workerJobLabel),
                        ],
                        [
                            'job' => $workerJobLabel,
                            'sequence_number' => ++$firstSequenceNumber,
                            'type' => 'lifecycle/execution-started',
                            'body' => [],
                            'label' => $workerJobLabel,
                            'reference' => md5($workerJobLabel),
                        ],
                        [
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
                        [
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
                        [
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
                        [
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
                        [
                            'job' => $workerJobLabel,
                            'sequence_number' => ++$firstSequenceNumber,
                            'type' => 'job/ended',
                            'body' => [
                                'end_state' => 'failed/test/failure',
                                'success' => false,
                                'event_count' => 11,
                            ],
                            'label' => $workerJobLabel,
                            'reference' => md5($workerJobLabel),
                        ],
                    ];
                },
            ],
        ];
    }
}
