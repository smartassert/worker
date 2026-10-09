<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Enum\ApplicationState;
use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;

class CreateCompileExecuteTest extends AbstractCreateCompileExecuteTest
{
    public function testMain(): void
    {
        $this->doWaitForApplicationToFinish();

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
}
