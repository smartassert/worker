<?php

declare(strict_types=1);

namespace App\Tests\Services;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

class IntegrationDeliverEventRequestFactory
{
    public function __construct(
        private readonly IntegrationJobProperties $jobProperties,
    ) {
    }

    /**
     * @param non-empty-string $reference
     * @param array<mixed>     $payload
     */
    public function create(int $identifier, string $type, string $reference, array $payload): RequestInterface
    {
        return new Request(
            'POST',
            $this->jobProperties->getEventDeliveryUrl(),
            [
                'content-type' => 'application/json',
            ],
            (string) json_encode([
                'label' => $this->jobProperties->getLabel(),
                'identifier' => $identifier,
                'type' => $type,
                'reference' => $reference,
                'payload' => $payload,
            ])
        );
    }
}
