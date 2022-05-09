<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEvent;
use App\Exception\NonSuccessfulHttpResponseException;
use App\HttpMessage\CallbackRequest;
use App\Repository\JobRepository;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as HttpClientInterface;

class CallbackSender
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
    public function send(WorkerEvent $callback): void
    {
        $job = $this->jobRepository->get();
        if (null === $job) {
            return;
        }

        $request = new CallbackRequest($callback, $job);
        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() >= 300) {
            throw new NonSuccessfulHttpResponseException($callback, $response);
        }
    }
}
