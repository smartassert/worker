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
use App\Tests\Integration\AbstractBaseIntegrationTestCase;
use App\Tests\Services\Asserter\JsonResponseAsserter;
use App\Tests\Services\ClientRequestSender;
use App\Tests\Services\CreateJobSourceFactory;
use App\Tests\Services\Integration\HttpLogReader;
use App\Tests\Services\IntegrationDeliverEventRequestFactory;
use Psr\Http\Message\RequestInterface;
use SebastianBergmann\Timer\Timer;
use webignition\HttpHistoryContainer\Collection\RequestCollection;
use webignition\HttpHistoryContainer\Collection\RequestCollectionInterface;

class CreateCompileExecuteTest extends AbstractBaseIntegrationTestCase
{
    private const MAX_DURATION_IN_SECONDS = 30;
    private const RESULTS_TOKEN = '01GWA71YQVHWF5FYFE16VWTMP3';

    private ClientRequestSender $clientRequestSender;
    private JsonResponseAsserter $jsonResponseAsserter;
    private CreateJobSourceFactory $createJobSourceFactory;
    private ApplicationProgress $applicationProgress;
    private WorkerEventRepository $workerEventRepository;
    private string $eventDeliveryBaseUrl;

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

        $eventDeliveryBaseUrl = self::getContainer()->getParameter('event_delivery_base_url');
        \assert(is_string($eventDeliveryBaseUrl));
        $this->eventDeliveryBaseUrl = $eventDeliveryBaseUrl;
    }

    /**
     * @dataProvider createAddSourcesCompileExecuteDataProvider
     *
     * @param non-empty-string[]       $manifestPaths
     * @param string[]                 $sourcePaths
     * @param array<int, array<mixed>> $expectedTestDataCollection
     */
    public function testCreateCompileExecute(
        array $manifestPaths,
        array $sourcePaths,
        string $jobLabel,
        string $eventDeliveryUrlPath,
        int $jobMaximumDurationInSeconds,
        CompilationState $expectedCompilationEndState,
        ExecutionState $expectedExecutionEndState,
        array $expectedTestDataCollection,
        callable $assertions
    ): void {
        $jobStatusResponse = $this->clientRequestSender->getJobStatus();
        $this->jsonResponseAsserter->assertJsonResponse(400, [], $jobStatusResponse);

        $eventDeliveryUrl = $this->eventDeliveryBaseUrl . self::RESULTS_TOKEN;

        $requestPayload = [
            CreateJobRequest::KEY_LABEL => $jobLabel,
            CreateJobRequest::KEY_EVENT_DELIVERY_URL => $eventDeliveryUrl,
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
        self::assertSame($eventDeliveryUrl, $jobStatusData['event_delivery_url']);
        self::assertSame($jobMaximumDurationInSeconds, $jobStatusData['maximum_duration_in_seconds']);
        self::assertSame($sourcePaths, $jobStatusData['sources']);
        self::assertArrayHasKey('event_ids', $jobStatusData);

        $applicationStateResponse = $this->clientRequestSender->getApplicationState();
        self::assertSame(200, $applicationStateResponse->getStatusCode());
        self::assertSame('application/json', $applicationStateResponse->headers->get('content-type'));

        $applicationStateData = json_decode((string) $applicationStateResponse->getContent(), true);
        self::assertIsArray($applicationStateData);
        self::assertSame($expectedCompilationEndState->value, $applicationStateData['compilation']);
        self::assertSame($expectedExecutionEndState->value, $applicationStateData['execution']);
        self::assertSame(EventDeliveryState::COMPLETE->value, $applicationStateData['event_delivery']);

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

        $httpLogReader = self::getContainer()->get(HttpLogReader::class);
        $deliverEventRequestFactory = self::getContainer()->get(IntegrationDeliverEventRequestFactory::class);
        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);

        $assertions($httpLogReader, $deliverEventRequestFactory, $workerEventRepository, $this->eventDeliveryBaseUrl);
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
                'eventDeliveryUrlPath' => '/status/200',
                'jobMaximumDurationInSeconds' => 60,
                'expectedCompilationEndState' => CompilationState::FAILED,
                'expectedExecutionEndState' => ExecutionState::AWAITING,
                'expectedTestDataCollection' => [],
                'assertions' => function (
                    HttpLogReader $httpLogReader,
                    IntegrationDeliverEventRequestFactory $requestFactory,
                    WorkerEventRepository $workerEventRepository,
                    string $eventDeliveryBaseUrl,
                ) use (
                    $jobLabel,
                ) {
                    $firstEvent = $workerEventRepository->findOneBy(
                        [
                            'scope' => WorkerEventScope::JOB->value,
                            'outcome' => WorkerEventOutcome::STARTED->value,
                        ],
                        ['id' => 'ASC']
                    );
                    \assert($firstEvent instanceof WorkerEvent);
                    $firstEventId = (int) $firstEvent->getId();

                    $expectedHttpRequests = new RequestCollection([
                        'job/started' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => $firstEventId,
                                'type' => 'job/started',
                                'body' => [
                                    'tests' => [
                                        'Test/chrome-open-index-compilation-failure.yml',
                                    ],
                                ],
                                'label' => $jobLabel,
                                'reference' => md5($jobLabel),
                                'related_references' => [
                                    [
                                        'label' => 'Test/chrome-open-index-compilation-failure.yml',
                                        'reference' => md5(
                                            $jobLabel .
                                            'Test/chrome-open-index-compilation-failure.yml'
                                        ),
                                    ],
                                ],
                            ],
                        ),
                        'job/compilation/started' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'job/compilation/started',
                                'body' => [],
                                'label' => $jobLabel,
                                'reference' => md5($jobLabel),
                            ],
                        ),
                        'source-compilation/started: chrome-open-index-compilation-failure' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'source-compilation/started',
                                'body' => [
                                    'source' => 'Test/chrome-open-index-compilation-failure.yml',
                                ],
                                'label' => 'Test/chrome-open-index-compilation-failure.yml',
                                'reference' => md5($jobLabel . 'Test/chrome-open-index-compilation-failure.yml'),
                            ],
                        ),
                        'source-compilation/failed: chrome-open-index-compilation-failure' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'source-compilation/failed',
                                'body' => [
                                    'source' => 'Test/chrome-open-index-compilation-failure.yml',
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
                                'label' => 'Test/chrome-open-index-compilation-failure.yml',
                                'reference' => md5($jobLabel . 'Test/chrome-open-index-compilation-failure.yml'),
                            ],
                        ),
                        'job/ended' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'job/ended',
                                'body' => [
                                    'end_state' => 'failed/compilation',
                                    'success' => false,
                                    'event_count' => 5,
                                ],
                                'label' => $jobLabel,
                                'reference' => md5($jobLabel),
                            ],
                        ),
                    ]);

                    $transactions = $httpLogReader->getTransactions();
                    $transactions = $transactions->slice(-1 * $expectedHttpRequests->count(), null);
                    $requests = $transactions->getRequests();

                    self::assertCount(count($expectedHttpRequests), $requests);
                    $this->assertRequestCollectionsAreEquivalent($expectedHttpRequests, $requests);
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
                'eventDeliveryUrlPath' => '/status/200',
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
                    IntegrationDeliverEventRequestFactory $requestFactory,
                    WorkerEventRepository $workerEventRepository,
                    string $eventDeliveryBaseUrl,
                ) use (
                    $jobLabel,
                ) {
                    $firstEvent = $workerEventRepository->findOneBy(
                        [
                            'scope' => WorkerEventScope::JOB->value,
                            'outcome' => WorkerEventOutcome::STARTED->value,
                        ],
                        ['id' => 'ASC']
                    );
                    \assert($firstEvent instanceof WorkerEvent);
                    $firstEventId = (int) $firstEvent->getId();

                    $expectedHttpRequests = new RequestCollection([
                        'job/started' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => $firstEventId,
                                'type' => 'job/started',
                                'body' => [
                                    'tests' => [
                                        'Test/chrome-open-index.yml',
                                        'Test/chrome-open-index-compilation-failure.yml',
                                    ],
                                ],
                                'label' => $jobLabel,
                                'reference' => md5($jobLabel),
                                'related_references' => [
                                    [
                                        'label' => 'Test/chrome-open-index.yml',
                                        'reference' => md5($jobLabel . 'Test/chrome-open-index.yml'),
                                    ],
                                    [
                                        'label' => 'Test/chrome-open-index-compilation-failure.yml',
                                        'reference' => md5(
                                            $jobLabel . 'Test/chrome-open-index-compilation-failure.yml'
                                        ),
                                    ],
                                ],
                            ],
                        ),
                        'job/compilation/started' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'job/compilation/started',
                                'body' => [],
                                'label' => $jobLabel,
                                'reference' => md5($jobLabel),
                            ],
                        ),
                        'source-compilation/started: chrome-open-index' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'source-compilation/started',
                                'body' => [
                                    'source' => 'Test/chrome-open-index.yml',
                                ],
                                'label' => 'Test/chrome-open-index.yml',
                                'reference' => md5($jobLabel . 'Test/chrome-open-index.yml'),
                            ],
                        ),
                        'source-compilation/passed: chrome-open-index' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'source-compilation/passed',
                                'body' => [
                                    'source' => 'Test/chrome-open-index.yml',
                                ],
                                'label' => 'Test/chrome-open-index.yml',
                                'reference' => md5($jobLabel . 'Test/chrome-open-index.yml'),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobLabel .
                                            'Test/chrome-open-index.yml' .
                                            'verify page is open'
                                        )
                                    ],
                                ],
                            ],
                        ),
                        'source-compilation/started: chrome-open-index-compilation-failure' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'source-compilation/started',
                                'body' => [
                                    'source' => 'Test/chrome-open-index-compilation-failure.yml',
                                ],
                                'label' => 'Test/chrome-open-index-compilation-failure.yml',
                                'reference' => md5($jobLabel . 'Test/chrome-open-index-compilation-failure.yml'),
                            ],
                        ),
                        'source-compilation/failed: chrome-open-index-compilation-failure' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'source-compilation/failed',
                                'body' => [
                                    'source' => 'Test/chrome-open-index-compilation-failure.yml',
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
                                'label' => 'Test/chrome-open-index-compilation-failure.yml',
                                'reference' => md5($jobLabel . 'Test/chrome-open-index-compilation-failure.yml'),
                            ],
                        ),
                        'job/ended' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'job/ended',
                                'body' => [
                                    'end_state' => 'failed/compilation',
                                    'success' => false,
                                    'event_count' => 7,
                                ],
                                'label' => $jobLabel,
                                'reference' => md5($jobLabel),
                            ],
                        ),
                    ]);

                    $transactions = $httpLogReader->getTransactions();
                    $transactions = $transactions->slice(-1 * $expectedHttpRequests->count(), null);
                    $requests = $transactions->getRequests();

                    self::assertCount(count($expectedHttpRequests), $requests);
                    $this->assertRequestCollectionsAreEquivalent($expectedHttpRequests, $requests);
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
                'eventDeliveryUrlPath' => '/status/200',
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
                    IntegrationDeliverEventRequestFactory $requestFactory,
                    WorkerEventRepository $workerEventRepository,
                    string $eventDeliveryBaseUrl,
                ) use (
                    $jobLabel,
                ) {
                    $firstEvent = $workerEventRepository->findOneBy(
                        [
                            'scope' => WorkerEventScope::JOB->value,
                            'outcome' => WorkerEventOutcome::STARTED->value,
                        ],
                        ['id' => 'ASC']
                    );
                    \assert($firstEvent instanceof WorkerEvent);
                    $firstEventId = (int) $firstEvent->getId();

                    $expectedHttpRequests = new RequestCollection([
                        'job/started' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => $firstEventId,
                                'type' => 'job/started',
                                'body' => [
                                    'tests' => [
                                        'Test/chrome-open-index.yml',
                                        'Test/chrome-firefox-open-index.yml',
                                        'Test/chrome-open-form.yml',
                                    ],
                                ],
                                'label' => $jobLabel,
                                'reference' => md5($jobLabel),
                                'related_references' => [
                                    [
                                        'label' => 'Test/chrome-open-index.yml',
                                        'reference' => md5($jobLabel . 'Test/chrome-open-index.yml'),
                                    ],
                                    [
                                        'label' => 'Test/chrome-firefox-open-index.yml',
                                        'reference' => md5(
                                            $jobLabel . 'Test/chrome-firefox-open-index.yml'
                                        ),
                                    ],
                                    [
                                        'label' => 'Test/chrome-open-form.yml',
                                        'reference' => md5($jobLabel . 'Test/chrome-open-form.yml'),
                                    ],
                                ],
                            ],
                        ),
                        'job/compilation/started' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'job/compilation/started',
                                'body' => [],
                                'label' => $jobLabel,
                                'reference' => md5($jobLabel),
                            ],
                        ),
                        'source-compilation/started: chrome-open-index' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'source-compilation/started',
                                'body' => [
                                    'source' => 'Test/chrome-open-index.yml',
                                ],
                                'label' => 'Test/chrome-open-index.yml',
                                'reference' => md5($jobLabel . 'Test/chrome-open-index.yml'),
                            ],
                        ),
                        'source-compilation/passed: chrome-open-index' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'source-compilation/passed',
                                'body' => [
                                    'source' => 'Test/chrome-open-index.yml',
                                ],
                                'label' => 'Test/chrome-open-index.yml',
                                'reference' => md5($jobLabel . 'Test/chrome-open-index.yml'),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobLabel .
                                            'Test/chrome-open-index.yml' .
                                            'verify page is open'
                                        )
                                    ],
                                ],
                            ],
                        ),
                        'source-compilation/started: chrome-firefox-open-index' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'source-compilation/started',
                                'body' => [
                                    'source' => 'Test/chrome-firefox-open-index.yml',
                                ],
                                'label' => 'Test/chrome-firefox-open-index.yml',
                                'reference' => md5($jobLabel . 'Test/chrome-firefox-open-index.yml'),
                            ],
                        ),
                        'source-compilation/passed: chrome-firefox-open-index' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'source-compilation/passed',
                                'body' => [
                                    'source' => 'Test/chrome-firefox-open-index.yml',
                                ],
                                'label' => 'Test/chrome-firefox-open-index.yml',
                                'reference' => md5($jobLabel . 'Test/chrome-firefox-open-index.yml'),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobLabel .
                                            'Test/chrome-firefox-open-index.yml' .
                                            'verify page is open'
                                        )
                                    ],
                                ],
                            ],
                        ),
                        'source-compilation/started: chrome-open-form' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'source-compilation/started',
                                'body' => [
                                    'source' => 'Test/chrome-open-form.yml',
                                ],
                                'label' => 'Test/chrome-open-form.yml',
                                'reference' => md5($jobLabel . 'Test/chrome-open-form.yml'),
                            ],
                        ),
                        'source-compilation/passed: chrome-open-form' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'source-compilation/passed',
                                'body' => [
                                    'source' => 'Test/chrome-open-form.yml',
                                ],
                                'label' => 'Test/chrome-open-form.yml',
                                'reference' => md5($jobLabel . 'Test/chrome-open-form.yml'),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobLabel .
                                            'Test/chrome-open-form.yml' .
                                            'verify page is open'
                                        )
                                    ],
                                ],
                            ],
                        ),
                        'job/compilation/ended' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'job/compilation/ended',
                                'body' => [],
                                'label' => $jobLabel,
                                'reference' => md5($jobLabel),
                            ],
                        ),
                        'job/execution/started' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'job/execution/started',
                                'body' => [],
                                'label' => $jobLabel,
                                'reference' => md5($jobLabel),
                            ],
                        ),
                        'test/started: chrome-open-index' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
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
                                'reference' => md5($jobLabel . 'Test/chrome-open-index.yml'),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobLabel .
                                            'Test/chrome-open-index.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ],
                        ),
                        'step/passed: chrome-open-index: open' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
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
                                                            'source' => '$page.url is $index.url'
                                                        ]
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'name' => 'verify page is open',
                                ],
                                'label' => 'verify page is open',
                                'reference' => md5(
                                    $jobLabel . 'Test/chrome-open-index.yml' . 'verify page is open'
                                ),
                            ],
                        ),
                        'test/passed: chrome-open-index' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
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
                                'reference' => md5($jobLabel . 'Test/chrome-open-index.yml'),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobLabel .
                                            'Test/chrome-open-index.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ],
                        ),
                        'test/started: chrome-firefox-open-index: chrome' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
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
                                'reference' => md5($jobLabel . 'Test/chrome-firefox-open-index.yml'),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobLabel .
                                            'Test/chrome-firefox-open-index.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ],
                        ),
                        'step/passed: chrome-firefox-open-index: chrome, open' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
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
                                    $jobLabel . 'Test/chrome-firefox-open-index.yml' . 'verify page is open'
                                ),
                            ],
                        ),
                        'test/passed: chrome-firefox-open-index: chrome' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
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
                                'reference' => md5($jobLabel . 'Test/chrome-firefox-open-index.yml'),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobLabel .
                                            'Test/chrome-firefox-open-index.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ],
                        ),
                        'test/started: chrome-firefox-open-index: firefox' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
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
                                'reference' => md5($jobLabel . 'Test/chrome-firefox-open-index.yml'),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobLabel .
                                            'Test/chrome-firefox-open-index.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ],
                        ),
                        'step/passed: chrome-firefox-open-index: firefox open' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
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
                                    $jobLabel . 'Test/chrome-firefox-open-index.yml' . 'verify page is open'
                                ),
                            ],
                        ),
                        'test/passed: chrome-firefox-open-index: firefox' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
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
                                'reference' => md5($jobLabel . 'Test/chrome-firefox-open-index.yml'),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobLabel .
                                            'Test/chrome-firefox-open-index.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ],
                        ),
                        'test/started: chrome-open-form' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
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
                                'reference' => md5($jobLabel . 'Test/chrome-open-form.yml'),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobLabel .
                                            'Test/chrome-open-form.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ],
                        ),
                        'step/passed: chrome-open-form: open' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
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
                                    $jobLabel . 'Test/chrome-open-form.yml' . 'verify page is open'
                                ),
                            ],
                        ),
                        'test/passed: chrome-open-form' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
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
                                'reference' => md5($jobLabel . 'Test/chrome-open-form.yml'),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobLabel .
                                            'Test/chrome-open-form.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                ],
                            ],
                        ),
                        'job/execution/completed' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'job/execution/completed',
                                'body' => [],
                                'label' => $jobLabel,
                                'reference' => md5($jobLabel),
                            ],
                        ),
                        'job/ended' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'job/ended',
                                'body' => [
                                    'end_state' => 'complete',
                                    'success' => true,
                                    'event_count' => 24,
                                ],
                                'label' => $jobLabel,
                                'reference' => md5($jobLabel),
                            ],
                        ),
                    ]);

                    $transactions = $httpLogReader->getTransactions();
                    $transactions = $transactions->slice(-1 * $expectedHttpRequests->count(), null);
                    $requests = $transactions->getRequests();

                    self::assertCount(count($expectedHttpRequests), $requests);
                    $this->assertRequestCollectionsAreEquivalent($expectedHttpRequests, $requests);
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
                'eventDeliveryUrlPath' => '/status/200',
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
                    IntegrationDeliverEventRequestFactory $requestFactory,
                    WorkerEventRepository $workerEventRepository,
                    string $eventDeliveryBaseUrl,
                ) use (
                    $jobLabel,
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
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => $firstEventId,
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
                                ],
                                'label' => 'fail on intentionally-missing element',
                                'reference' => md5(
                                    $jobLabel .
                                    'Test/chrome-open-index-with-step-failure.yml' .
                                    'fail on intentionally-missing element'
                                ),
                            ],
                        ),
                        'test/failed' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
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
                                    $jobLabel .
                                    'Test/chrome-open-index-with-step-failure.yml'
                                ),
                                'related_references' => [
                                    [
                                        'label' => 'verify page is open',
                                        'reference' => md5(
                                            $jobLabel .
                                            'Test/chrome-open-index-with-step-failure.yml' .
                                            'verify page is open'
                                        ),
                                    ],
                                    [
                                        'label' => 'fail on intentionally-missing element',
                                        'reference' => md5(
                                            $jobLabel .
                                            'Test/chrome-open-index-with-step-failure.yml' .
                                            'fail on intentionally-missing element'
                                        ),
                                    ],
                                ],
                            ],
                        ),
                        'job/ended' => $requestFactory->create(
                            $eventDeliveryBaseUrl . self::RESULTS_TOKEN,
                            [
                                'sequence_number' => ++$firstEventId,
                                'type' => 'job/ended',
                                'body' => [
                                    'end_state' => 'failed/test/failure',
                                    'success' => false,
                                    'event_count' => 11,
                                ],
                                'label' => $jobLabel,
                                'reference' => md5($jobLabel),
                            ],
                        ),
                    ]);

                    $transactions = $transactions->slice(-1 * $expectedHttpRequests->count(), null);
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
