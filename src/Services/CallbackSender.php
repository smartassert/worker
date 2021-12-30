<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Callback\CallbackInterface;
use App\HttpMessage\CallbackRequest;
use App\Model\SendCallbackResult;
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

    public function send(CallbackInterface $callback): ?SendCallbackResult
    {
        if (false === $this->jobStore->has()) {
            return null;
        }

        $job = $this->jobStore->get();
        $request = new CallbackRequest($callback, $job);

        try {
            $resultContext = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $httpClientException) {
            $resultContext = $httpClientException;
        }

        return new SendCallbackResult($callback, $resultContext);
    }
}
