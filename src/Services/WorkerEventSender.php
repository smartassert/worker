<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEvent;
use App\Exception\NonSuccessfulHttpResponseException;
use App\HttpMessage\EventDeliveryRequest;
use App\Repository\JobRepository;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as HttpClientInterface;

class WorkerEventSender
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private readonly JobRepository $jobRepository,
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws NonSuccessfulHttpResponseException
     */
    public function send(WorkerEvent $workerEvent): void
    {
        $job = $this->jobRepository->get();
        if (null === $job) {
            return;
        }

        $request = new EventDeliveryRequest($workerEvent, $job);
        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() >= 300) {
            throw new NonSuccessfulHttpResponseException($workerEvent, $response);
        }
    }
}
