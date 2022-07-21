<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;
use GuzzleHttp\Exception\ClientException;

class AppTest extends AbstractImageTest
{
    private const MICROSECONDS_PER_SECOND = 1000000;
    private const WAIT_INTERVAL = self::MICROSECONDS_PER_SECOND;
    private const WAIT_TIMEOUT = self::MICROSECONDS_PER_SECOND * 60;

    public function testInitialStatus(): void
    {
        try {
            $response = $this->makeGetJobRequest();
        } catch (ClientException $exception) {
            $response = $exception->getResponse();
        }

        self::assertSame(400, $response->getStatusCode());
    }

    /**
     * @depends testInitialStatus
     */
    public function testCreateJob(): void
    {
        $serializedSource = $this->createSerializedSource(
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
        );

        $response = $this->makeCreateJobRequest([
            'label' => md5('label content'),
            'event_delivery_url' => 'http://event-receiver/events',
            'maximum_duration_in_seconds' => 600,
            'source' => $serializedSource,
        ]);

        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @depends testCreateJob
     */
    public function testCompilationExecution(): void
    {
        $duration = 0;
        $durationExceeded = false;

        while (false === $durationExceeded && false === $this->waitForApplicationToComplete()) {
            usleep(self::WAIT_INTERVAL);
            $duration += self::WAIT_INTERVAL;
            $durationExceeded = $duration >= self::WAIT_TIMEOUT;
        }

        self::assertFalse($durationExceeded);

        $this->assertJob(
            [
                'label' => md5('label content'),
                'event_delivery_url' => 'http://event-receiver/events',
                'maximum_duration_in_seconds' => 600,
                'sources' => [
                    'Test/chrome-open-index.yml',
                    'Test/chrome-firefox-open-index.yml',
                    'Test/chrome-open-form.yml',
                    'Page/index.yml',
                ],
                'application_state' => 'complete',
                'compilation_state' => 'complete',
                'execution_state' => 'complete',
                'event_delivery_state' => 'complete',
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
                'application' => 'complete',
                'compilation' => 'complete',
                'execution' => 'complete',
                'event_delivery' => 'complete',
            ],
            $this->fetchApplicationState()
        );
    }

    private function waitForApplicationToComplete(): bool
    {
        $state = $this->fetchApplicationState();

        return CompilationState::COMPLETE->value === $state['compilation']
            && ExecutionState::COMPLETE->value === $state['execution']
            && EventDeliveryState::COMPLETE->value === $state['event_delivery'];
    }
}
