<?php

declare(strict_types=1);

namespace App\HttpMessage;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use GuzzleHttp\Psr7\Request as GuzzleRequest;

class EventDeliveryRequest extends GuzzleRequest
{
    private const METHOD = 'POST';

    public function __construct(WorkerEvent $workerEvent, Job $job)
    {
        parent::__construct(
            self::METHOD,
            (string) $job->getEventDeliveryUrl(),
            [
                'content-type' => 'application/json',
            ],
            (string) json_encode([
                'label' => $job->getLabel(),
                'identifier' => $workerEvent->getId(),
                'type' => $workerEvent->getType(),
                'reference' => $workerEvent->getReference(),
                'payload' => $workerEvent->getPayload(),
            ])
        );
    }
}
