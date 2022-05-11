<?php

declare(strict_types=1);

namespace App\Tests\Integration\EndToEnd;

use App\Entity\Test;
use App\Entity\WorkerEventType;
use App\Request\CreateJobRequest;
use App\Services\ApplicationState;
use App\Services\CompilationState;
use App\Services\ExecutionState;
use App\Services\WorkerEventState;
use App\Tests\Integration\AbstractBaseIntegrationTest;
use App\Tests\Services\Asserter\JsonResponseAsserter;
use App\Tests\Services\CallableInvoker;
use App\Tests\Services\ClientRequestSender;
use App\Tests\Services\CreateJobSourceFactory;
use App\Tests\Services\Integration\HttpLogReader;
use App\Tests\Services\IntegrationDeliverEventRequestFactory;
use App\Tests\Services\IntegrationJobProperties;
use Psr\Http\Message\RequestInterface;
use SebastianBergmann\Timer\Timer;
use webignition\HttpHistoryContainer\Collection\RequestCollection;
use webignition\HttpHistoryContainer\Collection\RequestCollectionInterface;

class CreateCompileExecuteTest extends AbstractBaseIntegrationTest
{
    private const MAX_DURATION_IN_SECONDS = 30;

    private CallableInvoker $callableInvoker;
    private ClientRequestSender $clientRequestSender;
    private JsonResponseAsserter $jsonResponseAsserter;
    private IntegrationJobProperties $jobProperties;
    private CreateJobSourceFactory $createJobSourceFactory;
    private ApplicationState $applicationState;

    protected function setUp(): void
    {
        parent::setUp();

        $callableInvoker = self::getContainer()->get(CallableInvoker::class);
        \assert($callableInvoker instanceof CallableInvoker);
        $this->callableInvoker = $callableInvoker;

        $clientRequestSender = self::getContainer()->get(ClientRequestSender::class);
        \assert($clientRequestSender instanceof ClientRequestSender);
        $this->clientRequestSender = $clientRequestSender;

        $jsonResponseAsserter = self::getContainer()->get(JsonResponseAsserter::class);
        \assert($jsonResponseAsserter instanceof JsonResponseAsserter);
        $this->jsonResponseAsserter = $jsonResponseAsserter;

        $jobProperties = self::getContainer()->get(IntegrationJobProperties::class);
        \assert($jobProperties instanceof IntegrationJobProperties);
        $this->jobProperties = $jobProperties;

        $createJobSourceFactory = self::getContainer()->get(CreateJobSourceFactory::class);
        \assert($createJobSourceFactory instanceof CreateJobSourceFactory);
        $this->createJobSourceFactory = $createJobSourceFactory;

        $applicationState = self::getContainer()->get(ApplicationState::class);
        \assert($applicationState instanceof ApplicationState);
        $this->applicationState = $applicationState;
    }

