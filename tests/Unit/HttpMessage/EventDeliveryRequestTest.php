<?php

declare(strict_types=1);

namespace App\Tests\Unit\HttpMessage;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\HttpMessage\EventDeliveryRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class EventDeliveryRequestTest extends TestCase
{
    public function testCreate(): void
    {
        $jobCallbackUrl = 'http://example.com/callback';
        $jobLabel = 'label content';
        $job = Job::create($jobLabel, $jobCallbackUrl, 600);

        $workerEventType = WorkerEventType::JOB_COMPLETED;
        $workerEventReference = 'reference value';
        $workerEventData = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $workerEvent = \Mockery::mock(WorkerEvent::class);
        $workerEvent
            ->shouldReceive('getType')
            ->andReturn($workerEventType)
        ;
        $workerEvent
            ->shouldReceive('getPayload')
            ->andReturn($workerEventData)
        ;
        $workerEvent
            ->shouldReceive('getReference')
            ->andReturn($workerEventReference)
        ;

        $request = new EventDeliveryRequest($workerEvent, $job);

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame($jobCallbackUrl, (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('content-type'));
        self::assertSame(
            [
                'label' => $jobLabel,
                'type' => $workerEventType->value,
                'reference' => $workerEventReference,
                'payload' => $workerEventData,
            ],
            json_decode((string) $request->getBody(), true)
        );
    }
}
