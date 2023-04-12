<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerEvent;
use App\Exception\JobNotFoundException;
use App\Repository\JobRepository;
use Psr\Http\Client\ClientExceptionInterface;
use SmartAssert\ResultsClient\Client as ResultsClient;
use SmartAssert\ResultsClient\Exception\InvalidJobTokenException;
use SmartAssert\ServiceClient\Exception\InvalidModelDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseDataException;
use SmartAssert\ServiceClient\Exception\InvalidResponseTypeException;
use SmartAssert\ServiceClient\Exception\NonSuccessResponseException;

class WorkerEventSender
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly ResultsClient $resultsClient,
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidJobTokenException
     * @throws InvalidModelDataException
     * @throws InvalidResponseDataException
     * @throws InvalidResponseTypeException
     * @throws JobNotFoundException
     * @throws NonSuccessResponseException
     */
    public function send(WorkerEvent $workerEvent): void
    {
        $this->resultsClient->addEvent($this->jobRepository->get()->resultsToken, $workerEvent);
    }
}
