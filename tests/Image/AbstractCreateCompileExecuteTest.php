<?php

declare(strict_types=1);

namespace App\Tests\Image;

use Psr\Http\Message\ResponseInterface;
use SmartAssert\ResultsClient\ClientInterface as ResultsClient;
use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;
use Symfony\Component\Uid\Ulid;

abstract class AbstractCreateCompileExecuteTest extends AbstractImageTestCase
{
    protected const int MICROSECONDS_PER_SECOND = 1000000;
    protected const int WAIT_INTERVAL = self::MICROSECONDS_PER_SECOND;
    protected static ResponseInterface $createResponse;

    protected static string $jobId;
    protected static ResultsJob $resultsJob;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $apiToken = $apiTokenProvider->get('user@example.com');

        $resultsClient = self::getContainer()->get(ResultsClient::class);
        \assert($resultsClient instanceof ResultsClient);

        self::$jobId = (string) new Ulid();
        self::$resultsJob = $resultsClient->createJob($apiToken, self::$jobId, null);

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
                'event_notify_url' => self::getEventNotifyUrl(),
                'event_notify_token' => 'event-notify-token',
                'maximum_duration_in_seconds' => 600,
            ]
        ));
    }

    protected static function getEventNotifyUrl(): string
    {
        return self::$resultsJob->authenticator;
    }

    protected function doWaitForApplicationToFinish(): void
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
    }

    protected function isApplicationComplete(): bool
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

        $metaState = $state['meta_state'] ?? [];
        $metaState = is_array($metaState) ? $metaState : [];

        $metaStateEnded = $metaState['ended'] ?? false;

        return is_bool($metaStateEnded) && $metaStateEnded;
    }
}