    /**
     * @dataProvider createAddSourcesCompileExecuteDataProvider
     *
     * @param string[]                  $manifestPaths
     * @param string[]                  $sourcePaths
     * @param CompilationState::STATE_* $expectedCompilationEndState
     * @param ExecutionState::STATE_*   $expectedExecutionEndState
     * @param array<int, array<mixed>>  $expectedTestDataCollection
     */
    public function testCreateCompileExecute(
        array $manifestPaths,
        array $sourcePaths,
        int $jobMaximumDurationInSeconds,
        string $expectedCompilationEndState,
        string $expectedExecutionEndState,
        array $expectedTestDataCollection,
        ?callable $assertions = null
    ): void {
        $statusResponse = $this->clientRequestSender->getStatus();
        $this->jsonResponseAsserter->assertJsonResponse(400, [], $statusResponse);

        $label = $this->jobProperties->getLabel();
        $eventDeliveryUrl = $this->jobProperties->getEventDeliveryUrl();

        $requestPayload = [
            CreateJobRequest::KEY_LABEL => $label,
            CreateJobRequest::KEY_EVENT_DELIVERY_URL => $eventDeliveryUrl,
            CreateJobRequest::KEY_MAXIMUM_DURATION => $jobMaximumDurationInSeconds,
            CreateJobRequest::KEY_SOURCE => $this->createJobSourceFactory->create($manifestPaths, $sourcePaths),
        ];

        $timer = new Timer();
        $timer->start();

        $createResponse = $this->clientRequestSender->create($requestPayload);

        $duration = $timer->stop();
        self::assertLessThanOrEqual(self::MAX_DURATION_IN_SECONDS, $duration->asSeconds());

        $this->jsonResponseAsserter->assertJsonResponse(200, [], $createResponse);

        $statusResponse = $this->clientRequestSender->getStatus();

        self::assertSame(200, $statusResponse->getStatusCode());
        self::assertSame('application/json', $statusResponse->headers->get('content-type'));

        $statusData = json_decode((string) $statusResponse->getContent(), true);
        self::assertIsArray($statusData);

        self::assertSame($label, $statusData['label']);
        self::assertSame($eventDeliveryUrl, $statusData['event_delivery_url']);
        self::assertSame($jobMaximumDurationInSeconds, $statusData['maximum_duration_in_seconds']);
        self::assertSame($expectedCompilationEndState, $statusData['compilation_state']);
        self::assertSame($expectedExecutionEndState, $statusData['execution_state']);
        self::assertSame(WorkerEventState::STATE_COMPLETE, $statusData['event_delivery_state']);
        self::assertSame($sourcePaths, $statusData['sources']);

        $testDataCollection = $statusData['tests'];
        self::assertIsArray($testDataCollection);

        self::assertCount(count($expectedTestDataCollection), $testDataCollection);

        foreach ($testDataCollection as $index => $testData) {
            self::assertIsArray($testData);
            $expectedTestData = $expectedTestDataCollection[$index];

            self::assertSame($expectedTestData['configuration'], $testData['configuration']);
            self::assertSame($expectedTestData['source'], $testData['source']);
            self::assertSame($expectedTestData['step_count'], $testData['step_count']);
            self::assertSame($expectedTestData['state'], $testData['state']);
            self::assertSame($expectedTestData['position'], $testData['position']);
            self::assertMatchesRegularExpression('/^Generated.{32}Test\.php$/', $testData['target']);
        }

        self::assertSame(ApplicationState::STATE_COMPLETE, (string) $this->applicationState);

        if (is_callable($assertions)) {
            $this->callableInvoker->invoke($assertions);
        }
    }

