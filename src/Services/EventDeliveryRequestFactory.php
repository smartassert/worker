<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

class EventDeliveryRequestFactory
{
    public function __construct(
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function create(Job $job, WorkerEvent $workerEvent): RequestInterface
    {
        $payload = array_merge(
            [
                'job' => $job->getLabel(),
            ],
            [
                'sequence_number' => $workerEvent->getId(),
                'type' => $workerEvent->getScope()->value . '/' . $workerEvent->getOutcome()->value,
                'label' => $workerEvent->getLabel(),
                'reference' => $workerEvent->getReference(),
                'payload' => $workerEvent->getPayload(),
            ]
        );

        return $this
            ->requestFactory
            ->createRequest('POST', $job->getEventDeliveryUrl())
            ->withHeader('content-type', 'application/json')
            ->withBody($this->streamFactory->createStream((string) json_encode($payload)))
        ;
    }
}
