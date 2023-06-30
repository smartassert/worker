<?php

declare(strict_types=1);

namespace App\Tests\Integration\EndToEnd;

use App\Enum\ApplicationState;
use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Repository\WorkerEventRepository;
use App\Request\CreateJobRequest;
use App\Services\ApplicationProgress;
use App\Tests\Integration\AbstractBaseIntegrationTestCase;
use App\Tests\Services\Asserter\JsonResponseAsserter;
use App\Tests\Services\ClientRequestSender;
use App\Tests\Services\CreateJobSourceFactory;
use SebastianBergmann\Timer\Timer;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ResultsClient\Model\Event;
use SmartAssert\ResultsClient\Model\EventInterface;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\ResultsClient\Model\ResourceReference;
use SmartAssert\ResultsClient\Model\ResourceReferenceCollection;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Component\Uid\Ulid;

class CreateCompileExecuteTest extends AbstractBaseIntegrationTestCase
{
    private const MAX_DURATION_IN_SECONDS = 30;

    private ClientRequestSender $clientRequestSender;
    private JsonResponseAsserter $jsonResponseAsserter;
    private CreateJobSourceFactory $createJobSourceFactory;
    private ApplicationProgress $applicationProgress;
    private WorkerEventRepository $workerEventRepository;
    private ResultsJob $resultsJob;

    /**
     * @var non-empty-string
     */
    private string $apiToken;

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

        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $this->apiToken = $apiTokenProvider->get('user@example.com');

        $resultsClient = self::getContainer()->get(ResultsClient::class);
        \assert($resultsClient instanceof ResultsClient);

