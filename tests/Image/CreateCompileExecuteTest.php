<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;

class CreateCompileExecuteTest extends AbstractJobTest
{
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
        self::assertArrayHasKey('header', $responseData);

        $headerData = $responseData['header'];
        self::assertIsArray($headerData);
        self::assertArrayHasKey('type', $headerData);
        self::assertSame('job/started', $headerData['type']);
    }

    protected function doMain(): void
    {
        $this->assertJob(
            [
                'label' => md5('label content'),
                'event_delivery_url' => 'http://event-receiver/status/200',
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

        $jobData = $this->fetchJob();
        self::assertArrayHasKey('event_ids', $jobData);

        $eventIds = $jobData['event_ids'];
        self::assertNotEmpty($eventIds);
        self::assertNotSame([1], $eventIds);
    }

    protected static function getManifestPaths(): array
    {
        return [
            'Test/chrome-open-index.yml',
            'Test/chrome-firefox-open-index.yml',
            'Test/chrome-open-form.yml',
        ];
    }

    protected static function getSourcePaths(): array
    {
        return [
            'Test/chrome-open-index.yml',
            'Test/chrome-firefox-open-index.yml',
            'Test/chrome-open-form.yml',
            'Page/index.yml',
        ];
    }

    protected static function getCreateJobParameters(): array
    {
        return [
            'label' => md5('label content'),
            'event_delivery_url' => 'http://event-receiver/status/200',
            'maximum_duration_in_seconds' => 600,
        ];
    }

    protected function isApplicationToComplete(): bool
    {
        $state = $this->fetchApplicationState();

        return CompilationState::COMPLETE->value === $state['compilation']
            && ExecutionState::COMPLETE->value === $state['execution']
            && EventDeliveryState::COMPLETE->value === $state['event_delivery'];
    }

    protected function getWaitThresholdInSeconds(): int
    {
        return 60;
    }
}
