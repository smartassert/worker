<?php

declare(strict_types=1);

namespace App\Tests\Integration\EndToEnd;

use App\Entity\WorkerEvent;
use App\Enum\ApplicationState;
use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Repository\WorkerEventRepository;
use App\Request\CreateJobRequest;
use App\Services\ApplicationProgress;
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
    private ApplicationProgress $applicationProgress;

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

        $applicationProgress = self::getContainer()->get(ApplicationProgress::class);
        \assert($applicationProgress instanceof ApplicationProgress);
        $this->applicationProgress = $applicationProgress;
    }

    /**
     * @dataProvider createAddSourcesCompileExecuteDataProvider
     *
     * @param string[]                 $manifestPaths
     * @param string[]                 $sourcePaths
     * @param array<int, array<mixed>> $expectedTestDataCollection
     */
    public function testCreateCompileExecute(
        array $manifestPaths,
        array $sourcePaths,
        int $jobMaximumDurationInSeconds,
        CompilationState $expectedCompilationEndState,
        ExecutionState $expectedExecutionEndState,
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

        self::assertSame(200, $createResponse->getStatusCode());
        self::assertSame('application/json', $createResponse->headers->get('content-type'));

        $statusResponse = $this->clientRequestSender->getStatus();

        self::assertSame(200, $statusResponse->getStatusCode());
        self::assertSame('application/json', $statusResponse->headers->get('content-type'));

        $statusData = json_decode((string) $statusResponse->getContent(), true);
        self::assertIsArray($statusData);

        self::assertSame($label, $statusData['label']);
        self::assertSame($eventDeliveryUrl, $statusData['event_delivery_url']);
        self::assertSame($jobMaximumDurationInSeconds, $statusData['maximum_duration_in_seconds']);
        self::assertSame($expectedCompilationEndState->value, $statusData['compilation_state']);
        self::assertSame($expectedExecutionEndState->value, $statusData['execution_state']);
        self::assertSame(EventDeliveryState::COMPLETE->value, $statusData['event_delivery_state']);
        self::assertSame($sourcePaths, $statusData['sources']);

        $testDataCollection = $statusData['tests'];
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
            'compilation failed on first test' => [
                'manifestPaths' => [
                    'Test/chrome-open-index-compilation-failure.yml',
                ],
                'sourcePaths' => [
                    'Test/chrome-open-index-compilation-failure.yml',
                ],
                'jobMaximumDurationInSeconds' => 60,
                'expectedCompilationEndState' => CompilationState::FAILED,
                'expectedExecutionEndState' => ExecutionState::AWAITING,
                'expectedTestDataCollection' => [],
                'assertions' => function (
                    HttpLogReader $httpLogReader,
                    IntegrationJobProperties $jobProperties,
                    IntegrationDeliverEventRequestFactory $requestFactory,
                    WorkerEventRepository $workerEventRepository,
                ) {
                    $firstEvent = $workerEventRepository->findOneBy([], ['id' => 'ASC']);
                    \assert($firstEvent instanceof WorkerEvent);
                    $firstEventId = (int) $firstEvent->getId();

                    $expectedHttpRequests = new RequestCollection([
                        'job/started' => $requestFactory->create(
                            $firstEventId,
                            'job/started',
                            $jobProperties->getLabel(),
                            md5($jobProperties->getLabel()),
                            [
                                'tests' => [
                                    'Test/chrome-open-index-compilation-failure.yml',
                                ],
                                'related_references' => [
                                    [
                                        'label' => 'Test/chrome-open-index-compilation-failure.yml',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-open-index-compilation-failure.yml'
                                        ),
                                    ],
                                ],
                            ]
                        ),
                        'compilation/started: chrome-open-index-compilation-failure' => $requestFactory->create(
                            ++$firstEventId,
                            'compilation/started',
                            'Test/chrome-open-index-compilation-failure.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index-compilation-failure.yml'),
                            [
                                'source' => 'Test/chrome-open-index-compilation-failure.yml',
                            ]
                        ),
                        'compilation/failed: chrome-open-index-compilation-failure' => $requestFactory->create(
                            ++$firstEventId,
                            'compilation/failed',
                            'Test/chrome-open-index-compilation-failure.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index-compilation-failure.yml'),
                            [
                                'source' => 'Test/chrome-open-index-compilation-failure.yml',
                                'output' => [
                                    'message' => 'Invalid test at path ' .
                                        '"/app/source/Test/chrome-open-index-compilation-failure.yml"' .
                                        ': test-step-invalid',
                                    'code' => 204,
                                    'context' => [
                                        'test_path' => '/app/source/Test/chrome-open-index-compilation-failure.yml',
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
                            ]
                        ),
                    ]);

                    $transactions = $httpLogReader->getTransactions();
                    $httpLogReader->reset();

                    $this->assertRequestCollectionsAreEquivalent($expectedHttpRequests, $transactions->getRequests());
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
                'jobMaximumDurationInSeconds' => 60,
                'expectedCompilationEndState' => CompilationState::FAILED,
                'expectedExecutionEndState' => ExecutionState::AWAITING,
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
                'assertions' => function (
                    HttpLogReader $httpLogReader,
                    IntegrationJobProperties $jobProperties,
                    IntegrationDeliverEventRequestFactory $requestFactory,
                    WorkerEventRepository $workerEventRepository,
                ) {
                    $firstEvent = $workerEventRepository->findOneBy([], ['id' => 'ASC']);
                    \assert($firstEvent instanceof WorkerEvent);
                    $firstEventId = (int) $firstEvent->getId();

                    $expectedHttpRequests = new RequestCollection([
                        'job/started' => $requestFactory->create(
                            $firstEventId,
                            'job/started',
                            $jobProperties->getLabel(),
                            md5($jobProperties->getLabel()),
                            [
                                'tests' => [
                                    'Test/chrome-open-index.yml',
                                    'Test/chrome-open-index-compilation-failure.yml',
                                ],
                                'related_references' => [
                                    [
                                        'label' => 'Test/chrome-open-index.yml',
                                        'reference' => md5($jobProperties->getLabel() . 'Test/chrome-open-index.yml'),
                                    ],
                                    [
                                        'label' => 'Test/chrome-open-index-compilation-failure.yml',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-open-index-compilation-failure.yml'
                                        ),
                                    ],
                                ],
                            ]
                        ),
                        'compilation/started: chrome-open-index' => $requestFactory->create(
                            ++$firstEventId,
                            'compilation/started',
                            'Test/chrome-open-index.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index.yml'),
                            [
                                'source' => 'Test/chrome-open-index.yml',
                            ]
                        ),
                        'compilation/passed: chrome-open-index' => $requestFactory->create(
                            ++$firstEventId,
                            'compilation/passed',
                            'Test/chrome-open-index.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index.yml'),
                            [
                                'source' => 'Test/chrome-open-index.yml',
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-open-index.yml' .
                                            'verify page is open'
                                        )
                                    ],
                                ],
                            ]
                        ),
                        'compilation/started: chrome-open-index-compilation-failure' => $requestFactory->create(
                            ++$firstEventId,
                            'compilation/started',
                            'Test/chrome-open-index-compilation-failure.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index-compilation-failure.yml'),
                            [
                                'source' => 'Test/chrome-open-index-compilation-failure.yml',
                            ]
                        ),
                        'compilation/failed: chrome-open-index-compilation-failure' => $requestFactory->create(
                            ++$firstEventId,
                            'compilation/failed',
                            'Test/chrome-open-index-compilation-failure.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index-compilation-failure.yml'),
                            [
                                'source' => 'Test/chrome-open-index-compilation-failure.yml',
                                'output' => [
                                    'message' => 'Invalid test at path ' .
                                        '"/app/source/Test/chrome-open-index-compilation-failure.yml"' .
                                        ': test-step-invalid',
                                    'code' => 204,
                                    'context' => [
                                        'test_path' => '/app/source/Test/chrome-open-index-compilation-failure.yml',
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
                            ]
                        ),
                    ]);

                    $transactions = $httpLogReader->getTransactions();
                    $httpLogReader->reset();

                    $this->assertRequestCollectionsAreEquivalent($expectedHttpRequests, $transactions->getRequests());
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
                'jobMaximumDurationInSeconds' => 60,
                'expectedCompilationEndState' => CompilationState::COMPLETE,
                'expectedExecutionEndState' => ExecutionState::COMPLETE,
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
                'assertions' => function (
                    HttpLogReader $httpLogReader,
                    IntegrationJobProperties $jobProperties,
                    IntegrationDeliverEventRequestFactory $requestFactory,
                    WorkerEventRepository $workerEventRepository,
                ) {
                    $firstEvent = $workerEventRepository->findOneBy([], ['id' => 'ASC']);
                    \assert($firstEvent instanceof WorkerEvent);
                    $firstEventId = (int) $firstEvent->getId();

                    $expectedHttpRequests = new RequestCollection([
                        'job/started' => $requestFactory->create(
                            $firstEventId,
                            'job/started',
                            $jobProperties->getLabel(),
                            md5($jobProperties->getLabel()),
                            [
                                'tests' => [
                                    'Test/chrome-open-index.yml',
                                    'Test/chrome-firefox-open-index.yml',
                                    'Test/chrome-open-form.yml',
                                ],
                                'related_references' => [
                                    [
                                        'label' => 'Test/chrome-open-index.yml',
                                        'reference' => md5($jobProperties->getLabel() . 'Test/chrome-open-index.yml'),
                                    ],
                                    [
                                        'label' => 'Test/chrome-firefox-open-index.yml',
                                        'reference' => md5(
                                            $jobProperties->getLabel() . 'Test/chrome-firefox-open-index.yml'
                                        ),
                                    ],
                                    [
                                        'label' => 'Test/chrome-open-form.yml',
                                        'reference' => md5($jobProperties->getLabel() . 'Test/chrome-open-form.yml'),
                                    ],
                                ],
                            ]
                        ),
                        'compilation/started: chrome-open-index' => $requestFactory->create(
                            ++$firstEventId,
                            'compilation/started',
                            'Test/chrome-open-index.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index.yml'),
                            [
                                'source' => 'Test/chrome-open-index.yml',
                            ]
                        ),
                        'compilation/passed: chrome-open-index' => $requestFactory->create(
                            ++$firstEventId,
                            'compilation/passed',
                            'Test/chrome-open-index.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index.yml'),
                            [
                                'source' => 'Test/chrome-open-index.yml',
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-open-index.yml' .
                                            'verify page is open'
                                        )
                                    ],
                                ],
                            ]
                        ),
                        'compilation/started: chrome-firefox-open-index' => $requestFactory->create(
                            ++$firstEventId,
                            'compilation/started',
                            'Test/chrome-firefox-open-index.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-firefox-open-index.yml'),
                            [
                                'source' => 'Test/chrome-firefox-open-index.yml',
                            ]
                        ),
                        'compilation/passed: chrome-firefox-open-index' => $requestFactory->create(
                            ++$firstEventId,
                            'compilation/passed',
                            'Test/chrome-firefox-open-index.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-firefox-open-index.yml'),
                            [
                                'source' => 'Test/chrome-firefox-open-index.yml',
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-firefox-open-index.yml' .
                                            'verify page is open'
                                        )
                                    ],
                                ],
                            ]
                        ),
                        'compilation/started: chrome-open-form' => $requestFactory->create(
                            ++$firstEventId,
                            'compilation/started',
                            'Test/chrome-open-form.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-form.yml'),
                            [
                                'source' => 'Test/chrome-open-form.yml',
                            ]
                        ),
                        'compilation/passed: chrome-open-form' => $requestFactory->create(
                            ++$firstEventId,
                            'compilation/passed',
                            'Test/chrome-open-form.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-form.yml'),
                            [
                                'source' => 'Test/chrome-open-form.yml',
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-open-form.yml' .
                                            'verify page is open'
                                        )
                                    ],
                                ],
                            ]
                        ),
                        'job/compiled' => $requestFactory->create(
                            ++$firstEventId,
                            'job/compiled',
                            $jobProperties->getLabel(),
                            md5($jobProperties->getLabel()),
                            []
                        ),
                        'execution/started' => $requestFactory->create(
                            ++$firstEventId,
                            'execution/started',
                            $jobProperties->getLabel(),
                            md5($jobProperties->getLabel()),
                            []
                        ),
                        'test/started: chrome-open-index' => $requestFactory->create(
                            ++$firstEventId,
                            'test/started',
                            'Test/chrome-open-index.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index.yml'),
                            [
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
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-open-index.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ]
                        ),
                        'step/passed: chrome-open-index: open' => $requestFactory->create(
                            ++$firstEventId,
                            'step/passed',
                            'verify page is open',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index.yml' . 'verify page is open'),
                            [
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
                                                        'source' => '$page.url is $index.url'
                                                    ]
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'name' => 'verify page is open',
                            ]
                        ),
                        'test/passed: chrome-open-index' => $requestFactory->create(
                            ++$firstEventId,
                            'test/passed',
                            'Test/chrome-open-index.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index.yml'),
                            [
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
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-open-index.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ]
                        ),
                        'test/started: chrome-firefox-open-index: chrome' => $requestFactory->create(
                            ++$firstEventId,
                            'test/started',
                            'Test/chrome-firefox-open-index.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-firefox-open-index.yml'),
                            [
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
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-firefox-open-index.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ]
                        ),
                        'step/passed: chrome-firefox-open-index: chrome, open' => $requestFactory->create(
                            ++$firstEventId,
                            'step/passed',
                            'verify page is open',
                            md5(
                                $jobProperties->getLabel() .
                                'Test/chrome-firefox-open-index.yml' .
                                'verify page is open'
                            ),
                            [
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
                            ]
                        ),
                        'test/passed: chrome-firefox-open-index: chrome' => $requestFactory->create(
                            ++$firstEventId,
                            'test/passed',
                            'Test/chrome-firefox-open-index.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-firefox-open-index.yml'),
                            [
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
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-firefox-open-index.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ]
                        ),
                        'test/started: chrome-firefox-open-index: firefox' => $requestFactory->create(
                            ++$firstEventId,
                            'test/started',
                            'Test/chrome-firefox-open-index.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-firefox-open-index.yml'),
                            [
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
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-firefox-open-index.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ]
                        ),
                        'step/passed: chrome-firefox-open-index: firefox open' => $requestFactory->create(
                            ++$firstEventId,
                            'step/passed',
                            'verify page is open',
                            md5(
                                $jobProperties->getLabel() .
                                'Test/chrome-firefox-open-index.yml' .
                                'verify page is open'
                            ),
                            [
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
                            ]
                        ),
                        'test/passed: chrome-firefox-open-index: firefox' => $requestFactory->create(
                            ++$firstEventId,
                            'test/passed',
                            'Test/chrome-firefox-open-index.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-firefox-open-index.yml'),
                            [
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
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-firefox-open-index.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ]
                        ),
                        'test/started: chrome-open-form' => $requestFactory->create(
                            ++$firstEventId,
                            'test/started',
                            'Test/chrome-open-form.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-form.yml'),
                            [
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
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-open-form.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ]
                        ),
                        'step/passed: chrome-open-form: open' => $requestFactory->create(
                            ++$firstEventId,
                            'step/passed',
                            'verify page is open',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-form.yml' . 'verify page is open'),
                            [
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
                            ]
                        ),
                        'test/passed: chrome-open-form' => $requestFactory->create(
                            ++$firstEventId,
                            'test/passed',
                            'Test/chrome-open-form.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-form.yml'),
                            [
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
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-open-form.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ]
                        ),
                        'execution/completed' => $requestFactory->create(
                            ++$firstEventId,
                            'execution/completed',
                            $jobProperties->getLabel(),
                            md5($jobProperties->getLabel()),
                            []
                        ),
                        'job/completed' => $requestFactory->create(
                            ++$firstEventId,
                            'job/completed',
                            $jobProperties->getLabel(),
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
                'expectedCompilationEndState' => CompilationState::COMPLETE,
                'expectedExecutionEndState' => ExecutionState::CANCELLED,
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
                'assertions' => function (
                    HttpLogReader $httpLogReader,
                    IntegrationJobProperties $jobProperties,
                    IntegrationDeliverEventRequestFactory $requestFactory,
                    WorkerEventRepository $workerEventRepository,
                ) {
                    $firstEvent = $workerEventRepository->findOneBy(
                        [
                            'scope' => WorkerEventScope::STEP->value,
                            'outcome' => WorkerEventOutcome::FAILED->value,
                        ],
                        ['id' => 'ASC']
                    );
                    \assert($firstEvent instanceof WorkerEvent);
                    $firstEventId = (int) $firstEvent->getId();

                    $transactions = $httpLogReader->getTransactions();
                    $httpLogReader->reset();

                    $expectedHttpRequests = new RequestCollection([
                        'step/failed' => $requestFactory->create(
                            $firstEventId,
                            'step/failed',
                            'fail on intentionally-missing element',
                            md5(
                                $jobProperties->getLabel() .
                                'Test/chrome-open-index-with-step-failure.yml' .
                                'fail on intentionally-missing element'
                            ),
                            [
                                'source' => 'Test/chrome-open-index-with-step-failure.yml',
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
                        ),
                        'test/failed' => $requestFactory->create(
                            ++$firstEventId,
                            'test/failed',
                            'Test/chrome-open-index-with-step-failure.yml',
                            md5($jobProperties->getLabel() . 'Test/chrome-open-index-with-step-failure.yml'),
                            [
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
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-open-index-with-step-failure.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                    [
                                        'label' => 'fail on intentionally-missing element',
                                        'reference' => md5(
                                            $jobProperties->getLabel() .
                                            'Test/chrome-open-index-with-step-failure.yml' .
                                            'fail on intentionally-missing element'
                                        ),
                                    ],
                                ],
                            ]
                        ),
                        'job/failed' => $requestFactory->create(
                            ++$firstEventId,
                            'job/failed',
                            $jobProperties->getLabel(),
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
