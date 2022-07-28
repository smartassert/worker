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
        private readonly WorkerEventSerializer $workerEventSerializer,
    ) {
    }

    public function create(Job $job, WorkerEvent $workerEvent): RequestInterface
    {
        return $this
            ->requestFactory
            ->createRequest('POST', $job->eventDeliveryUrl)
            ->withHeader('content-type', 'application/json')
            ->withBody($this->streamFactory->createStream((string) json_encode(
                $this->workerEventSerializer->serialize($job, $workerEvent)
            )))
        ;
    }
}
