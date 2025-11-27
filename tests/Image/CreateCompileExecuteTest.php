<?php

declare(strict_types=1);

namespace App\Tests\Image;

use Psr\Http\Message\ResponseInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Component\Uid\Ulid;

class CreateCompileExecuteTest extends AbstractImageTestCase
{
    private const MICROSECONDS_PER_SECOND = 1000000;
    private const WAIT_INTERVAL = self::MICROSECONDS_PER_SECOND;
    protected static ResponseInterface $createResponse;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user@example.com');

        $resultsClient = self::getContainer()->get(ResultsClient::class);
        \assert($resultsClient instanceof ResultsClient);

        $jobLabel = (string) new Ulid();
        $resultsJob = $resultsClient->createJob($apiToken, $jobLabel);

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
                'label' => $jobLabel,
                'results_token' => $resultsJob->token,
                'maximum_duration_in_seconds' => 600,
            ]
        ));
    }

    public function testMain(): void
    {
        $duration = 0;
        $durationExceeded = false;
        $waitThreshold = 60 * self::MICROSECONDS_PER_SECOND;

        while (false === $durationExceeded && false === $this->isApplicationComplete()) {
            usleep(self::WAIT_INTERVAL);
            $duration += self::WAIT_INTERVAL;
            $durationExceeded = $duration >= $waitThreshold;
        }

        self::assertFalse($durationExceeded);

        $this->assertJob(
            [
                'label' => md5('label content'),
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
                    'is_end_state' => true,
                ],
                'compilation' => [
                    'state' => 'complete',
                    'is_end_state' => true,
                ],
                'execution' => [
                    'state' => 'complete',
                    'is_end_state' => true,
                ],
                'event_delivery' => [
                    'state' => 'complete',
                    'is_end_state' => true,
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

    public function testJobIsCreated(): void
    {
        self::assertSame(200, self::$createResponse->getStatusCode());

        $responseData = json_decode(self::$createResponse->getBody()->getContents(), true);
        self::assertIsArray($responseData);
        self::assertArrayHasKey('event_ids', $responseData);
        self::assertSame([1, 2], $responseData['event_ids']);
    }

    public function testGetJobStartedEvent(): void
    {
        $response = $this->makeGetEventRequest(1);
        self::assertSame(200, $response->getStatusCode());

        $responseData = json_decode($response->getBody()->getContents(), true);
        self::assertIsArray($responseData);
        self::assertArrayHasKey('type', $responseData);
        self::assertSame('job/started', $responseData['type']);
    }

    private function isApplicationComplete(): bool
    {
        $state = $this->fetchApplicationState();

        return true === $this->isComponentEndState($state, 'compilation')
            && true === $this->isComponentEndState($state, 'execution')
            && true === $this->isComponentEndState($state, 'event_delivery');
    }

    /**
     * @param array<mixed>     $data
     * @param non-empty-string $key
     */
    private function isComponentEndState(array $data, string $key): bool
    {
        $state = $data[$key] ?? [];
        $state = is_array($state) ? $state : [];

        $isEndState = $state['is_end_state'] ?? false;

        return is_bool($isEndState) ? $isEndState : false;
    }
}
