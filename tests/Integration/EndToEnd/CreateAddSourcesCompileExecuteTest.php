<?php

declare(strict_types=1);

namespace App\Tests\Integration\EndToEnd;

use App\Entity\Callback\CallbackInterface;
use App\Message\JobReadyMessage;
use App\Services\ApplicationState;
use App\Services\CallbackState;
use App\Services\CompilationState;
use App\Services\ExecutionState;
use App\Tests\Integration\AbstractBaseIntegrationTest;
use App\Tests\Services\ApplicationStateHandler;
use App\Tests\Services\Asserter\JsonResponseAsserter;
use App\Tests\Services\Asserter\SystemStateAsserter;
use App\Tests\Services\CallableInvoker;
use App\Tests\Services\ClientRequestSender;
use App\Tests\Services\Integration\HttpLogReader;
use App\Tests\Services\IntegrationCallbackRequestFactory;
use App\Tests\Services\IntegrationJobProperties;
use App\Tests\Services\UploadedFileFactory;
use Psr\Http\Message\RequestInterface;
use SebastianBergmann\Timer\Timer;
use Symfony\Component\Messenger\MessageBusInterface;
use webignition\HttpHistoryContainer\Collection\RequestCollection;
use webignition\HttpHistoryContainer\Collection\RequestCollectionInterface;

class CreateAddSourcesCompileExecuteTest extends AbstractBaseIntegrationTest
{
    private const MAX_DURATION_IN_SECONDS = 30;

    private CallableInvoker $callableInvoker;
    private ClientRequestSender $clientRequestSender;
    private JsonResponseAsserter $jsonResponseAsserter;
    private SystemStateAsserter $systemStateAsserter;
    private ApplicationStateHandler $applicationStateHandler;
    private IntegrationJobProperties $jobProperties;

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

        $systemStateAsserter = self::getContainer()->get(SystemStateAsserter::class);
        \assert($systemStateAsserter instanceof SystemStateAsserter);
        $this->systemStateAsserter = $systemStateAsserter;

        $applicationStateHandler = self::getContainer()->get(ApplicationStateHandler::class);
        \assert($applicationStateHandler instanceof ApplicationStateHandler);
        $this->applicationStateHandler = $applicationStateHandler;