        $jobLabel = (string) new Ulid();
        \assert('' !== $jobLabel);
        $this->resultsJob = $resultsClient->createJob($this->apiToken, $jobLabel);
    }

    /**
     * @dataProvider createAddSourcesCompileExecuteDataProvider
     *
     * @param non-empty-string[]                              $manifestPaths
     * @param string[]                                        $sourcePaths
     * @param array{state: non-empty-string, is_end_state: bool} $expectedCompilationEndState
     * @param array{state: non-empty-string, is_end_state: bool} $expectedExecutionEndState
     * @param array<int, array<mixed>>                        $expectedTestDataCollection
     * @param callable(int, string, string): EventInterface[] $expectedEventsCreator
     */
    public function testCreateCompileExecute(
        array $manifestPaths,
        array $sourcePaths,
        string $jobLabel,
        array $expectedCompilationEndState,
        array $expectedExecutionEndState,
        array $expectedTestDataCollection,
        callable $expectedEventsCreator,
    ): void {
        $jobMaximumDurationInSeconds = 60;

        $jobStatusResponse = $this->clientRequestSender->getJobStatus();
        $this->jsonResponseAsserter->assertJsonResponse(400, [], $jobStatusResponse);

        $requestPayload = [
            CreateJobRequest::KEY_LABEL => $jobLabel,
            CreateJobRequest::KEY_RESULTS_TOKEN => $this->resultsJob->token,
            CreateJobRequest::KEY_MAXIMUM_DURATION => $jobMaximumDurationInSeconds,
            CreateJobRequest::KEY_SOURCE => $this->createJobSourceFactory->create($manifestPaths, $sourcePaths),
        ];

        $timer = new Timer();
        $timer->start();

        $createResponse = $this->clientRequestSender->createJob($requestPayload);

        $duration = $timer->stop();
        self::assertLessThanOrEqual(self::MAX_DURATION_IN_SECONDS, $duration->asSeconds());

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
                'is_end_state' => true,
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
            self::assertMatchesRegularExpression('/^Generated.{32}Test\.php$/', $testData['target']);
        }

        self::assertSame(ApplicationState::COMPLETE, $this->applicationProgress->get());

        $resultsClient = self::getContainer()->get(ResultsClient::class);
        \assert($resultsClient instanceof ResultsClient);

        $resultsJobLabel = $this->resultsJob->label;
        \assert('' !== $resultsJobLabel);

        $events = $resultsClient->listEvents($this->apiToken, $resultsJobLabel, null, null);
        $firstEvent = $events[0];
        \assert($firstEvent instanceof Event);

        $expectedEvents = $expectedEventsCreator($firstEvent->sequenceNumber, $jobLabel, $resultsJobLabel);

        self::assertEquals(array_values($expectedEvents), $events);
    }

    /**
     * @return array<mixed>
     */
    public function createAddSourcesCompileExecuteDataProvider(): array
    {
        $jobLabel = md5((string) rand());

        return [
            'compilation failed on first test' => [
                'manifestPaths' => [
                    'Test/chrome-open-index-compilation-failure.yml',
                ],
                'sourcePaths' => [
                    'Test/chrome-open-index-compilation-failure.yml',
                ],
                'jobLabel' => $jobLabel,
                'expectedCompilationEndState' => [
                    'state' => CompilationState::FAILED->value,
                    'is_end_state' => true,
                ],
                'expectedExecutionEndState' => [
                    'state' => ExecutionState::AWAITING->value,
                    'is_end_state' => false,
                ],
                'expectedTestDataCollection' => [],
                'expectedEventsCreator' => function (
                    int $firstSequenceNumber,
                    string $workerJobLabel,
                    string $resultsJobLabel,
                ) {
                    \assert('' !== $resultsJobLabel);
                    \assert($firstSequenceNumber >= 1 && $firstSequenceNumber <= PHP_INT_MAX);
                    \assert('' !== $workerJobLabel);

                    $failedTestPath = 'Test/chrome-open-index-compilation-failure.yml';
                    $jobReference = new ResourceReference($workerJobLabel, md5($workerJobLabel));
                    $sourceReference = new ResourceReference($failedTestPath, md5($workerJobLabel . $failedTestPath));

                    return [
                        'job/started' => (new Event(
                            $firstSequenceNumber,
                            'job/started',
                            $jobReference,
                            [
                                'tests' => [$failedTestPath],
                            ],
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(new ResourceReferenceCollection([$sourceReference])),
                        'job/compilation/started' => (new Event(
                            ++$firstSequenceNumber,
                            'job/compilation/started',
                            $jobReference,
                            []
                        ))->withJob($resultsJobLabel),
                        'source-compilation/started: chrome-open-index-compilation-failure' => (new Event(
                            ++$firstSequenceNumber,
                            'source-compilation/started',
                            $sourceReference,
                            [
                                'source' => $failedTestPath,
                            ]
                        ))->withJob($resultsJobLabel),
                        'source-compilation/failed: chrome-open-index-compilation-failure' => (new Event(
                            ++$firstSequenceNumber,
                            'source-compilation/failed',
                            $sourceReference,
                            [
                                'output' => [
                                    'message' => 'Invalid test at path "' .
                                        $failedTestPath .
                                        '": test-step-invalid',
                                    'code' => 204,
                                    'context' => [
                                        'test_path' => $failedTestPath,
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
                                'source' => $failedTestPath,
                            ]
                        ))->withJob($resultsJobLabel),
                        'job/ended' => (new Event(
                            ++$firstSequenceNumber,
                            'job/ended',
                            $jobReference,
                            [
                                'end_state' => 'failed/compilation',
                                'success' => false,
                                'event_count' => 5,
                            ]
                        ))->withJob($resultsJobLabel),
                    ];
                },
            ],
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
                'expectedCompilationEndState' => [
                    'state' => CompilationState::FAILED->value,
                    'is_end_state' => true,
                ],
                'expectedExecutionEndState' => [
                    'state' => ExecutionState::AWAITING->value,
                    'is_end_state' => false,
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
                'expectedEventsCreator' => function (
                    int $firstSequenceNumber,
                    string $workerJobLabel,
                    string $resultsJobLabel,
                ) {
                    \assert('' !== $resultsJobLabel);
                    \assert($firstSequenceNumber >= 1 && $firstSequenceNumber <= PHP_INT_MAX);
                    \assert('' !== $workerJobLabel);

                    $successfulTestPath = 'Test/chrome-open-index.yml';
                    $failedTestPath = 'Test/chrome-open-index-compilation-failure.yml';

                    $jobReference = new ResourceReference($workerJobLabel, md5($workerJobLabel));
                    $successfulSourceReference = new ResourceReference(
                        $successfulTestPath,
                        md5($workerJobLabel . $successfulTestPath)
                    );
                    $failedSourceReference = new ResourceReference(
                        $failedTestPath,
                        md5($workerJobLabel . $failedTestPath)
                    );

                    return [
                        'job/started' => (new Event(
                            $firstSequenceNumber,
                            'job/started',
                            $jobReference,
                            [
                                'tests' => [$successfulTestPath, $failedTestPath],
                            ]
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([$failedSourceReference, $successfulSourceReference])
                            ),
                        'job/compilation/started' => (new Event(
                            ++$firstSequenceNumber,
                            'job/compilation/started',
                            $jobReference,
                            []
                        ))->withJob($resultsJobLabel),
                        'source-compilation/started:' . $successfulTestPath => (new Event(
                            ++$firstSequenceNumber,
                            'source-compilation/started',
                            $successfulSourceReference,
                            [
                                'source' => $successfulTestPath,
                            ]
                        ))->withJob($resultsJobLabel),
                        'source-compilation/passed:' . $successfulTestPath => (new Event(
                            ++$firstSequenceNumber,
                            'source-compilation/passed',
                            $successfulSourceReference,
                            [
                                'source' => $successfulTestPath,
                            ],
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([
                                    new ResourceReference(
                                        'verify page is open',
                                        md5($workerJobLabel . $successfulTestPath . 'verify page is open')
                                    ),
                                ])
                            ),
                        'source-compilation/started:' . $failedTestPath => (new Event(
                            ++$firstSequenceNumber,
                            'source-compilation/started',
                            $failedSourceReference,
                            [
                                'source' => $failedTestPath,
                            ]
                        ))->withJob($resultsJobLabel),
                        'source-compilation/failed' => (new Event(
                            ++$firstSequenceNumber,
                            'source-compilation/failed',
                            $failedSourceReference,
                            [
                                'source' => $failedTestPath,
                                'output' => [
                                    'message' => 'Invalid test at path ' .
                                        '"Test/chrome-open-index-compilation-failure.yml"' .
                                        ': test-step-invalid',
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
                        ))->withJob($resultsJobLabel),
                        'job/ended' => (new Event(
                            ++$firstSequenceNumber,
                            'job/ended',
                            $jobReference,
                            [
                                'end_state' => 'failed/compilation',
                                'success' => false,
                                'event_count' => 7,
                            ]
                        ))->withJob($resultsJobLabel),
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
                'expectedCompilationEndState' => [
                    'state' => CompilationState::COMPLETE->value,
                    'is_end_state' => true,
                ],
                'expectedExecutionEndState' => [
                    'state' => ExecutionState::COMPLETE->value,
                    'is_end_state' => true,
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
                'expectedEventsCreator' => function (
                    int $firstSequenceNumber,
                    string $workerJobLabel,
                    string $resultsJobLabel,
                ) {
                    \assert('' !== $resultsJobLabel);
                    \assert($firstSequenceNumber >= 1 && $firstSequenceNumber <= PHP_INT_MAX);
                    \assert('' !== $workerJobLabel);

                    $jobReference = new ResourceReference($workerJobLabel, md5($workerJobLabel));

                    $sourcePaths = [
                        'Test/chrome-open-index.yml',
                        'Test/chrome-firefox-open-index.yml',
                        'Test/chrome-open-form.yml',
                    ];

                    $sourceReferences = [
                        new ResourceReference($sourcePaths[0], md5($workerJobLabel . $sourcePaths[0])),
                        new ResourceReference($sourcePaths[1], md5($workerJobLabel . $sourcePaths[1])),
                        new ResourceReference($sourcePaths[2], md5($workerJobLabel . $sourcePaths[2])),
                    ];

                    return [
                        'job/started' => (new Event(
                            $firstSequenceNumber,
                            'job/started',
                            $jobReference,
                            [
                                'tests' => $sourcePaths,
                            ],
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(new ResourceReferenceCollection($sourceReferences)),
                        'job/compilation/started' => (new Event(
                            ++$firstSequenceNumber,
                            'job/compilation/started',
                            $jobReference,
                            []
                        ))->withJob($resultsJobLabel),
                        'source-compilation/started:' . $sourcePaths[0] => (new Event(
                            ++$firstSequenceNumber,
                            'source-compilation/started',
                            $sourceReferences[0],
                            [
                                'source' => $sourcePaths[0],
                            ]
                        ))->withJob($resultsJobLabel),
                        'source-compilation/passed:' . $sourcePaths[0] => (new Event(
                            ++$firstSequenceNumber,
                            'source-compilation/passed',
                            $sourceReferences[0],
                            [
                                'source' => $sourcePaths[0],
                            ],
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([
                                    new ResourceReference(
                                        'verify page is open',
                                        md5($workerJobLabel . $sourcePaths[0] . 'verify page is open')
                                    ),
                                ])
                            ),
                        'source-compilation/started:' . $sourcePaths[1] => (new Event(
                            ++$firstSequenceNumber,
                            'source-compilation/started',
                            $sourceReferences[1],
                            [
                                'source' => $sourcePaths[1],
                            ]
                        ))->withJob($resultsJobLabel),
                        'source-compilation/passed:' . $sourcePaths[1] => (new Event(
                            ++$firstSequenceNumber,
                            'source-compilation/passed',
                            $sourceReferences[1],
                            [
                                'source' => $sourcePaths[1],
                            ],
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([
                                    new ResourceReference(
                                        'verify page is open',
                                        md5($workerJobLabel . $sourcePaths[1] . 'verify page is open')
                                    ),
                                ])
                            ),
                        'source-compilation/started:' . $sourcePaths[2] => (new Event(
                            ++$firstSequenceNumber,
                            'source-compilation/started',
                            $sourceReferences[2],
                            [
                                'source' => $sourcePaths[2],
                            ]
                        ))->withJob($resultsJobLabel),
                        'source-compilation/passed:' . $sourcePaths[2] => (new Event(
                            ++$firstSequenceNumber,
                            'source-compilation/passed',
                            $sourceReferences[2],
                            [
                                'source' => $sourcePaths[2],
                            ],
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([
                                    new ResourceReference(
                                        'verify page is open',
                                        md5($workerJobLabel . $sourcePaths[2] . 'verify page is open')
                                    ),
                                ])
                            ),
                        'job/compilation/ended' => (new Event(
                            ++$firstSequenceNumber,
                            'job/compilation/ended',
                            $jobReference,
                            []
                        ))->withJob($resultsJobLabel),
                        'job/execution/started' => (new Event(
                            ++$firstSequenceNumber,
                            'job/execution/started',
                            $jobReference,
                            []
                        ))->withJob($resultsJobLabel),
                        'test/started:' . $sourcePaths[0] => (new Event(
                            ++$firstSequenceNumber,
                            'test/started',
                            $sourceReferences[0],
                            [
                                'source' => $sourcePaths[0],
                                'document' => [
                                    'type' => 'test',
                                    'payload' => [
                                        'path' => $sourcePaths[0],
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
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([
                                    new ResourceReference(
                                        'verify page is open',
                                        md5(
                                            $workerJobLabel .
                                            $sourcePaths[0] .
                                            'verify page is open'
                                        )
                                    ),
                                ])
                            ),
                        'step/passed:' . $sourcePaths[0] . 'verify page is open' => (new Event(
                            ++$firstSequenceNumber,
                            'step/passed',
                            new ResourceReference(
                                'verify page is open',
                                md5(
                                    $workerJobLabel .
                                    $sourcePaths[0] .
                                    'verify page is open'
                                )
                            ),
                            [
                                'source' => $sourcePaths[0],
                                'name' => 'verify page is open',
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
                                                        'source' => '$page.url is $index.url'
                                                    ]
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ))->withJob($resultsJobLabel),
                        'test/passed:' . $sourcePaths[0] => (new Event(
                            ++$firstSequenceNumber,
                            'test/passed',
                            $sourceReferences[0],
                            [
                                'source' => $sourcePaths[0],
                                'document' => [
                                    'type' => 'test',
                                    'payload' => [
                                        'path' => $sourcePaths[0],
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
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([
                                    new ResourceReference(
                                        'verify page is open',
                                        md5(
                                            $workerJobLabel .
                                            $sourcePaths[0] .
                                            'verify page is open'
                                        )
                                    ),
                                ])
                            ),
                        'test/started:' . $sourcePaths[1] . ', chrome' => (new Event(
                            ++$firstSequenceNumber,
                            'test/started',
                            $sourceReferences[1],
                            [
                                'source' => $sourcePaths[1],
                                'document' => [
                                    'type' => 'test',
                                    'payload' => [
                                        'path' => $sourcePaths[1],
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
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([
                                    new ResourceReference(
                                        'verify page is open',
                                        md5(
                                            $workerJobLabel .
                                            $sourcePaths[1] .
                                            'verify page is open'
                                        )
                                    ),
                                ])
                            ),
                        'step/passed:' . $sourcePaths[1] . 'verify page is open, chrome' => (new Event(
                            ++$firstSequenceNumber,
                            'step/passed',
                            new ResourceReference(
                                'verify page is open',
                                md5(
                                    $workerJobLabel .
                                    $sourcePaths[1] .
                                    'verify page is open'
                                )
                            ),
                            [
                                'source' => $sourcePaths[1],
                                'name' => 'verify page is open',
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
                            ],
                        ))->withJob($resultsJobLabel),
                        'test/passed' . $sourcePaths[1] . ', chrome' => (new Event(
                            ++$firstSequenceNumber,
                            'test/passed',
                            $sourceReferences[1],
                            [
                                'source' => $sourcePaths[1],
                                'document' => [
                                    'type' => 'test',
                                    'payload' => [
                                        'path' => $sourcePaths[1],
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
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([
                                    new ResourceReference(
                                        'verify page is open',
                                        md5(
                                            $workerJobLabel .
                                            $sourcePaths[1] .
                                            'verify page is open'
                                        )
                                    ),
                                ])
                            ),
                        'test/started:' . $sourcePaths[1] . ', firefox' => (new Event(
                            ++$firstSequenceNumber,
                            'test/started',
                            $sourceReferences[1],
                            [
                                'source' => $sourcePaths[1],
                                'document' => [
                                    'type' => 'test',
                                    'payload' => [
                                        'path' => $sourcePaths[1],
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
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([
                                    new ResourceReference(
                                        'verify page is open',
                                        md5(
                                            $workerJobLabel .
                                            $sourcePaths[1] .
                                            'verify page is open'
                                        )
                                    ),
                                ])
                            ),
                        'step/passed:' . $sourcePaths[1] . 'verify page is open, firefox' => (new Event(
                            ++$firstSequenceNumber,
                            'step/passed',
                            new ResourceReference(
                                'verify page is open',
                                md5(
                                    $workerJobLabel .
                                    $sourcePaths[1] .
                                    'verify page is open'
                                )
                            ),
                            [
                                'source' => $sourcePaths[1],
                                'name' => 'verify page is open',
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
                            ],
                        ))->withJob($resultsJobLabel),
                        'test/passed' . $sourcePaths[1] . ', firefox' => (new Event(
                            ++$firstSequenceNumber,
                            'test/passed',
                            $sourceReferences[1],
                            [
                                'source' => $sourcePaths[1],
                                'document' => [
                                    'type' => 'test',
                                    'payload' => [
                                        'path' => $sourcePaths[1],
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
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([
                                    new ResourceReference(
                                        'verify page is open',
                                        md5(
                                            $workerJobLabel .
                                            $sourcePaths[1] .
                                            'verify page is open'
                                        )
                                    ),
                                ])
                            ),
                        'test/started:' . $sourcePaths[2] => (new Event(
                            ++$firstSequenceNumber,
                            'test/started',
                            $sourceReferences[2],
                            [
                                'source' => $sourcePaths[2],
                                'document' => [
                                    'type' => 'test',
                                    'payload' => [
                                        'path' => $sourcePaths[2],
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
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([
                                    new ResourceReference(
                                        'verify page is open',
                                        md5(
                                            $workerJobLabel .
                                            $sourcePaths[2] .
                                            'verify page is open'
                                        )
                                    ),
                                ])
                            ),
                        'step/passed:' . $sourcePaths[2] . 'verify page is open' => (new Event(
                            ++$firstSequenceNumber,
                            'step/passed',
                            new ResourceReference(
                                'verify page is open',
                                md5(
                                    $workerJobLabel .
                                    $sourcePaths[2] .
                                    'verify page is open'
                                )
                            ),
                            [
                                'source' => $sourcePaths[2],
                                'name' => 'verify page is open',
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
                            ],
                        ))->withJob($resultsJobLabel),
                        'test/passed' . $sourcePaths[2] => (new Event(
                            ++$firstSequenceNumber,
                            'test/passed',
                            $sourceReferences[2],
                            [
                                'source' => $sourcePaths[2],
                                'document' => [
                                    'type' => 'test',
                                    'payload' => [
                                        'path' => $sourcePaths[2],
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
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([
                                    new ResourceReference(
                                        'verify page is open',
                                        md5(
                                            $workerJobLabel .
                                            $sourcePaths[2] .
                                            'verify page is open'
                                        )
                                    ),
                                ])
                            ),
                        'job/execution/completed' => (new Event(
                            ++$firstSequenceNumber,
                            'job/execution/completed',
                            $jobReference,
                            []
                        ))->withJob($resultsJobLabel),
                        'job/ended' => (new Event(
                            ++$firstSequenceNumber,
                            'job/ended',
                            $jobReference,
                            [
                                'end_state' => 'complete',
                                'success' => true,
                                'event_count' => 24,
                            ]
                        ))->withJob($resultsJobLabel),
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
                'expectedCompilationEndState' => [
                    'state' => CompilationState::COMPLETE->value,
                    'is_end_state' => true,
                ],
                'expectedExecutionEndState' => [
                    'state' => ExecutionState::CANCELLED->value,
                    'is_end_state' => true,
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
                'expectedEventsCreator' => function (
                    int $firstSequenceNumber,
                    string $workerJobLabel,
                    string $resultsJobLabel,
                ) {
                    \assert('' !== $resultsJobLabel);
                    \assert($firstSequenceNumber >= 1 && $firstSequenceNumber <= PHP_INT_MAX);
                    \assert('' !== $workerJobLabel);

                    $jobReference = new ResourceReference($workerJobLabel, md5($workerJobLabel));
                    $sourcePath = 'Test/chrome-open-index-with-step-failure.yml';
                    $sourceReference = new ResourceReference($sourcePath, md5($workerJobLabel . $sourcePath));

                    return [
                        'job/started' => (new Event(
                            $firstSequenceNumber,
                            'job/started',
                            $jobReference,
                            [
                                'tests' => [$sourcePath],
                            ],
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(new ResourceReferenceCollection([$sourceReference])),
                        'job/compilation/started' => (new Event(
                            ++$firstSequenceNumber,
                            'job/compilation/started',
                            $jobReference,
                            []
                        ))->withJob($resultsJobLabel),
                        'source-compilation/started:' . $sourcePath => (new Event(
                            ++$firstSequenceNumber,
                            'source-compilation/started',
                            $sourceReference,
                            [
                                'source' => $sourcePath,
                            ]
                        ))->withJob($resultsJobLabel),
                        'source-compilation/passed:' . $sourcePath => (new Event(
                            ++$firstSequenceNumber,
                            'source-compilation/passed',
                            $sourceReference,
                            [
                                'source' => $sourcePath,
                            ]
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([
                                    new ResourceReference(
                                        'verify page is open',
                                        md5($workerJobLabel . $sourcePath . 'verify page is open')
                                    ),
                                    new ResourceReference(
                                        'fail on intentionally-missing element',
                                        md5($workerJobLabel . $sourcePath . 'fail on intentionally-missing element')
                                    ),
                                ])
                            ),
                        'job/compilation/ended' => (new Event(
                            ++$firstSequenceNumber,
                            'job/compilation/ended',
                            $jobReference,
                            []
                        ))->withJob($resultsJobLabel),
                        'job/execution/started' => (new Event(
                            ++$firstSequenceNumber,
                            'job/execution/started',
                            $jobReference,
                            []
                        ))->withJob($resultsJobLabel),
                        'test/started:' . $sourcePath => (new Event(
                            ++$firstSequenceNumber,
                            'test/started',
                            $sourceReference,
                            [
                                'source' => $sourcePath,
                                'document' => [
                                    'type' => 'test',
                                    'payload' => [
                                        'path' => $sourcePath,
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
                            ]
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([
                                    new ResourceReference(
                                        'verify page is open',
                                        md5(
                                            $workerJobLabel .
                                            $sourcePath .
                                            'verify page is open'
                                        )
                                    ),
                                    new ResourceReference(
                                        'fail on intentionally-missing element',
                                        md5(
                                            $workerJobLabel .
                                            $sourcePath .
                                            'fail on intentionally-missing element'
                                        )
                                    ),
                                ])
                            ),
                        'step/passed:' . $sourcePath . 'verify page is open' => (new Event(
                            ++$firstSequenceNumber,
                            'step/passed',
                            new ResourceReference(
                                'verify page is open',
                                md5(
                                    $workerJobLabel .
                                    $sourcePath .
                                    'verify page is open'
                                )
                            ),
                            [
                                'source' => $sourcePath,
                                'name' => 'verify page is open',
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
                            ],
                        ))->withJob($resultsJobLabel),
                        'step/failed:' . $sourcePath . 'fail on intentionally-missing element' => (new Event(
                            ++$firstSequenceNumber,
                            'step/failed',
                            new ResourceReference(
                                'fail on intentionally-missing element',
                                md5(
                                    $workerJobLabel .
                                    $sourcePath .
                                    'fail on intentionally-missing element'
                                )
                            ),
                            [
                                'source' => $sourcePath,
                                'document' => [
                                    'type' => 'step',
                                    'payload' => [
                                        'name' => 'fail on intentionally-missing element',
                                        'status' => 'failed',
                                        'statements' => [
                                            [
                                                'type' => 'assertion',
                                                'source' => '$".non-existent" exists',
                                                'status' => 'failed',
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
                            ]
                        ))->withJob($resultsJobLabel),
                        'test/failed' => (new Event(
                            ++$firstSequenceNumber,
                            'test/failed',
                            $sourceReference,
                            [
                                'source' => $sourcePath,
                                'document' => [
                                    'type' => 'test',
                                    'payload' => [
                                        'path' => $sourcePath,
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
                            ]
                        ))
                            ->withJob($resultsJobLabel)
                            ->withRelatedReferences(
                                new ResourceReferenceCollection([
                                    new ResourceReference(
                                        'verify page is open',
                                        md5(
                                            $workerJobLabel .
                                            $sourcePath .
                                            'verify page is open'
                                        )
                                    ),
                                    new ResourceReference(
                                        'fail on intentionally-missing element',
                                        md5(
                                            $workerJobLabel .
                                            $sourcePath .
                                            'fail on intentionally-missing element'
                                        )
                                    ),
                                ])
                            ),
                        'job/ended' => (new Event(
                            ++$firstSequenceNumber,
                            'job/ended',
                            $jobReference,
                            [
                                'end_state' => 'failed/test/failure',
                                'success' => false,
                                'event_count' => 11,
                            ]
                        ))->withJob($resultsJobLabel),
                    ];
                },
            ],
        ];
    }
}