    /**
     * @return array<mixed>
     */
    public function createAddSourcesCompileExecuteDataProvider(): array
    {
        return [
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
                'jobMaximumDurationInSeconds' => 60,
                'expectedCompilationEndState' => CompilationState::STATE_COMPLETE,
                'expectedExecutionEndState' => ExecutionState::STATE_COMPLETE,
                'expectedTestDataCollection' => [
                    [
                        'configuration' => [
                            'browser' => 'chrome',
                            'url' => 'http://html-fixtures/index.html',
                        ],
                        'source' => 'Test/chrome-open-index.yml',
                        'step_count' => 1,
                        'state' => Test::STATE_COMPLETE,
                        'position' => 1,
                    ],
                    [
                        'configuration' => [
                            'browser' => 'chrome',
                            'url' => 'http://html-fixtures/index.html',
                        ],
                        'source' => 'Test/chrome-firefox-open-index.yml',
                        'step_count' => 1,
                        'state' => Test::STATE_COMPLETE,
                        'position' => 2,
                    ],
                    [
                        'configuration' => [
                            'browser' => 'firefox',
                            'url' => 'http://html-fixtures/index.html',
                        ],
                        'source' => 'Test/chrome-firefox-open-index.yml',
                        'step_count' => 1,
                        'state' => Test::STATE_COMPLETE,
                        'position' => 3,
                    ],
                    [
                        'configuration' => [
                            'browser' => 'chrome',
                            'url' => 'http://html-fixtures/form.html',
                        ],
                        'source' => 'Test/chrome-open-form.yml',
                        'step_count' => 1,
                        'state' => Test::STATE_COMPLETE,
                        'position' => 4,
                    ],
                ],
                'assertions' => function (
                    HttpLogReader $httpLogReader,
                    IntegrationJobProperties $jobProperties,
                    IntegrationDeliverEventRequestFactory $requestFactory,
                ) {
                    $expectedHttpRequests = new RequestCollection([
                        'job/started' => $requestFactory->create(
                            WorkerEventType::JOB_STARTED,
                            md5($jobProperties->getLabel()),
                            []
                        ),
                        'compilation/started: chrome-open-index' => $requestFactory->create(
                            WorkerEventType::COMPILATION_STARTED,
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index.yml'),
                            [
                                'source' => 'Test/chrome-open-index.yml',
                            ]
                        ),
                        'compilation/passed: chrome-open-index' => $requestFactory->create(
                            WorkerEventType::COMPILATION_PASSED,
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index.yml'),
                            [
                                'source' => 'Test/chrome-open-index.yml',
                            ]
                        ),
                        'compilation/started: chrome-firefox-open-index' => $requestFactory->create(
                            WorkerEventType::COMPILATION_STARTED,
                            md5($jobProperties->getLabel() . 'Test/chrome-firefox-open-index.yml'),
                            [
                                'source' => 'Test/chrome-firefox-open-index.yml',
                            ]
                        ),
                        'compilation/passed: chrome-firefox-open-index' => $requestFactory->create(
                            WorkerEventType::COMPILATION_PASSED,
                            md5($jobProperties->getLabel() . 'Test/chrome-firefox-open-index.yml'),
                            [
                                'source' => 'Test/chrome-firefox-open-index.yml',
                            ]
                        ),
                        'compilation/started: chrome-open-form' => $requestFactory->create(
                            WorkerEventType::COMPILATION_STARTED,
                            md5($jobProperties->getLabel() . 'Test/chrome-open-form.yml'),
                            [
                                'source' => 'Test/chrome-open-form.yml',
                            ]
                        ),
                        'compilation/passed: chrome-open-form' => $requestFactory->create(
                            WorkerEventType::COMPILATION_PASSED,
                            md5($jobProperties->getLabel() . 'Test/chrome-open-form.yml'),
                            [
                                'source' => 'Test/chrome-open-form.yml',
                            ]
                        ),
                        'job/compiled' => $requestFactory->create(
                            WorkerEventType::JOB_COMPILED,
                            md5($jobProperties->getLabel()),
                            []
                        ),
                        'execution/started' => $requestFactory->create(
                            WorkerEventType::EXECUTION_STARTED,
                            md5($jobProperties->getLabel()),
                            []
                        ),
                        'test/started: chrome-open-index' => $requestFactory->create(
                            WorkerEventType::TEST_STARTED,
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index.yml'),
                            [
                                'type' => 'test',
                                'payload' => [
                                    'path' => 'Test/chrome-open-index.yml',
                                    'config' => [
                                        'browser' => 'chrome',
                                        'url' => 'http://html-fixtures/index.html',
                                    ],
                                ],
                            ]
                        ),
                        'step/passed: chrome-open-index: open' => $requestFactory->create(
                            WorkerEventType::STEP_PASSED,
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index.yml' . 'verify page is open'),
                            [
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
                            ]
                        ),
                        'test/passed: chrome-open-index' => $requestFactory->create(
                            WorkerEventType::TEST_PASSED,
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index.yml'),
                            [
                                'type' => 'test',
                                'payload' => [
                                    'path' => 'Test/chrome-open-index.yml',
                                    'config' => [
                                        'browser' => 'chrome',
                                        'url' => 'http://html-fixtures/index.html',
                                    ],
                                ],
                            ]
                        ),
                        'test/started: chrome-firefox-open-index: chrome' => $requestFactory->create(
                            WorkerEventType::TEST_STARTED,
                            md5($jobProperties->getLabel() . 'Test/chrome-firefox-open-index.yml'),
                            [
                                'type' => 'test',
                                'payload' => [
                                    'path' => 'Test/chrome-firefox-open-index.yml',
                                    'config' => [
                                        'browser' => 'chrome',
                                        'url' => 'http://html-fixtures/index.html',
                                    ],
                                ],
                            ]
                        ),
                        'step/passed: chrome-firefox-open-index: chrome, open' => $requestFactory->create(
                            WorkerEventType::STEP_PASSED,
                            md5(
                                $jobProperties->getLabel() .
                                'Test/chrome-firefox-open-index.yml' .
                                'verify page is open'
                            ),
                            [
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
                            ]
                        ),
                        'test/passed: chrome-firefox-open-index: chrome' => $requestFactory->create(
                            WorkerEventType::TEST_PASSED,
                            md5($jobProperties->getLabel() . 'Test/chrome-firefox-open-index.yml'),
                            [
                                'type' => 'test',
                                'payload' => [
                                    'path' => 'Test/chrome-firefox-open-index.yml',
                                    'config' => [
                                        'browser' => 'chrome',
                                        'url' => 'http://html-fixtures/index.html',
                                    ],
                                ],
                            ]
                        ),
                        'test/started: chrome-firefox-open-index: firefox' => $requestFactory->create(
                            WorkerEventType::TEST_STARTED,
                            md5($jobProperties->getLabel() . 'Test/chrome-firefox-open-index.yml'),
                            [
                                'type' => 'test',
                                'payload' => [
                                    'path' => 'Test/chrome-firefox-open-index.yml',
                                    'config' => [
                                        'browser' => 'firefox',
                                        'url' => 'http://html-fixtures/index.html',
                                    ],
                                ],
                            ]
                        ),
                        'step/passed: chrome-firefox-open-index: firefox open' => $requestFactory->create(
                            WorkerEventType::STEP_PASSED,
                            md5(
                                $jobProperties->getLabel() .
                                'Test/chrome-firefox-open-index.yml' .
                                'verify page is open'
                            ),
                            [
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
                            ]
                        ),
                        'test/passed: chrome-firefox-open-index: firefox' => $requestFactory->create(
                            WorkerEventType::TEST_PASSED,
                            md5($jobProperties->getLabel() . 'Test/chrome-firefox-open-index.yml'),
                            [
                                'type' => 'test',
                                'payload' => [
                                    'path' => 'Test/chrome-firefox-open-index.yml',
                                    'config' => [
                                        'browser' => 'firefox',
                                        'url' => 'http://html-fixtures/index.html',
                                    ],
                                ],
                            ]
                        ),
                        'test/started: chrome-open-form' => $requestFactory->create(
                            WorkerEventType::TEST_STARTED,
                            md5($jobProperties->getLabel() . 'Test/chrome-open-form.yml'),
                            [
                                'type' => 'test',
                                'payload' => [
                                    'path' => 'Test/chrome-open-form.yml',
                                    'config' => [
                                        'browser' => 'chrome',
                                        'url' => 'http://html-fixtures/form.html',
                                    ],
                                ],
                            ]
                        ),
                        'step/passed: chrome-open-form: open' => $requestFactory->create(
                            WorkerEventType::STEP_PASSED,
                            md5($jobProperties->getLabel() . 'Test/chrome-open-form.yml' . 'verify page is open'),
                            [
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
                            ]
                        ),
                        'test/passed: chrome-open-form' => $requestFactory->create(
                            WorkerEventType::TEST_PASSED,
                            md5($jobProperties->getLabel() . 'Test/chrome-open-form.yml'),
                            [
                                'type' => 'test',
                                'payload' => [
                                    'path' => 'Test/chrome-open-form.yml',
                                    'config' => [
                                        'browser' => 'chrome',
                                        'url' => 'http://html-fixtures/form.html',
                                    ],
                                ],
                            ]
                        ),
                        'execution/completed' => $requestFactory->create(
                            WorkerEventType::EXECUTION_COMPLETED,
                            md5($jobProperties->getLabel()),
                            []
                        ),
                        'job/completed' => $requestFactory->create(
                            WorkerEventType::JOB_COMPLETED,
                            md5($jobProperties->getLabel()),
                            []
                        ),
                    ]);

                    $transactions = $httpLogReader->getTransactions();
                    $httpLogReader->reset();

                    $this->assertRequestCollectionsAreEquivalent($expectedHttpRequests, $transactions->getRequests());
                },
            ],
            'step failed' => [
                'manifestPaths' => [
                    'Test/chrome-open-index-with-step-failure.yml',
                ],
                'sourcePaths' => [
                    'Test/chrome-open-index-with-step-failure.yml',
                ],
                'jobMaximumDurationInSeconds' => 60,
                'expectedCompilationEndState' => CompilationState::STATE_COMPLETE,
                'expectedExecutionEndState' => ExecutionState::STATE_CANCELLED,
                'expectedTestDataCollection' => [
                    [
                        'configuration' => [
                            'browser' => 'chrome',
                            'url' => 'http://html-fixtures/index.html',
                        ],
                        'source' => 'Test/chrome-open-index-with-step-failure.yml',
                        'step_count' => 2,
                        'state' => Test::STATE_FAILED,
                        'position' => 1,
                    ],
                ],
                'assertions' => function (
                    HttpLogReader $httpLogReader,
                    IntegrationJobProperties $jobProperties,
                    IntegrationDeliverEventRequestFactory $requestFactory,
                ) {
                    $transactions = $httpLogReader->getTransactions();
                    $httpLogReader->reset();

                    $expectedHttpRequests = new RequestCollection([
                        'step/failed' => $requestFactory->create(
                            WorkerEventType::STEP_FAILED,
                            md5(
                                $jobProperties->getLabel() .
                                'Test/chrome-open-index-with-step-failure.yml' .
                                'fail on intentionally-missing element'
                            ),
                            [
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
                            ]
                        ),
                        'test/failed' => $requestFactory->create(
                            WorkerEventType::TEST_FAILED,
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index-with-step-failure.yml'),
                            [
                                'type' => 'test',
                                'payload' => [
                                    'path' => 'Test/chrome-open-index-with-step-failure.yml',
                                    'config' => [
                                        'browser' => 'chrome',
                                        'url' => 'http://html-fixtures/index.html',
                                    ],
                                ],
                            ]
                        ),
                        'job/failed' => $requestFactory->create(
                            WorkerEventType::JOB_FAILED,
                            md5($jobProperties->getLabel()),
                            []
                        ),
                    ]);

                    $transactions = $transactions->slice(
                        (-1 * $expectedHttpRequests->count()),
                        null
                    );

                    $requests = $transactions->getRequests();

                    self::assertCount(count($expectedHttpRequests), $requests);
                    $this->assertRequestCollectionsAreEquivalent($expectedHttpRequests, $requests);
                },
            ],
        ];
    }

    private function assertRequestCollectionsAreEquivalent(
        RequestCollectionInterface $expectedRequests,
        RequestCollectionInterface $requests
    ): void {
        $requestsIterator = $requests->getIterator();

        foreach ($expectedRequests as $requestIndex => $expectedRequest) {
            $request = $requestsIterator->current();
            $requestsIterator->next();

            self::assertInstanceOf(RequestInterface::class, $request);
            $this->assertRequestsAreEquivalent($expectedRequest, $request, $requestIndex);
        }
    }

    private function assertRequestsAreEquivalent(
        RequestInterface $expected,
        RequestInterface $actual,
        int $requestIndex
    ): void {
        self::assertSame(
            $expected->getMethod(),
            $actual->getMethod(),
            'Method of request at index ' . $requestIndex . ' not as expected'
        );

        self::assertSame(
            (string) $expected->getUri(),
            (string) $actual->getUri(),
            'URL of request at index ' . $requestIndex . ' not as expected'
        );

        self::assertSame(
            $expected->getHeaderLine('content-type'),
            $actual->getHeaderLine('content-type'),
            'Content-type header of request at index ' . $requestIndex . ' not as expected'
        );

        self::assertSame(
            json_decode($expected->getBody()->getContents(), true),
            json_decode($actual->getBody()->getContents(), true),
            'Body of request at index ' . $requestIndex . ' not as expected'
        );
    }
}