        $jobProperties = self::getContainer()->get(IntegrationJobProperties::class);
        \assert($jobProperties instanceof IntegrationJobProperties);
        $this->jobProperties = $jobProperties;
    }

    /**
     * @dataProvider createAddSourcesCompileExecuteDataProvider
     *
     * @param string[]                  $sourcePaths
     * @param CompilationState::STATE_* $expectedCompilationEndState
     * @param ExecutionState::STATE_*   $expectedExecutionEndState
     * @param ApplicationState::STATE_* $expectedApplicationEndState
     */
    public function testCreateAddSourcesCompileExecute(
        int $jobMaximumDurationInSeconds,
        string $manifestPath,
        array $sourcePaths,
        ?callable $postAddSources,
        string $expectedCompilationEndState,
        string $expectedExecutionEndState,
        string $expectedApplicationEndState,
        ?callable $assertions = null
    ): void {
        $statusResponse = $this->clientRequestSender->getStatus();
        $this->jsonResponseAsserter->assertJsonResponse(400, [], $statusResponse);

        $label = $this->jobProperties->getLabel();
        $callbackUrl = $this->jobProperties->getCallbackUrl();

        $createResponse = $this->clientRequestSender->createJob($label, $callbackUrl, $jobMaximumDurationInSeconds);
        $this->jsonResponseAsserter->assertJsonResponse(200, (object) [], $createResponse);

        $expectedJobProperties = [
            'label' => $label,
            'callback_url' => $callbackUrl,
            'maximum_duration_in_seconds' => $jobMaximumDurationInSeconds,
        ];

        $statusResponse = $this->clientRequestSender->getStatus();

        $this->jsonResponseAsserter->assertJsonResponse(
            200,
            array_merge(
                $expectedJobProperties,
                [
                    'compilation_state' => CompilationState::STATE_AWAITING,
                    'execution_state' => ExecutionState::STATE_AWAITING,
                    'callback_state' => CallbackState::STATE_AWAITING,
                    'sources' => [],
                    'tests' => [],
                ]
            ),
            $statusResponse
        );

        $this->systemStateAsserter->assertSystemState(
            CompilationState::STATE_AWAITING,
            ExecutionState::STATE_AWAITING,
            ApplicationState::STATE_AWAITING_SOURCES
        );

        $uploadedFileFactory = self::getContainer()->get(UploadedFileFactory::class);
        \assert($uploadedFileFactory instanceof UploadedFileFactory);

        $uploadedFileCollection = $uploadedFileFactory->createCollection(
            $this->uploadStoreHandler->copyFixtures($sourcePaths)
        );

        $addSourcesResponse = $this->clientRequestSender->addJobSources(
            $uploadedFileFactory->createForManifest($manifestPath),
            $uploadedFileCollection
        );

        $this->jsonResponseAsserter->assertJsonResponse(200, (object) [], $addSourcesResponse);

        $timer = new Timer();
        $timer->start();

        if (is_callable($postAddSources)) {
            $this->callableInvoker->invoke($postAddSources);
        }

        $this->applicationStateHandler->waitUntilStateIs([
            ApplicationState::STATE_COMPLETE,
            ApplicationState::STATE_TIMED_OUT,
        ]);

        $duration = $timer->stop();

        $this->systemStateAsserter->assertSystemState(
            $expectedCompilationEndState,
            $expectedExecutionEndState,
            $expectedApplicationEndState
        );

        self::assertLessThanOrEqual(self::MAX_DURATION_IN_SECONDS, $duration->asSeconds());

        if (is_callable($assertions)) {
            $this->callableInvoker->invoke($assertions);
        }
    }

    /**
     * @return array[]
     */
    public function createAddSourcesCompileExecuteDataProvider(): array
    {
        return [
            'default' => [
                'jobMaximumDurationInSeconds' => 60,
                'manifestPath' => getcwd() . '/tests/Fixtures/Manifest/manifest.txt',
                'sourcePaths' => [
                    'Page/index.yml',
                    'Test/chrome-open-index.yml',
                    'Test/chrome-firefox-open-index.yml',
                    'Test/chrome-open-form.yml',
                ],
                'postAddSources' => function (MessageBusInterface $messageBus) {
                    $messageBus->dispatch(new JobReadyMessage());
                },
                'expectedCompilationEndState' => CompilationState::STATE_COMPLETE,
                'expectedExecutionEndState' => ExecutionState::STATE_COMPLETE,
                'expectedApplicationEndState' => ApplicationState::STATE_COMPLETE,
                'assertions' => function (
                    HttpLogReader $httpLogReader,
                    IntegrationJobProperties $jobProperties,
                    IntegrationCallbackRequestFactory $requestFactory,
                ) {
                    $expectedHttpRequests = new RequestCollection([
                        'job/started' => $requestFactory->create(
                            CallbackInterface::TYPE_JOB_STARTED,
                            []
                        ),
                        'compilation/started: chrome-open-index' => $requestFactory->create(
                            CallbackInterface::TYPE_COMPILATION_STARTED,
                            [
                                'source' => 'Test/chrome-open-index.yml',
                            ]
                        ),
                        'compilation/passed: chrome-open-index' => $requestFactory->create(
                            CallbackInterface::TYPE_COMPILATION_PASSED,
                            [
                                'source' => 'Test/chrome-open-index.yml',
                            ]
                        ),
                        'compilation/started: chrome-firefox-open-index' => $requestFactory->create(
                            CallbackInterface::TYPE_COMPILATION_STARTED,
                            [
                                'source' => 'Test/chrome-firefox-open-index.yml',
                            ]
                        ),
                        'compilation/passed: chrome-firefox-open-index' => $requestFactory->create(
                            CallbackInterface::TYPE_COMPILATION_PASSED,
                            [
                                'source' => 'Test/chrome-firefox-open-index.yml',
                            ]
                        ),
                        'compilation/started: chrome--open-form' => $requestFactory->create(
                            CallbackInterface::TYPE_COMPILATION_STARTED,
                            [
                                'source' => 'Test/chrome-open-form.yml',
                            ]
                        ),
                        'compilation/passed: chrome--open-form' => $requestFactory->create(
                            CallbackInterface::TYPE_COMPILATION_PASSED,
                            [
                                'source' => 'Test/chrome-open-form.yml',
                            ]
                        ),
                        'compilation/completed' => $requestFactory->create(
                            CallbackInterface::TYPE_COMPILATION_SUCCEEDED,
                            []
                        ),
                        'execution/started' => $requestFactory->create(
                            CallbackInterface::TYPE_EXECUTION_STARTED,
                            []
                        ),
                        'test/started: chrome-open-index' => $requestFactory->create(
                            CallbackInterface::TYPE_TEST_STARTED,
                            [
                                'type' => 'test',
                                'path' => 'Test/chrome-open-index.yml',
                                'config' => [
                                    'browser' => 'chrome',
                                    'url' => 'http://html-fixtures/index.html',
                                ],
                            ]
                        ),
                        'step/passed: chrome-open-index: open' => $requestFactory->create(
                            CallbackInterface::TYPE_STEP_PASSED,
                            [
                                'type' => 'step',
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
                            ]
                        ),
                        'test/passed: chrome-open-index' => $requestFactory->create(
                            CallbackInterface::TYPE_TEST_PASSED,
                            [
                                'type' => 'test',
                                'path' => 'Test/chrome-open-index.yml',
                                'config' => [
                                    'browser' => 'chrome',
                                    'url' => 'http://html-fixtures/index.html',
                                ],
                            ]
                        ),
                        'test/started: chrome-firefox-open-index: chrome' => $requestFactory->create(
                            CallbackInterface::TYPE_TEST_STARTED,
                            [
                                'type' => 'test',
                                'path' => 'Test/chrome-firefox-open-index.yml',
                                'config' => [
                                    'browser' => 'chrome',
                                    'url' => 'http://html-fixtures/index.html',
                                ],
                            ]
                        ),
                        'step/passed: chrome-firefox-open-index: chrome, open' => $requestFactory->create(
                            CallbackInterface::TYPE_STEP_PASSED,
                            [
                                'type' => 'step',
                                'name' => 'verify page is open',
                                'status' => 'passed',
                                'statements' => [
                                    [
                                        'type' => 'assertion',
                                        'source' => '$page.url is "http://html-fixtures/index.html"',
                                        'status' => 'passed',
                                    ],
                                ],
                            ]
                        ),
                        'test/passed: chrome-firefox-open-index: chrome' => $requestFactory->create(
                            CallbackInterface::TYPE_TEST_PASSED,
                            [
                                'type' => 'test',
                                'path' => 'Test/chrome-firefox-open-index.yml',
                                'config' => [
                                    'browser' => 'chrome',
                                    'url' => 'http://html-fixtures/index.html',
                                ],
                            ]
                        ),
                        'test/started: chrome-firefox-open-index: firefox' => $requestFactory->create(
                            CallbackInterface::TYPE_TEST_STARTED,
                            [
                                'type' => 'test',
                                'path' => 'Test/chrome-firefox-open-index.yml',
                                'config' => [
                                    'browser' => 'firefox',
                                    'url' => 'http://html-fixtures/index.html',
                                ],
                            ]
                        ),
                        'step/passed: chrome-firefox-open-index: firefox open' => $requestFactory->create(
                            CallbackInterface::TYPE_STEP_PASSED,
                            [
                                'type' => 'step',
                                'name' => 'verify page is open',
                                'status' => 'passed',
                                'statements' => [
                                    [
                                        'type' => 'assertion',
                                        'source' => '$page.url is "http://html-fixtures/index.html"',
                                        'status' => 'passed',
                                    ],
                                ],
                            ]
                        ),
                        'test/passed: chrome-firefox-open-index: firefox' => $requestFactory->create(
                            CallbackInterface::TYPE_TEST_PASSED,
                            [
                                'type' => 'test',
                                'path' => 'Test/chrome-firefox-open-index.yml',
                                'config' => [
                                    'browser' => 'firefox',
                                    'url' => 'http://html-fixtures/index.html',
                                ],
                            ]
                        ),
                        'test/started: chrome-open-form' => $requestFactory->create(
                            CallbackInterface::TYPE_TEST_STARTED,
                            [
                                'type' => 'test',
                                'path' => 'Test/chrome-open-form.yml',
                                'config' => [
                                    'browser' => 'chrome',
                                    'url' => 'http://html-fixtures/form.html',
                                ],
                            ]
                        ),
                        'step/passed: chrome-open-form: open' => $requestFactory->create(
                            CallbackInterface::TYPE_STEP_PASSED,
                            [
                                'type' => 'step',
                                'name' => 'verify page is open',
                                'status' => 'passed',
                                'statements' => [
                                    [
                                        'type' => 'assertion',
                                        'source' => '$page.url is "http://html-fixtures/form.html"',
                                        'status' => 'passed',
                                    ],
                                ],
                            ]
                        ),
                        'test/passed: chrome-open-form' => $requestFactory->create(
                            CallbackInterface::TYPE_TEST_PASSED,
                            [
                                'type' => 'test',
                                'path' => 'Test/chrome-open-form.yml',
                                'config' => [
                                    'browser' => 'chrome',
                                    'url' => 'http://html-fixtures/form.html',
                                ],
                            ]
                        ),
                        'execution/completed' => $requestFactory->create(
                            CallbackInterface::TYPE_EXECUTION_COMPLETED,
                            []
                        ),
                        'job/completed' => $requestFactory->create(
                            CallbackInterface::TYPE_JOB_COMPLETED,
                            []
                        ),
                    ]);

                    $transactions = $httpLogReader->getTransactions();
                    $httpLogReader->reset();

                    $this->assertRequestCollectionsAreEquivalent($expectedHttpRequests, $transactions->getRequests());
                },
            ],
            'step failed' => [
                'jobMaximumDurationInSeconds' => 60,
                'manifestPath' => getcwd() . '/tests/Fixtures/Manifest/manifest-step-failure.txt',
                'sourcePaths' => [
                    'Test/chrome-open-index-with-step-failure.yml',
                ],
                'postAddSources' => function (MessageBusInterface $messageBus) {
                    $messageBus->dispatch(new JobReadyMessage());
                },
                'expectedCompilationEndState' => CompilationState::STATE_COMPLETE,
                'expectedExecutionEndState' => ExecutionState::STATE_CANCELLED,
                'expectedApplicationEndState' => ApplicationState::STATE_COMPLETE,
                'assertions' => function (
                    HttpLogReader $httpLogReader,
                    IntegrationJobProperties $jobProperties,
                    IntegrationCallbackRequestFactory $requestFactory,
                ) {
                    $transactions = $httpLogReader->getTransactions();
                    $httpLogReader->reset();

                    $expectedHttpRequests = new RequestCollection([
                        'step/failed' => $requestFactory->create(
                            CallbackInterface::TYPE_STEP_FAILED,
                            [
                                'type' => 'step',
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
                            ]
                        ),
                        'test/failed' => $requestFactory->create(
                            CallbackInterface::TYPE_TEST_FAILED,
                            [
                                'type' => 'test',
                                'path' => 'Test/chrome-open-index-with-step-failure.yml',
                                'config' => [
                                    'browser' => 'chrome',
                                    'url' => 'http://html-fixtures/index.html',
                                ],
                            ]
                        ),
                        'job/failed' => $requestFactory->create(
                            CallbackInterface::TYPE_JOB_FAILED,
                            []
                        ),
                    ]);

                    $transactions = $transactions->slice(
                        -1 * $expectedHttpRequests->count(),
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
