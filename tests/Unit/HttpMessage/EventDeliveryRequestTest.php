<?php

declare(strict_types=1);

namespace App\Tests\Unit\HttpMessage;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventType;
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

        $workerEventScope = WorkerEventScope::JOB;
        $workerEventType = WorkerEventType::JOB_COMPLETED;
        $workerEventReference = 'reference value';
        $workerEventData = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $workerEvent = new WorkerEvent(
            $workerEventScope,
            $workerEventType,
            $workerEventReference,
            $workerEventData
        );
        ObjectReflector::setProperty($workerEvent, $workerEvent::class, 'id', 123);

        $request = new EventDeliveryRequest($workerEvent, $job);

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame($jobEventDeliveryUrl, (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('content-type'));
        self::assertSame(
            [
                'label' => $jobLabel,
                'identifier' => 123,
                'type' => $workerEventType->value,
                'reference' => $workerEventReference,
                'payload' => $workerEventData,
            ],
            json_decode((string) $request->getBody(), true)
        );
    }
}
