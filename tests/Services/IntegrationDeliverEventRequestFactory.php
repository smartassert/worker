<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
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
    public function create(
        int $identifier,
        WorkerEventScope $scope,
        WorkerEventOutcome $outcome,
        string $reference,
        array $payload
    ): RequestInterface {
        return new Request(
            'POST',
            $this->jobProperties->getEventDeliveryUrl(),
            [
                'content-type' => 'application/json',
            ],
            (string) json_encode([
                'label' => $this->jobProperties->getLabel(),
                'identifier' => $identifier,
                'type' => $scope->value . '/' . $outcome->value,
                'reference' => $reference,
                'payload' => $payload,
            ])
        );
    }
}
