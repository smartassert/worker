<?php

declare(strict_types=1);

namespace App\HttpMessage;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Job;
use GuzzleHttp\Psr7\Request as GuzzleRequest;

class CallbackRequest extends GuzzleRequest
{
    private const METHOD = 'POST';

    public function __construct(CallbackEntity $callback, Job $job)
    {
        parent::__construct(
            self::METHOD,
            (string) $job->getCallbackUrl(),
            [
                'content-type' => 'application/json',
            ],
            (string) json_encode([
                'label' => $job->getLabel(),
                'type' => $callback->getType(),
                'payload' => $callback->getPayload(),
            ])
        );
    }
}
