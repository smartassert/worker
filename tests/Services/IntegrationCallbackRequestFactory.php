<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Entity\WorkerEventType;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

class IntegrationCallbackRequestFactory
{
    public function __construct(
        private readonly IntegrationJobProperties $jobProperties,
    ) {
    }

    /**
     * @param non-empty-string $reference
     * @param array<mixed>     $payload
     */
    public function create(WorkerEventType $type, string $reference, array $payload): RequestInterface
    {
        return new Request(
            'POST',
            $this->jobProperties->getCallbackUrl(),
            [
                'content-type' => 'application/json',
            ],
            (string) json_encode([
                'label' => $this->jobProperties->getLabel(),
                'type' => $type->value,
                'reference' => $reference,
                'payload' => $payload,
            ])
        );
    }
}
