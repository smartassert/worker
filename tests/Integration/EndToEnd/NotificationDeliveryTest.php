<?php

declare(strict_types=1);

namespace App\Tests\Integration\EndToEnd;

use App\Enum\ApplicationState;
use App\Enum\StateInterface;
use App\Request\CreateJobRequest;
use App\Services\ApplicationProgress;
use App\Tests\Integration\AbstractBaseIntegrationTestCase;
use App\Tests\Services\Asserter\JsonResponseAsserter;
use App\Tests\Services\ClientRequestSender;
use App\Tests\Services\CreateJobSourceFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\CallbackReceiverLogReader\Parser;
use SmartAssert\ResultsClient\ClientInterface as ResultsClient;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Component\Process\Process;
use Symfony\Component\Uid\Ulid;

use function PHPUnit\Framework\assertEquals;

class NotificationDeliveryTest extends AbstractBaseIntegrationTestCase
{
    private ClientRequestSender $clientRequestSender;
    private JsonResponseAsserter $jsonResponseAsserter;
    private CreateJobSourceFactory $createJobSourceFactory;
    private ApplicationProgress $applicationProgress;
    private ResultsJob $resultsJob;

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

        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user@example.com');

        $resultsClient = self::getContainer()->get(ResultsClient::class);
        \assert($resultsClient instanceof ResultsClient);

        $jobLabel = (string) new Ulid();
        $this->resultsJob = $resultsClient->createJob($apiToken, $jobLabel);
    }

    /**
     * @param non-empty-string[] $manifestPaths
     * @param string[]           $sourcePaths
     * @param array<mixed>       $expectedRequestBodies
     */
    #[DataProvider('deliveredNotificationsDataProvider')]
    public function testDeliveredNotifications(
        array $manifestPaths,
        array $sourcePaths,
        string $jobLabel,
        StateInterface $expectedApplicationState,
        int $expectedDispatchedNotificationsCount,
        array $expectedRequestBodies,
    ): void {
        $jobStatusResponse = $this->clientRequestSender->getJobStatus();
        $this->jsonResponseAsserter->assertJsonResponse(400, [], $jobStatusResponse);

        $requestPayload = [
            CreateJobRequest::KEY_LABEL => $jobLabel,
            CreateJobRequest::KEY_EVENT_ADD_URL => $this->resultsJob->authenticator,
            CreateJobRequest::KEY_MAXIMUM_DURATION => 60,
            CreateJobRequest::KEY_SOURCE => $this->createJobSourceFactory->create($manifestPaths, $sourcePaths),
            CreateJobRequest::STATE_NOTIFY_URL => 'http://localhost:8080',
        ];

        $createResponse = $this->clientRequestSender->createJob($requestPayload);
        self::assertSame(200, $createResponse->getStatusCode());
        self::assertSame($expectedApplicationState, $this->applicationProgress->get());

        $process = Process::fromShellCommandline('docker logs callback-receiver');
        $process->run();

        $output = $process->getOutput();
        $parser = new Parser();

        $requests = $parser->parse($output, $expectedDispatchedNotificationsCount);
        //        $requests = $parser->parse($output, 100);
        //        var_dump(count($requests));
        self::assertCount($expectedDispatchedNotificationsCount, $requests);
        self::assertSame(count($requests), count($expectedRequestBodies));

        foreach ($expectedRequestBodies as $requestIndex => $expectedRequestBody) {
            $request = $requests[$requestIndex];

            self::assertSame('POST', $request->getMethod());
            self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
            self::assertEquals('/worker.application.state_changed', (string) $request->getUri());

            $requestData = json_decode($request->getBody()->getContents(), true);
            self::assertIsArray($requestData);
            self:assertEquals($expectedRequestBody, $requestData);
        }
    }

    /**
     * @return array<mixed>
     */
    public static function deliveredNotificationsDataProvider(): array
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
            'compilation failed on first test' => [
                'manifestPaths' => [
                    'Test/chrome-open-index-compilation-failure.yml',
                ],
                'sourcePaths' => [
                    'Test/chrome-open-index-compilation-failure.yml',
                ],
                'jobLabel' => $jobLabel,
                'expectedApplicationState' => ApplicationState::FAILED,
                'expectedRequestBodies' => [
                    [
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
                    [
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
                    [
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
                    [
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
                'expectedDispatchedNotificationsCount' => 4,
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
                'expectedApplicationState' => ApplicationState::FAILED,
                'expectedRequestBodies' => [
                    [
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
                    [
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
                    [
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
                    [
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
                'expectedDispatchedNotificationsCount' => 4,
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
                'expectedRequestBodies' => [
                    [
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
                    [
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
                    [
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
                    [
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
                    [
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
                    [
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
                'expectedDispatchedNotificationsCount' => 6,
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
                'expectedRequestBodies' => [
                    [
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
                    [
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
                    [
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
                    [
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
                    [
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
                    [
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
                'expectedDispatchedNotificationsCount' => 6,
            ],
        ];
    }
}
