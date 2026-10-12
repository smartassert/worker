<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Enum\ApplicationState;
use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\ResultsClient\ClientInterface as ResultsClient;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Component\Uid\Ulid;

class CreateCompileExecuteTest extends AbstractImageTestCase
{
    private const MICROSECONDS_PER_SECOND = 1000000;
    private const WAIT_INTERVAL = self::MICROSECONDS_PER_SECOND;
    protected static ResponseInterface $createResponse;

    private static string $jobId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user@example.com');

        $resultsClient = self::getContainer()->get(ResultsClient::class);
        \assert($resultsClient instanceof ResultsClient);

        self::$jobId = (string) new Ulid();
        $resultsJob = $resultsClient->createJob($apiToken, self::$jobId, null);

        self::$createResponse = self::makeCreateJobRequest(array_merge(
            [
                'source' => self::createSerializedSource(
                    [
                        'Test/chrome-open-index.yml',
                        'Test/chrome-firefox-open-index.yml',
                        'Test/chrome-open-form.yml',
                    ],
                    [
                        'Test/chrome-open-index.yml',
                        'Test/chrome-firefox-open-index.yml',
                        'Test/chrome-open-form.yml',
                        'Page/index.yml',
                    ]
                ),
            ],
            [
                'label' => self::$jobId,
                'event_add_url' => $resultsJob->authenticator,
                'maximum_duration_in_seconds' => 600,
            ]
        ));

        self::assertSame(200, self::$createResponse->getStatusCode());

        $duration = 0;
        $durationExceeded = false;
        $waitThreshold = 60 * self::MICROSECONDS_PER_SECOND;

        while (false === $durationExceeded && false === self::isApplicationComplete()) {
            usleep(self::WAIT_INTERVAL);
            $duration += self::WAIT_INTERVAL;
            $durationExceeded = $duration >= $waitThreshold;
        }

        self::assertFalse($durationExceeded);
    }

    public function testMain(): void
    {
        $this->assertJob(
            [
                'label' => self::$jobId,
                'maximum_duration_in_seconds' => 600,
                'sources' => [
                    'Test/chrome-open-index.yml',
                    'Test/chrome-firefox-open-index.yml',
                    'Test/chrome-open-form.yml',
                    'Page/index.yml',
                ],
                'tests' => [
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-open-index.yml',
                        'step_names' => [
                            'verify page is open',
                        ],
                        'state' => 'complete',
                        'position' => 1,
                    ],
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-firefox-open-index.yml',
                        'step_names' => [
                            'verify page is open',
                        ],
                        'state' => 'complete',
                        'position' => 2,
                    ],
                    [
                        'browser' => 'firefox',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-firefox-open-index.yml',
                        'step_names' => [
                            'verify page is open',
                        ],
                        'state' => 'complete',
                        'position' => 3,
                    ],
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/form.html',
                        'source' => 'Test/chrome-open-form.yml',
                        'step_names' => [
                            'verify page is open',
                        ],
                        'state' => 'complete',
                        'position' => 4,
                    ],
                ],
            ],
            $this->fetchJob()
        );
        $this->assertApplicationState(
            [
                'application' => [
                    'state' => 'complete',
                    'meta_state' => [
                        'pending' => false,
                        'ended' => true,
                        'succeeded' => true,
                    ],
                    'previous_states' => [
                        ApplicationState::AWAITING->value,
                        ApplicationState::COMPILING->value,
                        ApplicationState::EXECUTING->value,
                        ApplicationState::COMPLETING_EVENT_DELIVERY->value,
                        ApplicationState::COMPLETE->value,
                    ],
                ],
                'compilation' => [
                    'state' => 'complete',
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
                'execution' => [
                    'state' => 'complete',
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
                'event_delivery' => [
                    'state' => 'complete',
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
            ],
            $this->fetchApplicationState()
        );

        $jobData = $this->fetchJob();
        self::assertArrayHasKey('event_ids', $jobData);

        $eventIds = $jobData['event_ids'];
        self::assertNotEmpty($eventIds);
        self::assertNotSame([1], $eventIds);
    }

    public function testGetJobStartedEvent(): void
    {
        $jobData = $this->fetchJob();
        $eventIds = $jobData['event_ids'];
        \assert(\is_array($eventIds));

        $eventId = $eventIds[0];
        \assert(\is_int($eventId));

        $response = $this->makeGetEventRequest($eventId);
        self::assertSame(200, $response->getStatusCode());

        $responseData = json_decode($response->getBody()->getContents(), true);
        self::assertIsArray($responseData);
        self::assertArrayHasKey('type', $responseData);
        self::assertSame('job/started', $responseData['type']);
    }

    private static function isApplicationComplete(): bool
    {
        $state = self::fetchApplicationState();

        return true === self::isComponentEndState($state, 'compilation')
            && true === self::isComponentEndState($state, 'execution')
            && true === self::isComponentEndState($state, 'event_delivery');
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
