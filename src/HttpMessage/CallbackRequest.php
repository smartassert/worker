<?php

declare(strict_types=1);

namespace App\HttpMessage;

use App\Entity\Callback\CallbackInterface;
use App\Entity\Job;
use GuzzleHttp\Psr7\Request as GuzzleRequest;

class CallbackRequest extends GuzzleRequest
{
    private const METHOD = 'POST';

    public function __construct(CallbackInterface $callback, Job $job)
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
                'reference' => $callback->getReference(),
                'payload' => $callback->getPayload(),
            ])
        );
    }
}
