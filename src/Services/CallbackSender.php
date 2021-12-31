<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Callback\CallbackInterface;
use App\Exception\NonSuccessfulHttpResponseException;
use App\HttpMessage\CallbackRequest;
use App\Services\EntityStore\JobStore;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as HttpClientInterface;

class CallbackSender
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private JobStore $jobStore
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws NonSuccessfulHttpResponseException
     */
    public function send(CallbackInterface $callback): void
    {
        if (false === $this->jobStore->has()) {
            return;
        }

        $job = $this->jobStore->get();
        $request = new CallbackRequest($callback, $job);
        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() >= 300) {
            throw new NonSuccessfulHttpResponseException($callback, $response);
        }
    }
}
