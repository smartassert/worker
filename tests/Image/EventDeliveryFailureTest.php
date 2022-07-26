<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;
use App\Enum\WorkerEventState;

class EventDeliveryFailureTest extends AbstractImageTest
{
    private const MICROSECONDS_PER_SECOND = 1000000;
    private const WAIT_INTERVAL = self::MICROSECONDS_PER_SECOND;
    private const WAIT_TIMEOUT = self::MICROSECONDS_PER_SECOND * 10;

    protected function setUp(): void
    {
        parent::setUp();

        $serializedSource = $this->createSerializedSource(
            [
                'Test/chrome-open-index.yml',
            ],
            [
                'Test/chrome-open-index.yml',
                'Page/index.yml',
            ]
        );

        $this->makeCreateJobRequest([
            'label' => md5('label content'),
            'event_delivery_url' => 'http://event-receiver/status/404',
            'maximum_duration_in_seconds' => 20,
            'source' => $serializedSource,
        ]);
    }

    public function testAllEventsAreMarkedAsFailed(): void
    {
        $duration = 0;
        $durationExceeded = false;

        while (false === $durationExceeded && false === $this->waitForApplicationToComplete()) {
            usleep(self::WAIT_INTERVAL);
            $duration += self::WAIT_INTERVAL;
            $durationExceeded = $duration >= self::WAIT_TIMEOUT;
        }

        self::assertFalse($durationExceeded);

        $jobStatusResponse = $this->makeGetJobRequest();
        self::assertSame(200, $jobStatusResponse->getStatusCode());
        self::assertSame('application/json', $jobStatusResponse->getHeaderLine('content-type'));

        $jobStatusData = json_decode($jobStatusResponse->getBody()->getContents(), true);
        self::assertIsArray($jobStatusData);
        self::assertArrayHasKey('event_ids', $jobStatusData);

        $eventIds = $jobStatusData['event_ids'];
        self::assertIsArray($eventIds);

        $filteredEventIds = [];
        foreach ($eventIds as $eventId) {
            if (is_int($eventId)) {
                $filteredEventIds[] = $eventId;
            }
        }
        self::assertNotEmpty($filteredEventIds);

        foreach ($filteredEventIds as $eventId) {
            if (is_int($eventId)) {
                $eventResponse = $this->makeGetEventRequest($eventId);

                self::assertSame(200, $jobStatusResponse->getStatusCode());
                self::assertSame('application/json', $jobStatusResponse->getHeaderLine('content-type'));

                $eventData = json_decode($eventResponse->getBody()->getContents(), true);
                self::assertIsArray($eventData);
                self::assertArrayHasKey('state', $eventData);
                self::assertSame(WorkerEventState::FAILED->value, $eventData['state']);
            }
        }
    }

    private function waitForApplicationToComplete(): bool
    {
        $state = $this->fetchApplicationState();

        return CompilationState::COMPLETE->value === $state['compilation']
            && ExecutionState::COMPLETE->value === $state['execution']
            && EventDeliveryState::COMPLETE->value === $state['event_delivery'];
    }
}
