<?php

declare(strict_types=1);

namespace App\Tests\Unit\HttpMessage;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\HttpMessage\EventDeliveryRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use webignition\ObjectReflector\ObjectReflector;

class EventDeliveryRequestTest extends TestCase
{
    public function testCreate(): void
    {
        $jobEventDeliveryUrl = 'http://example.com/events';
        $jobLabel = 'label content';
        $job = new Job($jobLabel, $jobEventDeliveryUrl, 600, ['test.yml']);

        $eventScope = WorkerEventScope::JOB;
        $eventOutcome = WorkerEventOutcome::COMPLETED;
        $eventLabel = 'non-empty label';
        $eventReference = 'reference value';
        $workerEventData = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $workerEvent = new WorkerEvent($eventScope, $eventOutcome, $eventLabel, $eventReference, $workerEventData);
        ObjectReflector::setProperty($workerEvent, $workerEvent::class, 'id', 123);

        $request = new EventDeliveryRequest($workerEvent, $job);

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame($jobEventDeliveryUrl, (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('content-type'));
        self::assertSame(
            [
                'job' => $jobLabel,
                'sequence_number' => 123,
                'type' => $eventScope->value . '/' . $eventOutcome->value,
                'label' => $eventLabel,
                'reference' => $eventReference,
                'payload' => $workerEventData,
            ],
            json_decode((string) $request->getBody(), true)
        );
    }
}
