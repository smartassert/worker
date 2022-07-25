<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEvent;
use App\Exception\JobNotFoundException;
use App\Exception\NonSuccessfulHttpResponseException;
use App\Repository\JobRepository;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as HttpClientInterface;

class WorkerEventSender
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private readonly JobRepository $jobRepository,
        private readonly EventDeliveryRequestFactory $eventDeliveryRequestFactory,
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws NonSuccessfulHttpResponseException
     * @throws JobNotFoundException
     */
    public function send(WorkerEvent $workerEvent): void
    {
        $request = $this->eventDeliveryRequestFactory->create($this->jobRepository->get(), $workerEvent);

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() >= 300) {
            throw new NonSuccessfulHttpResponseException($workerEvent, $response);
        }
    }
}
